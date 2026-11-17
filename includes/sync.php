<?php
// includes/sync.php — change feed (pull) and idempotent mutations (push) for offline devices
declare(strict_types=1);

require_once __DIR__ . '/sales.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/settings.php';

/** entity => [table, columns sent to devices] */
function sync_entities(): array
{
    return [
        'location' => ['locations', ['id', 'name', 'is_active']],
        'category' => ['product_categories', ['id', 'name', 'is_active']],
        'brand'    => ['brands', ['id', 'name', 'status']],
        'customer' => ['customers', ['id', 'name', 'phone', 'email', 'address', 'credit_limit', 'is_active']],
        'product'  => ['products', ['id', 'category_id', 'brand_id', 'name', 'sku', 'barcode', 'unit_type', 'unit_name', 'pieces_per_box',
                                    'wholesale_price', 'retail_price', 'is_active', 'images', 'updated_at']],
        'stock'    => ['stock_by_location', ['product_id', 'location_id', 'qty_base', 'low_level_base']],
    ];
}

function sync_head(mysqli $db): int
{
    return (int)($db->query("SELECT COALESCE(MAX(id), 0) FROM sync_changes")->fetch_row()[0] ?? 0);
}

/** Casts numeric strings so devices get numbers, and reduces images to the first path. */
function sync_shape(string $entity, array $row): array
{
    $ints = ['id', 'category_id', 'brand_id', 'product_id', 'location_id', 'is_active', 'pieces_per_box'];
    $nums = ['wholesale_price', 'retail_price', 'credit_limit', 'qty_base', 'low_level_base'];
    foreach ($row as $k => $v) {
        if ($v === null) continue;
        if (in_array($k, $ints, true)) $row[$k] = (int)$v;
        elseif (in_array($k, $nums, true)) $row[$k] = (float)$v;
    }
    if ($entity === 'product') {
        $rawImages = trim((string)($row['images'] ?? ''));
        $imgs = json_decode($rawImages, true);
        $image = null;
        if (is_array($imgs)) {
            foreach ($imgs as $img) {
                if (is_string($img) && trim($img) !== '') {
                    $image = trim($img);
                    break;
                }
                if (is_array($img)) {
                    foreach (['path', 'url', 'src', 'file'] as $key) {
                        if (isset($img[$key]) && is_string($img[$key]) && trim($img[$key]) !== '') {
                            $image = trim($img[$key]);
                            break 2;
                        }
                    }
                }
            }
        } elseif ($rawImages !== '' && $rawImages !== '[]' && $rawImages !== 'null') {
            // Older product rows stored one path directly instead of JSON.
            $image = $rawImages;
        }
        $row['image'] = $image;
        unset($row['images']);
    }
    if ($entity === 'stock') $row['key'] = $row['product_id'] . ':' . $row['location_id'];
    return $row;
}

function sync_fetch_rows(mysqli $db, string $entity, array $keys): array
{
    [$table, $cols] = sync_entities()[$entity];
    $list = '`' . implode('`,`', $cols) . '`';
    $out = [];
    foreach (array_chunk($keys, 400) as $chunk) {
        if ($entity === 'stock') {
            $conds = []; $args = [];
            foreach ($chunk as $k) { [$p, $l] = array_map('intval', explode(':', $k) + [0, 0]); $conds[] = '(product_id = ? AND location_id = ?)'; $args[] = $p; $args[] = $l; }
            $st = $db->prepare("SELECT $list FROM `$table` WHERE " . implode(' OR ', $conds));
            $st->bind_param(str_repeat('i', count($args)), ...$args);
        } else {
            $ids = array_map('intval', $chunk);
            $st = $db->prepare("SELECT $list FROM `$table` WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $st->bind_param(str_repeat('i', count($ids)), ...$ids);
        }
        $st->execute();
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $shaped = sync_shape($entity, $r);
            $out[$entity === 'stock' ? $shaped['key'] : (string)$shaped['id']] = $shaped;
        }
        $st->close();
    }
    return $out;
}

