<?php
// templates/layout/sidebar.php
// The menu is data: sections group pages by the job being done, each entry
// names the permission that reveals it. Rendering and active-state logic live below.

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
if (!function_exists('has_permission')) {
    require_once dirname(__DIR__, 2) . '/includes/rbac.php';
}

$reports = [
    ['Sales', '/modules/reports/sales.php', 'reports.sales.view'],
    ['Profit', '/modules/reports/profit.php', 'reports.profit.view'],
    ['Inventory', '/modules/reports/inventory.php', 'reports.inventory.view'],
    ['Installments', '/modules/reports/installments.php', 'reports.installments.view'],
    ['Expenses', '/modules/reports/expenses.php', 'reports.expenses.view'],
    ['Capital', '/modules/reports/capital.php', 'reports.capital.view'],
    ['B2B', '/modules/reports/b2b_report.php', 'reports.b2b.view'],
    ['Audit', '/modules/reports/audit.php', 'reports.audit.view'],
];

// [section label, [ entries ]]; entry = link [label, icon, href, perm] or group [label, icon, id, [[label, href, perm], ...]]
$menu = [
    ['', [
        ['Dashboard', 'bi-speedometer2', '/index.php', null],
    ]],
    ['Sell', [
        ['Point of Sale', 'bi-cart-plus', '/modules/pos/pos.php', 'pos.create'],
        ['Sales', 'bi-receipt-cutoff', 'sales', [
            ['Sales history', '/modules/pos/sales_history.php', 'pos.view'],
            ['Unpaid & pending', '/modules/pos/unpaid.php', 'pos.view'],
            ['Returns', '/modules/pos/returns.php', 'pos.void'],
            ['Shifts & cash-up', '/modules/pos/shifts.php', 'pos.create'],
        ]],
        ['Documents', 'bi-file-earmark-text', 'documents', [
            ['Receipts', '/modules/documents/receipts.php', 'documents.view'],
            ['Invoices', '/modules/documents/invoices.php', 'documents.view'],
            ['Delivery notes', '/modules/documents/delivery_notes.php', 'documents.view'],
            ['Email history', '/modules/documents/email_log.php', 'documents.view'],
        ]],
        ['Installments', 'bi-calendar-check', 'installments', [
            ['All installments', '/modules/installments/installments.php', 'installments.view'],
            ['Receive payment', '/modules/installments/installment_payment.php', 'installments.view'],
            ['Overdue actions', '/modules/installments/actions.php', 'installments.view'],
        ]],
    ]],
    ['Stock', [
        ['Products', 'bi-boxes', 'products', [
            ['All products', '/modules/products/products.php', 'products.view'],
            ['Categories', '/modules/products/categories.php', 'products.view'],
            ['Brands', '/modules/brands/index.php', 'products.view'],
            ['Price updates', '/modules/products/price_updates.php', 'products.view'],
        ]],
        ['Inventory', 'bi-box-seam', 'inventory', [
            ['Stock levels', '/modules/products/stock_levels.php', 'products.view'],
            ['Receive stock', '/modules/products/stock_in.php', 'products.view'],
            ['Adjustments', '/modules/products/stock_adjustments.php', 'products.view'],
            ['Movements', '/modules/products/stock_movements.php', 'products.view'],
            ['Stores', '/modules/stores/stores.php', 'stores.view'],
        ]],
        ['Purchasing', 'bi-bag-check', 'purchasing', [
            ['Shopping list', '/modules/procurement/shopping_list.php', 'procurement.view'],
            ['Suggested list', '/modules/procurement/suggested_list.php', 'procurement.view'],
            ['Wanted items', '/modules/procurement/wanted_items.php', 'procurement.view'],
            ['Suppliers', '/modules/products/suppliers.php', 'products.view'],
        ]],
    ]],
    ['Money', [
        ['Finance', 'bi-bank', 'finance', [
            ['Expenses', '/modules/finance/expenses.php', 'finance.view'],
            ['Capital in', '/modules/finance/capital_in.php', 'finance.view'],
            ['Capital out', '/modules/finance/capital_out.php', 'finance.view'],
            ['Banking', '/modules/finance/banking.php', 'finance.view'],
            ['Vouchers', '/modules/finance/vouchers.php', 'finance.view'],
            ['Reconciliation', '/modules/finance/reconciliation.php', 'finance.view'],
        ]],
        ['Reports', 'bi-bar-chart-line', 'reports', $reports],
    ]],
    ['People', [
        ['Contacts', 'bi-people', 'contacts', [
            ['All contacts', '/modules/contacts/contacts.php', 'contacts.view'],
            ['Customers', '/modules/contacts/customers.php', 'contacts.view'],
            ['Suppliers', '/modules/contacts/suppliers.php', 'contacts.view'],
            ['Staff', '/modules/contacts/staff.php', 'contacts.view'],
            ['Categories & tags', '/modules/contacts/categories_tags.php', 'contacts.view'],
            ['Export', '/modules/contacts/export.php', 'contacts.view'],
        ]],
        ['Messaging', 'bi-chat-dots', 'messaging', [
            ['Inbox', '/modules/messaging/inbox.php', null],
            ['Send message', '/modules/messaging/send.php', 'messaging.view'],
            ['Templates', '/modules/messaging/templates.php', 'messaging.view'],
            ['Queue', '/modules/messaging/queue.php', 'messaging.view'],
            ['Logs', '/modules/messaging/logs.php', 'messaging.view'],
        ]],
    ]],
    ['Admin', [
        ['Settings', 'bi-sliders', 'settings', [
            ['Business settings', '/modules/admin/settings.php', 'admin.exclusive'],
            ['Themes', '/modules/admin/themes.php', 'admin.exclusive'],
            ['Payments', '/modules/admin/payment_settings.php', 'admin.exclusive'],
            ['Reminders', '/modules/admin/reminders.php', 'admin.exclusive'],
        ]],
        ['Access', 'bi-shield-lock', 'access', [
            ['Users', '/modules/admin/users.php', 'admin.exclusive'],
            ['Roles', '/modules/admin/roles.php', 'admin.exclusive'],
            ['Permissions', '/modules/admin/permissions.php', 'admin.exclusive'],
            ['Approvals', '/modules/admin/approvals.php', 'admin.exclusive'],
        ]],
        ['System', 'bi-hdd-rack', 'system', [
            ['Updates', '/modules/admin/updates.php', 'admin.exclusive'],
            ['Update history', '/modules/admin/update_history.php', 'admin.exclusive'],
            ['Audit trail', '/modules/admin/audit_trail.php', 'admin.exclusive'],
            ['Devices', '/modules/admin/devices.php', 'admin.exclusive'],
            ['Database reset', '/modules/admin/database_reset.php', 'admin.exclusive'],
        ]],
    ]],
];

