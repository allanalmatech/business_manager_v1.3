<?php
// includes/uploads.php
declare(strict_types=1);

/**
 * The saved extension always comes from the file's detected content type,
 * never from the name the browser sent.
 */
const UPLOAD_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/** Returns a safe extension for image bytes on disk, or null if not an allowed image. */
function upload_image_ext_for_file(string $path): ?string
{
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $fi ? (string)finfo_file($fi, $path) : '';
    if ($fi) finfo_close($fi);
    if (!isset(UPLOAD_IMAGE_TYPES[$mime])) return null;
    if (@getimagesize($path) === false) return null;
    return UPLOAD_IMAGE_TYPES[$mime];
}

/** Same check for image bytes held in memory. */
function upload_image_ext_for_data(string $data): ?string
{
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $fi ? (string)finfo_buffer($fi, $data) : '';
    if ($fi) finfo_close($fi);
    if (!isset(UPLOAD_IMAGE_TYPES[$mime])) return null;
    if (@getimagesizefromstring($data) === false) return null;
    return UPLOAD_IMAGE_TYPES[$mime];
}

function upload_random_name(string $prefix, string $ext): string
{
    return $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
}

/**
 * True only for http(s) URLs whose host resolves to public addresses, so the
 * "fetch image from URL" feature cannot be pointed at the server's own network.
 */
function upload_url_is_public(string $url): bool
{
    $p = parse_url($url);
    if (!$p || !in_array(strtolower((string)($p['scheme'] ?? '')), ['http', 'https'], true)) return false;
    $host = (string)($p['host'] ?? '');
    if ($host === '') return false;
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    if (!$ips) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}