/** The whole dataset, for a first sync on hosts without triggers or when a device fell too far behind. */
function sync_snapshot(mysqli $db): array
{
    $changes = [];
    foreach (sync_entities() as $entity => [$table, $cols]) {
        $list = '`' . implode('`,`', $cols) . '`';
        $rows = $db->query("SELECT $list FROM `$table`")->fetch_all(MYSQLI_ASSOC);
        $changes[$entity] = ['upserts' => array_map(static function ($r) use ($entity) { return sync_shape($entity, $r); }, $rows), 'deletes' => []];
    }
    return $changes;
}

/**
 * Changes after version $since. The latest state of each record is sent once, however many times it
 * changed, and the device stores `next` only after it has saved the page — so a dropped connection
 * just repeats a page and nothing is skipped.
 */
function sync_pull(mysqli $db, int $since, int $limit = 500): array
{
    $limit = max(50, min(2000, $limit));
    $head = sync_head($db);
    $floor = (int)setting('sync_floor', '0');
    $snapshotMode = setting('sync_mode', 'triggers') !== 'triggers';

    if ($snapshotMode || $since < $floor || $since > $head) {
        return ['full' => true, 'since' => $since, 'next' => $head, 'has_more' => false, 'changes' => sync_snapshot($db)];
    }

    $st = $db->prepare("SELECT id, entity, entity_key, op FROM sync_changes WHERE id > ? ORDER BY id LIMIT ?");
    $st->bind_param('ii', $since, $limit);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $next = $since;
    $latest = []; // entity => key => true (the row's current state is what matters, not each step)
    foreach ($rows as $r) {
        $next = (int)$r['id'];
        if (isset(sync_entities()[$r['entity']])) $latest[$r['entity']][$r['entity_key']] = true;
    }
    $changes = [];
    foreach ($latest as $entity => $keys) {
        $keys = array_map('strval', array_keys($keys));
        $found = sync_fetch_rows($db, $entity, $keys);
        $changes[$entity] = ['upserts' => array_values($found), 'deletes' => array_values(array_diff($keys, array_map('strval', array_keys($found))))];
    }
    return ['full' => false, 'since' => $since, 'next' => $next, 'has_more' => count($rows) === $limit, 'changes' => $changes];
}

/* ---------------- push ---------------- */

class SyncReject extends RuntimeException
{
    public array $extra;
    public function __construct(string $message, array $extra = []) { parent::__construct($message); $this->extra = $extra; }
}

function sync_valid_uuid(string $u): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $u);
}

/** The stored outcome of an earlier mutation, or null. */
function sync_mutation_result(mysqli $db, string $uuid): ?array
{
    $st = $db->prepare("SELECT status, result_json FROM sync_mutations WHERE client_uuid = ? LIMIT 1");
    $st->bind_param('s', $uuid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) return null;
    return ['status' => $row['status'], 'result' => json_decode((string)$row['result_json'], true) ?: []];
}

/**
 * Applies a batch in the order the device made them. Each mutation is applied once: a repeat
 * returns the first outcome with duplicate=true. One rejected mutation does not stop the rest.
 */
function sync_push(mysqli $db, int $deviceId, int $uid, array $mutations): array
{
    $results = [];
    foreach (array_slice($mutations, 0, 200) as $m) {
        $uuid = strtolower((string)($m['uuid'] ?? ''));
        $kind = (string)($m['kind'] ?? '');
        if (!sync_valid_uuid($uuid)) { $results[] = ['uuid' => $uuid, 'status' => 'rejected', 'error' => 'Missing or malformed uuid']; continue; }

        $prev = sync_mutation_result($db, $uuid);
        if ($prev) { $results[] = ['uuid' => $uuid, 'status' => $prev['status'], 'duplicate' => true] + $prev['result']; continue; }

        try {
            $result = sync_apply($db, $uid, $uuid, $kind, is_array($m['payload'] ?? null) ? $m['payload'] : [], (string)($m['created_at'] ?? ''));
            $status = 'applied';
        } catch (SyncReject | SaleError | StockError $e) {
            @$db->rollback();
            $status = 'rejected';
            $result = ['error' => $e->getMessage()] + ($e instanceof SyncReject ? $e->extra : []);
        } catch (Throwable $e) {
            @$db->rollback();
            error_log("[sync.push] $kind $uuid: " . $e->getMessage());
            // Not recorded: the device keeps the mutation queued and tries again later
            $results[] = ['uuid' => $uuid, 'status' => 'retry', 'error' => 'Server problem. Will retry.'];
            continue;
        }
        $json = json_encode($result);
        $st = $db->prepare("INSERT IGNORE INTO sync_mutations (device_id, client_uuid, kind, status, result_json) VALUES (?,?,?,?,?)");
        $st->bind_param('issss', $deviceId, $uuid, $kind, $status, $json);
        $st->execute();
        $st->close();
        $results[] = ['uuid' => $uuid, 'status' => $status] + $result;
    }
    return $results;
}