$projectRoot = dirname(__DIR__, 2);
$scriptPath = '/' . ltrim(substr(str_replace('\\', '/', (string)realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))), strlen(str_replace('\\', '/', (string)realpath($projectRoot)))), '/');
$allowed = static function (?string $perm): bool { return $perm === null || has_permission($perm); };
$exists = static function (string $href) use ($projectRoot): bool { return is_file($projectRoot . $href); };
$e = static function (string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main menu">
  <div class="sb-brand">
    <span class="sb-brand-mark" aria-hidden="true"><i class="bi bi-briefcase-fill"></i></span>
    <span class="sb-brand-text">
      <span class="sb-brand-name">Business</span>
      <span class="sb-brand-sub">Manager</span>
    </span>
    <button class="sb-close d-lg-none" type="button" id="btnSidebarClose" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
  </div>

  <nav class="sb-nav">
    <?php foreach ($menu as [$section, $entries]):
        $html = '';
        foreach ($entries as $entry) {
            if (is_array($entry[3])) {
                [$label, $icon, $id, $children] = $entry;
                $links = '';
                $groupActive = false;
                foreach ($children as [$cLabel, $cHref, $cPerm]) {
                    if (!$allowed($cPerm) || !$exists($cHref)) continue;
                    $active = ($scriptPath === $cHref);
                    $groupActive = $groupActive || $active;
                    $links .= '<a class="sb-sublink' . ($active ? ' active' : '') . '"' . ($active ? ' aria-current="page"' : '') . ' href="' . $e($BASE_URL . $cHref) . '">' . $e($cLabel) . '</a>';
                }
                if ($links === '') continue;
                $html .= '<div class="sb-group' . ($groupActive ? ' open has-active' : '') . '" data-group="' . $e($id) . '">'
                       . '<button class="sb-link sb-toggle" type="button" aria-expanded="' . ($groupActive ? 'true' : 'false') . '" aria-controls="sb-sub-' . $e($id) . '">'
                       . '<i class="bi ' . $e($icon) . ' sb-ico" aria-hidden="true"></i><span class="sb-label">' . $e($label) . '</span>'
                       . '<i class="bi bi-chevron-down sb-caret" aria-hidden="true"></i></button>'
                       . '<div class="sb-sub" id="sb-sub-' . $e($id) . '"><div class="sb-sub-inner"><div class="sb-sub-title">' . $e($label) . '</div>' . $links . '</div></div></div>';
            } else {
                [$label, $icon, $href, $perm] = $entry;
                if (!$allowed($perm) || !$exists($href)) continue;
                $active = ($scriptPath === $href);
                $html .= '<a class="sb-link' . ($active ? ' active' : '') . '"' . ($active ? ' aria-current="page"' : '') . ' href="' . $e($BASE_URL . $href) . '" title="' . $e($label) . '">'
                       . '<i class="bi ' . $e($icon) . ' sb-ico" aria-hidden="true"></i><span class="sb-label">' . $e($label) . '</span></a>';
            }
        }
        if ($html === '') continue;
        if ($section !== '') echo '<div class="sb-section">' . $e($section) . '</div>';
        echo $html;
    endforeach; ?>
  </nav>

  <div class="sb-foot">
    <a class="sb-link" href="<?= $e($BASE_URL) ?>/logout.php" title="Sign out">
      <i class="bi bi-box-arrow-right sb-ico" aria-hidden="true"></i><span class="sb-label">Sign out</span>
    </a>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
