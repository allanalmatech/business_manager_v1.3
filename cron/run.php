<?php
// cron/run.php — the one scheduled job. Run it once a day from the command line:
//   php /path/to/cron/run.php
// It sends payment reminders (when enabled in Settings) and tidies old temporary rows.
// Each reminder is recorded in reminder_log, so running the job twice never sends twice.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/cron/run.php';
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/notifications.php';

$db = $GLOBALS['db'];
$say = static function (string $m): void { echo '[' . date('Y-m-d H:i:s') . "] $m\n"; };

/** Sends on the configured channels unless this kind+record was reminded within $everyDays. */
function remind(mysqli $db, string $kind, int $refId, int $everyDays, ?string $phone, ?string $email, string $text): array
{
    $want = setting('reminder_channel');
    $channels = $want === 'both' ? ['sms', 'email'] : [$want];
    $sent = [];
    foreach ($channels as $ch) {
        $target = $ch === 'sms' ? (string)$phone : (string)$email;
        if ($target === '') continue;
        $st = $db->prepare("SELECT 1 FROM reminder_log WHERE kind = ? AND ref_id = ? AND channel = ? AND sent_on > DATE_SUB(CURDATE(), INTERVAL ? DAY) LIMIT 1");
        $st->bind_param('sisi', $kind, $refId, $ch, $everyDays);
        $st->execute();
        $already = (bool)$st->get_result()->fetch_row();
        $st->close();
        if ($already) continue;

        $r = $ch === 'sms' ? send_sms_result($target, $text) : send_email_result($target, 'Payment reminder from ' . setting('business_name'), $text);
        $status = $r['ok'] ? 'sent' : 'failed';
        $detail = mb_substr($r['ok'] ? $target : $r['error'], 0, 255);
        $st = $db->prepare("INSERT IGNORE INTO reminder_log (kind, ref_id, channel, sent_on, status, detail) VALUES (?,?,?,CURDATE(),?,?)");
        $st->bind_param('sisss', $kind, $refId, $ch, $status, $detail);
        $st->execute();
        $st->close();
        $sent[] = "$ch:$status";
    }
    return $sent;
}

$shop = setting('business_name');
$cur = setting('currency_symbol');
$fmt = static function ($v) use ($cur): string { return $cur . number_format((float)$v, (int)setting('decimal_places')); };

if (!setting_bool('reminders_enabled')) {
    $say('Reminders are turned off in Settings.');
} else {
    $before = max(0, (int)setting('reminder_days_before'));
    $every = max(1, (int)setting('reminder_unpaid_days'));

    // Installments: once shortly before the due date, then every $every days while overdue
    $st = $db->prepare("SELECT i.id, i.reference, i.amount_due - i.amount_paid AS owed, i.due_date, c.name, c.phone, c.email,
                               (i.due_date < CURDATE()) AS overdue
                        FROM installments i JOIN contacts c ON c.id = i.contact_id
                        WHERE i.status NOT IN ('paid','cancelled','completed') AND i.amount_due > i.amount_paid
                          AND i.due_date IS NOT NULL AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)");
    $st->bind_param('i', $before);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $text = $r['overdue']
            ? "Hello {$r['name']}, your payment of {$fmt($r['owed'])} to $shop was due on {$r['due_date']}. Please pay as soon as you can. Thank you."
            : "Hello {$r['name']}, a reminder that {$fmt($r['owed'])} is due to $shop on {$r['due_date']}. Thank you.";
        $out = remind($db, $r['overdue'] ? 'installment_overdue' : 'installment_due', (int)$r['id'], $r['overdue'] ? $every : 3650, $r['phone'], $r['email'], $text);
        if ($out) $say("Installment #{$r['id']} ({$r['name']}): " . implode(', ', $out));
    }

    // Unpaid sales documents with a named customer
    $st = $db->prepare("SELECT s.id, s.doc_no, s.balance, c.name, c.phone, c.email
                        FROM sales s JOIN customers c ON c.id = s.customer_id
                        WHERE s.status <> 'voided' AND s.payment_status IN ('unpaid','partial') AND s.balance > 0
                          AND s.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->bind_param('i', $every);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $text = "Hello {$r['name']}, {$fmt($r['balance'])} is still unpaid on {$r['doc_no']} at $shop. Please pay when you can. Thank you.";
        $out = remind($db, 'sale_unpaid', (int)$r['id'], $every, $r['phone'], $r['email'], $text);
        if ($out) $say("{$r['doc_no']} ({$r['name']}): " . implode(', ', $out));
    }
}

// Housekeeping
$db->query("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
$db->query("DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
$db->query("DELETE FROM phone_scan_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
$db->query("DELETE FROM reminder_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)");
$say('Done.');
