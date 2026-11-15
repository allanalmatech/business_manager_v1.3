<?php
// includes/shifts.php
declare(strict_types=1);

/** True when this install has the shift tables. Selling must not depend on them. */
function shifts_available(mysqli $db): bool
{
    static $ok = null;
    if ($ok === null) {
        $r = @$db->query("SHOW TABLES LIKE 'shifts'");
        $ok = (bool)($r && $r->num_rows > 0);
    }
    return $ok;
}

function shift_current(mysqli $db, int $userId): ?array
{
    if (!shifts_available($db)) return null;
    $st = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    if (!$st) return null;
    $st->bind_param('i', $userId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

/** Cash that should be in the drawer, with the figures behind it. */
function shift_summary(mysqli $db, array $shift): array
{
    $id = (int)$shift['id'];
    $q = static function (mysqli $db, string $sql, int $id): array {
        $st = $db->prepare($sql);
        $st->bind_param('i', $id);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        return $rows;
    };
    $sales = $q($db, "SELECT COUNT(*) AS n, COALESCE(SUM(grand_total),0) AS total, COALESCE(SUM(balance),0) AS on_credit, COALESCE(SUM(tax_total),0) AS tax
                      FROM sales WHERE shift_id = ? AND status <> 'voided'", $id)[0];
    $byMethod = ['cash' => 0.0, 'mobile_money' => 0.0, 'bank' => 0.0];
    foreach ($q($db, "SELECT sp.method, COALESCE(SUM(sp.amount),0) AS amt FROM sale_payments sp JOIN sales s ON s.id = sp.sale_id
                      WHERE s.shift_id = ? AND s.status <> 'voided' GROUP BY sp.method", $id) as $r) {
        $byMethod[$r['method']] = (float)$r['amt'];
    }
    $moves = ['in' => 0.0, 'out' => 0.0];
    foreach ($q($db, "SELECT direction, COALESCE(SUM(amount),0) AS amt FROM shift_cash_movements WHERE shift_id = ? GROUP BY direction", $id) as $r) {
        $moves[$r['direction']] = (float)$r['amt'];
    }
    $expected = round((float)$shift['opening_float'] + $byMethod['cash'] + $moves['in'] - $moves['out'], 2);
    return [
        'sales_count' => (int)$sales['n'], 'sales_total' => (float)$sales['total'], 'on_credit' => (float)$sales['on_credit'], 'tax' => (float)$sales['tax'],
        'cash' => $byMethod['cash'], 'mobile_money' => $byMethod['mobile_money'], 'bank' => $byMethod['bank'],
        'cash_in' => $moves['in'], 'cash_out' => $moves['out'], 'opening_float' => (float)$shift['opening_float'],
        'expected_cash' => $expected,
    ];
}