function sync_apply(mysqli $db, int $uid, string $uuid, string $kind, array $p, string $createdAt): array
{
    switch ($kind) {
        case 'sale.create':
            if (!user_has_permission('pos.create')) throw new SyncReject('You are not allowed to make sales.');
            // If the server crashed after saving the sale but before recording the mutation, find it again
            $st = $db->prepare("SELECT id, doc_no, grand_total, tax_total, amount_paid, balance, review_note FROM sales WHERE client_uuid = ? LIMIT 1");
            $st->bind_param('s', $uuid);
            $st->execute();
            $existing = $st->get_result()->fetch_assoc();
            $st->close();
            if ($existing) {
                return ['sale_id' => (int)$existing['id'], 'doc_no' => $existing['doc_no'], 'grand_total' => (float)$existing['grand_total'], 'tax_total' => (float)$existing['tax_total'],
                        'amount_paid' => (float)$existing['amount_paid'], 'balance' => (float)$existing['balance'], 'needs_review' => $existing['review_note']];
            }
            // A customer created offline in the same batch is referenced by that mutation's uuid
            if (!empty($p['customer_ref']) && empty($p['customer_id'])) {
                $prev = sync_mutation_result($db, strtolower((string)$p['customer_ref']));
                if ($prev && $prev['status'] === 'applied') $p['customer_id'] = (int)($prev['result']['id'] ?? 0);
            }
            return sale_create($db, $p, $uid, ['offline' => true, 'client_uuid' => $uuid, 'client_ref' => $p['client_ref'] ?? null, 'sold_at' => $p['sold_at'] ?? $createdAt]);

        case 'customer.create':
            if (!user_has_permission('contacts.create') && !user_has_permission('pos.create')) throw new SyncReject('You are not allowed to add customers.');
            $name = trim((string)($p['name'] ?? ''));
            if ($name === '') throw new SyncReject('Customer name is required.');
            $phone = trim((string)($p['phone'] ?? ''));
            // The same person added on two devices becomes one record
            if ($phone !== '') {
                $st = $db->prepare("SELECT id FROM customers WHERE phone = ? AND name = ? LIMIT 1");
                $st->bind_param('ss', $phone, $name);
                $st->execute();
                $dup = $st->get_result()->fetch_row();
                $st->close();
                if ($dup) return ['id' => (int)$dup[0], 'merged' => true];
            }
            $email = trim((string)($p['email'] ?? ''));
            $address = trim((string)($p['address'] ?? ''));
            $st = $db->prepare("INSERT INTO customers (name, phone, email, address, created_at) VALUES (?,?,?,?,NOW())");
            $st->bind_param('ssss', $name, $phone, $email, $address);
            if (!$st->execute()) throw new RuntimeException($st->error);
            $id = (int)$st->insert_id;
            $st->close();
            audit_log('customers.add', 'customers', (string)$id, "Added from a device: $name");
            return ['id' => $id];

        case 'product.update':
            if (!user_has_permission('products.update')) throw new SyncReject('You are not allowed to edit products.');
            $id = (int)($p['id'] ?? 0);
            $fields = is_array($p['fields'] ?? null) ? $p['fields'] : [];
            $base = is_array($p['base'] ?? null) ? $p['base'] : [];
            $allowed = ['name' => 's', 'barcode' => 's', 'retail_price' => 'd', 'wholesale_price' => 'd'];
            $fields = array_intersect_key($fields, $allowed);
            if ($id <= 0 || !$fields) throw new SyncReject('Nothing to change.');

            $db->begin_transaction();
            $st = $db->prepare("SELECT id, name, barcode, retail_price, wholesale_price FROM products WHERE id = ? FOR UPDATE");
            $st->bind_param('i', $id);
            $st->execute();
            $cur = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$cur) { $db->rollback(); throw new SyncReject('That product no longer exists.'); }
            // Someone else changed the same field since this device last saw it: keep theirs, tell the device
            $same = static function ($a, $b, string $t): bool { return $t === 'd' ? abs((float)$a - (float)$b) < 0.005 : (string)$a === (string)$b; };
            foreach ($fields as $k => $v) {
                if (array_key_exists($k, $base) && !$same($cur[$k], $base[$k], $allowed[$k]) && !$same($cur[$k], $v, $allowed[$k])) {
                    $db->rollback();
                    throw new SyncReject('"' . $cur['name'] . '" was changed by someone else. Review and try again.', ['conflict' => true, 'server' => sync_shape('product', $cur)]);
                }
            }
            if (isset($fields['name']) && trim((string)$fields['name']) === '') { $db->rollback(); throw new SyncReject('Product name is required.'); }
            foreach (['retail_price', 'wholesale_price'] as $k) if (isset($fields[$k]) && (float)$fields[$k] < 0) { $db->rollback(); throw new SyncReject('Prices cannot be negative.'); }
            if (array_key_exists('barcode', $fields)) {
                $bc = trim((string)$fields['barcode']);
                $fields['barcode'] = $bc === '' ? null : $bc;
                if ($bc !== '') {
                    $st = $db->prepare("SELECT name FROM products WHERE barcode = ? AND id <> ? LIMIT 1");
                    $st->bind_param('si', $bc, $id);
                    $st->execute();
                    $other = $st->get_result()->fetch_row();
                    $st->close();
                    if ($other) { $db->rollback(); throw new SyncReject('That barcode already belongs to "' . $other[0] . '".'); }
                }
            }
            $sets = []; $types = ''; $args = [];
            foreach ($fields as $k => $v) { $sets[] = "`$k` = ?"; $types .= $allowed[$k]; $args[] = $v; }
            $types .= 'i'; $args[] = $id;
            $st = $db->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?");
            $st->bind_param($types, ...$args);
            if (!$st->execute()) { $err = $st->error; $db->rollback(); throw new RuntimeException($err); }
            $st->close();
            $db->commit();
            audit_log('products.update', 'product', (string)$id, 'Changed from a device: ' . implode(', ', array_keys($fields)));
            return ['id' => $id];

        case 'stock.adjust':
        case 'stock.receive':
            if (!user_has_permission('products.update')) throw new SyncReject('You are not allowed to change stock.');
            $pid = (int)($p['product_id'] ?? 0);
            $loc = (int)($p['location_id'] ?? 0);
            $delta = round((float)($p['delta'] ?? 0), 3);
            if ($kind === 'stock.receive' && $delta <= 0) throw new SyncReject('Received quantity must be above zero.');
            if ($delta == 0.0) throw new SyncReject('Nothing to change.');
            $note = mb_substr(trim((string)($p['reason'] ?? '')), 0, 200) ?: ($kind === 'stock.receive' ? 'Received (device)' : 'Adjustment (device)');
            $db->begin_transaction();
            // A change of +3 or -2, never "set to 7": two devices adjusting the same product both count
            $r = stock_move($db, $pid, $loc, $delta, $kind === 'stock.receive' ? 'stock_in' : 'adjustment', 'device', $uuid, $note, $uid);
            $db->commit();
            audit_log($kind === 'stock.receive' ? 'stock.in' : 'stock.adjust', 'product', (string)$pid, "$delta at location $loc from a device");
            return ['qty' => $r['after']];
    }
    throw new SyncReject('This app version sent something the server does not understand. Update the app.');
}
