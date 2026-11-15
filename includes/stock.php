<?php
// includes/stock.php
declare(strict_types=1);

/** A stock problem the user can act on; the message is safe to show. */
class StockError extends RuntimeException {}

/**
 * The one way to change stock. Call it inside a transaction.
 * Locks the product/location row, applies the change, refuses to go below
 * zero, and writes the movement ledger with the quantity before and after.
 *
 * @param string $type one of: stock_in, sale, return, adjustment, transfer
 * @return array{before: float, after: float}
 */
function stock_move(
    mysqli $db,
    int $productId,
    int $locationId,
    float $delta,
    string $type,
    ?string $refType,
    ?string $refId,
    string $note,
    int $userId,
    ?int $fromLocationId = null,
    ?int $toLocationId = null
): array {
    if ($productId <= 0 || $locationId <= 0) throw new StockError('Choose a product and a location.');

    $st = $db->prepare("INSERT IGNORE INTO stock_by_location (product_id, location_id, qty_base, low_level_base) VALUES (?, ?, 0, 0)");
    $st->bind_param('ii', $productId, $locationId);
    $st->execute();
    $st->close();

    $st = $db->prepare("SELECT qty_base FROM stock_by_location WHERE product_id = ? AND location_id = ? FOR UPDATE");
    $st->bind_param('ii', $productId, $locationId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) throw new RuntimeException('Stock row could not be locked.');

    $before = (float)$row['qty_base'];
    $after = round($before + $delta, 3);
    if ($after < -0.0005) {
        throw new StockError('Not enough stock: ' . rtrim(rtrim(number_format($before, 3, '.', ''), '0'), '.') . ' available.');
    }
    $after = max(0.0, $after);

    $st = $db->prepare("UPDATE stock_by_location SET qty_base = ? WHERE product_id = ? AND location_id = ?");
    $st->bind_param('dii', $after, $productId, $locationId);
    if (!$st->execute()) throw new RuntimeException('Stock update failed: ' . $st->error);
    $st->close();

    $from = $fromLocationId ?? ($delta < 0 ? $locationId : null);
    $to   = $toLocationId ?? ($delta >= 0 ? $locationId : null);
    $note = mb_substr($note, 0, 255);
    $st = $db->prepare("INSERT INTO stock_movements
        (product_id, from_location_id, to_location_id, movement_type, qty_change, qty_before, qty_after, reference_type, reference_id, note, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    if (!$st) throw new RuntimeException('Movement insert failed: ' . $db->error);
    $st->bind_param('iiisdddsssi', $productId, $from, $to, $type, $delta, $before, $after, $refType, $refId, $note, $userId);
    if (!$st->execute()) throw new RuntimeException('Movement insert failed: ' . $st->error);
    $st->close();

    return ['before' => $before, 'after' => $after];
}

/** Quantity of a product already returned against a sale (rejected/cancelled returns excluded). */
function stock_returned_qty(mysqli $db, int $saleId, int $productId, int $exceptReturnId = 0): float
{
    $st = $db->prepare("SELECT COALESCE(SUM(ri.quantity),0) FROM return_items ri
                        JOIN sale_returns r ON r.id = ri.return_id
                        WHERE r.sale_id = ? AND ri.product_id = ? AND r.id <> ?
                          AND r.status NOT IN ('rejected','cancelled')");
    $st->bind_param('iii', $saleId, $productId, $exceptReturnId);
    $st->execute();
    $q = (float)$st->get_result()->fetch_row()[0];
    $st->close();
    return $q;
}
