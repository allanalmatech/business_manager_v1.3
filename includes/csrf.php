<?php
// includes/csrf.php
declare(strict_types=1);

/**
 * One CSRF token per session, enforced centrally for every state-changing
 * request made by a signed-in user. Pages get the token through the
 * <meta name="csrf-token"> tag in the layout header, which also patches
 * fetch/XHR/forms to send it automatically.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    // Legacy key used by older pages; always the same value.
    $_SESSION['csrf_token'] = $_SESSION['csrf'];
    return (string)$_SESSION['csrf'];
}

function csrf_request_token(): string
{
    $h = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($h !== '') return $h;

    foreach (['csrf', 'csrf_token', '_csrf'] as $k) {
        if (isset($_POST[$k]) && is_string($_POST[$k]) && $_POST[$k] !== '') return $_POST[$k];
    }

    $ct = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($ct, 'application/json') !== false) {
        $j = json_decode((string)file_get_contents('php://input'), true);
        if (is_array($j)) {
            foreach (['csrf', 'csrf_token', '_csrf'] as $k) {
                if (isset($j[$k]) && is_string($j[$k]) && $j[$k] !== '') return $j[$k];
            }
        }
    }
    return '';
}

function csrf_is_valid(): bool
{
    $expected = (string)($_SESSION['csrf'] ?? '');
    $given = csrf_request_token();
    return $expected !== '' && $given !== '' && hash_equals($expected, $given);
}

function csrf_enforce(bool $isApiRequest = false): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;
    if (empty($_SESSION['user'])) return; // sign-in and reset forms check their own token
    if (csrf_is_valid()) {
        // Older handlers re-check a form field; give them the already-verified token.
        $_POST['csrf'] = $_POST['csrf_token'] = (string)$_SESSION['csrf'];
        return;
    }

    http_response_code(403);
    $msg = 'Your session token is missing or expired. Refresh the page and try again.';
    $wantsJson = $isApiRequest
        || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
        || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false;
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'success' => false, 'error' => $msg, 'message' => $msg]);
    } else {
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    }
    exit;
}
