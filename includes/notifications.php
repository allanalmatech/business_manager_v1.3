<?php
// includes/notifications.php
declare(strict_types=1);

/** Reads one value from the settings table. */
function setting_get(string $key, string $default = ''): string
{
    $db = $GLOBALS['db'] ?? null;
    if (!$db instanceof mysqli) return $default;
    $st = $db->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
    if (!$st) return $default;
    $st->bind_param('s', $key);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return ($row && $row['value'] !== null && $row['value'] !== '') ? (string)$row['value'] : $default;
}

/**
 * Sends a plain-text email through the server's mail transport and records
 * the attempt in email_log. Returns true when the server accepted it.
 */
function send_email(string $to, string $subject, string $body, string $toName = ''): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $fromName  = setting_get('business_name', 'Business Manager');
    $fromEmail = setting_get('business_email', '');
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $fromEmail = 'no-reply@' . ($host !== '' ? $host : 'localhost');
    }
    $clean = static function (string $v): string { return trim(str_replace(["\r", "\n"], ' ', $v)); };

    $headers = [
        'From: ' . mb_encode_mimeheader($clean($fromName)) . ' <' . $fromEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $ok = @mail($to, mb_encode_mimeheader($clean($subject)), $body, implode("\r\n", $headers));

    $db = $GLOBALS['db'] ?? null;
    if ($db instanceof mysqli) {
        $status = $ok ? 'sent' : 'failed';
        $err = $ok ? null : 'Server mail transport rejected the message';
        $st = $db->prepare("INSERT INTO email_log (to_email, to_name, subject, body, status, provider, error_message) VALUES (?,?,?,?,?, 'php_mail', ?)");
        if ($st) {
            $st->bind_param('ssssss', $to, $toName, $subject, $body, $status, $err);
            $st->execute();
            $st->close();
        }
    }
    return $ok;
}
