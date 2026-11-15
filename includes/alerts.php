<?php
// includes/alerts.php — things that need someone's attention, computed live from the data
declare(strict_types=1);

/**
 * @return array<int, array{key:string,title:string,detail:string,count:int,level:string,href:string,icon:string}>
 */
function alerts_for_user(mysqli $db, string $baseUrl): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $count = static function (mysqli $db, string $sql): int {
        $r = $db->query($sql);
        $row = $r ? $r->fetch_row() : null;
        return (int)($row[0] ?? 0);
    };
    $can = static function (string $p): bool {
        return function_exists('user_has_permission') ? user_has_permission($p) : false;
    };
    $out = [];
    $add = static function (array &$out, string $key, int $n, string $title, string $detail, string $level, string $href, string $icon): void {
        if ($n > 0) $out[] = ['key' => $key, 'title' => $title, 'detail' => $detail, 'count' => $n, 'level' => $level, 'href' => $href, 'icon' => $icon];
    };

    if ($can('products.view')) {
        $n = $count($db, "SELECT COUNT(*) FROM stock_by_location s JOIN products p ON p.id = s.product_id
                          WHERE p.is_active = 1 AND s.low_level_base > 0 AND s.qty_base <= s.low_level_base");
        $add($out, 'low_stock', $n, 'Low stock', "$n product(s) are at or below their reorder level.", 'warning', "$baseUrl/modules/products/stock_levels.php", 'bi-box-seam');

        $n = $count($db, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND track_expiry = 1 AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $add($out, 'expiring', $n, 'Expiring soon', "$n product(s) expire within 30 days.", 'warning', "$baseUrl/modules/products/products.php", 'bi-hourglass-split');
    }
    if ($can('pos.view')) {
        $n = $count($db, "SELECT COUNT(*) FROM sales WHERE status <> 'voided' AND payment_status IN ('unpaid','partial') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $add($out, 'unpaid', $n, 'Unpaid for over a week', "$n sale document(s) still have a balance.", 'danger', "$baseUrl/modules/pos/unpaid.php", 'bi-receipt');

        $n = $count($db, "SELECT COUNT(*) FROM sale_returns WHERE status IN ('pending','approved')");
        $add($out, 'returns', $n, 'Returns waiting', "$n return(s) are not completed yet.", 'info', "$baseUrl/modules/pos/returns.php", 'bi-arrow-return-left');
    }
    if ($can('installments.view')) {
        $n = $count($db, "SELECT COUNT(*) FROM installments WHERE status NOT IN ('paid','cancelled') AND due_date < CURDATE()");
        $add($out, 'installments', $n, 'Overdue installments', "$n installment(s) are past their due date.", 'danger', "$baseUrl/modules/installments/installments.php", 'bi-calendar-x');
    }
    if (function_exists('is_super_admin') && is_super_admin()) {
        require_once __DIR__ . '/migrations.php';
        try { $n = count(migrations_pending($db)); } catch (Throwable $e) { $n = 0; }
        $add($out, 'migrations', $n, 'Database update needed', "$n database step(s) are waiting.", 'danger', "$baseUrl/modules/admin/updates.php", 'bi-database-gear');
    }
    return $cache = $out;
}

function alerts_total(array $alerts): int
{
    return array_sum(array_column($alerts, 'count'));
}
