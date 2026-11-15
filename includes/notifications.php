<?php
// includes/notifications.php — outgoing email and SMS
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/** Kept for older callers. */
function setting_get(string $key, string $default = ''): string
{
    $v = setting($key, $default);
    return $v === '' ? $default : $v;
}

/* ======================= Email ======================= */

/** @return array{ok:bool, error:string} */
function send_email_result(string $to, string $subject, string $body, string $toName = ''): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Not a valid email address.'];

    $clean = static function (string $v): string { return trim(str_replace(["\r", "\n"], ' ', $v)); };
    $fromName  = $clean(setting('business_name'));
    $fromEmail = setting('business_email');
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $fromEmail = 'no-reply@' . ($host !== '' ? $host : 'localhost');
    }
    $subject = $clean($subject);
    $headers = [
        'From: ' . mb_encode_mimeheader($fromName) . ' <' . $fromEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $provider = setting('mail_transport') === 'smtp' ? 'smtp' : 'php_mail';
    $error = '';
    if ($provider === 'smtp') {
        try {
            smtp_send($fromEmail, $to, $subject, $body, $headers);
            $ok = true;
        } catch (Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }
    } else {
        $ok = @mail($to, mb_encode_mimeheader($subject), $body, implode("\r\n", $headers));
        if (!$ok) $error = 'The server\'s mail program rejected the message.';
    }

    $db = $GLOBALS['db'] ?? null;
    if ($db instanceof mysqli) {
        $status = $ok ? 'sent' : 'failed';
        $err = $ok ? null : $error;
        $st = $db->prepare("INSERT INTO email_log (to_email, to_name, subject, body, status, provider, error_message) VALUES (?,?,?,?,?,?,?)");
        if ($st) {
            $st->bind_param('sssssss', $to, $toName, $subject, $body, $status, $provider, $err);
            $st->execute();
            $st->close();
        }
    }
    return ['ok' => $ok, 'error' => $error];
}

function send_email(string $to, string $subject, string $body, string $toName = ''): bool
{
    return send_email_result($to, $subject, $body, $toName)['ok'];
}

/** Minimal SMTP client: SSL or STARTTLS, AUTH LOGIN. Throws RuntimeException with the server's reply. */
function smtp_send(string $from, string $to, string $subject, string $body, array $headers): void
{
    $host = setting('smtp_host');
    $port = (int)setting('smtp_port');
    $secure = setting('smtp_secure');
    $user = setting('smtp_user');
    $pass = setting('smtp_pass');
    if ($host === '' || $port <= 0) throw new RuntimeException('SMTP server and port are not set.');

    $errno = 0; $errstr = '';
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) throw new RuntimeException("Could not connect to $host:$port ($errstr).");
    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $out;
    };
    $cmd = static function (string $line, array $expect) use ($fp, $read): string {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        $reply = $read();
        if (!in_array((int)substr($reply, 0, 3), $expect, true)) {
            throw new RuntimeException('Mail server said: ' . trim($reply !== '' ? $reply : 'no reply'));
        }
        return $reply;
    };

    try {
        $me = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['SERVER_NAME'] ?? '')) ?: 'localhost';
        $cmd('', [220]);
        $cmd("EHLO $me", [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start encryption with the mail server.');
            }
            $cmd("EHLO $me", [250]);
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode($pass), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $msg = implode("\r\n", array_merge($headers, [
            'To: <' . $to . '>',
            'Subject: ' . mb_encode_mimeheader($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $me . '>',
        ])) . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $body));
        $cmd($msg . "\r\n.", [250]);
        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/* ======================= SMS ======================= */

/** Uganda-friendly normalisation: 07XXXXXXXX -> +2567XXXXXXXX; leaves other international numbers alone. */
function sms_normalize(string $number): string
{
    $n = preg_replace('/[^0-9+]/', '', $number);
    if ($n === '') return '';
    if ($n[0] === '+') return $n;
    if (strpos($n, '00') === 0) return '+' . substr($n, 2);
    if ($n[0] === '0' && strlen($n) === 10) return '+256' . substr($n, 1);
    if (strpos($n, '256') === 0) return '+' . $n;
    return $n;
}

/** @return array{ok:bool, status:int, body:string} */
function http_post(string $url, $data, array $headers = [], string $method = 'POST'): array
{
    if (!function_exists('curl_init')) return ['ok' => false, 'status' => 0, 'body' => 'The curl extension is not installed.'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok' => $body !== false && $status >= 200 && $status < 300, 'status' => $status, 'body' => $body === false ? $err : (string)$body];
}

/** @return array{ok:bool, error:string} */
function send_sms_result(string $to, string $message): array
{
    $provider = setting('sms_provider');
    $to = sms_normalize($to);
    if ($provider === 'none' || $provider === '') return ['ok' => false, 'error' => 'SMS is turned off in Settings.'];
    if (strlen(preg_replace('/\D/', '', $to)) < 9) return ['ok' => false, 'error' => 'Not a valid phone number.'];
    $message = trim($message);
    if ($message === '') return ['ok' => false, 'error' => 'The message is empty.'];

    $user = setting('sms_username');
    $key = setting('sms_api_key');
    $sender = setting('sms_sender_id');

    if ($provider === 'africastalking') {
        $fields = ['username' => $user, 'to' => $to, 'message' => $message];
        if ($sender !== '') $fields['from'] = $sender;
        $host = $user === 'sandbox' ? 'api.sandbox.africastalking.com' : 'api.africastalking.com';
        $r = http_post("https://$host/version1/messaging", http_build_query($fields),
            ['apiKey: ' . $key, 'Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
        $j = json_decode($r['body'], true);
        $rcp = $j['SMSMessageData']['Recipients'][0] ?? null;
        $ok = $r['ok'] && $rcp && in_array((int)($rcp['statusCode'] ?? 0), [100, 101, 102], true);
        $error = $ok ? '' : (string)($rcp['status'] ?? ($j['SMSMessageData']['Message'] ?? ('HTTP ' . $r['status'] . ' ' . mb_substr($r['body'], 0, 160))));
    } elseif ($provider === 'egosms') {
        $payload = json_encode(['method' => 'SendSms', 'userdata' => ['username' => $user, 'password' => $key],
            'msgdata' => [['number' => ltrim($to, '+'), 'message' => $message, 'senderid' => $sender]]]);
        $r = http_post('https://www.egosms.co/api/v1/json/', $payload, ['Content-Type: application/json']);
        $j = json_decode($r['body'], true);
        $ok = $r['ok'] && strtoupper((string)($j['Status'] ?? '')) === 'OK';
        $error = $ok ? '' : (string)($j['Message'] ?? ('HTTP ' . $r['status'] . ' ' . mb_substr($r['body'], 0, 160)));
    } else { // http: a link with {to} and {message}
        $tpl = setting('sms_http_url');
        if (!preg_match('~^https?://~i', $tpl) || strpos($tpl, '{to}') === false || strpos($tpl, '{message}') === false) {
            return ['ok' => false, 'error' => 'The SMS send link must start with http(s):// and contain {to} and {message}.'];
        }
        $url = str_replace(['{to}', '{message}'], [rawurlencode($to), rawurlencode($message)], $tpl);
        $r = http_post($url, null, [], 'GET');
        $ok = $r['ok'];
        $error = $ok ? '' : 'HTTP ' . $r['status'] . ' ' . mb_substr($r['body'], 0, 160);
    }
    return ['ok' => $ok, 'error' => $error];
}

function send_sms(string $to, string $message): bool
{
    return send_sms_result($to, $message)['ok'];
}
