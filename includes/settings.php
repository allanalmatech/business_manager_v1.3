<?php
// includes/settings.php — typed access to settings with schema defaults
declare(strict_types=1);

function settings_schema(): array
{
    static $s = null;
    return $s ?? ($s = require __DIR__ . '/settings_schema.php');
}

/** key => field definition, across all sections */
function settings_fields(): array
{
    static $f = null;
    if ($f === null) {
        $f = [];
        foreach (settings_schema() as $section) $f += $section['fields'];
    }
    return $f;
}

/** All stored settings, loaded once per request. */
function settings_all(bool $refresh = false): array
{
    static $all = null;
    if ($all === null || $refresh) {
        $all = [];
        $db = $GLOBALS['db'] ?? null;
        if ($db instanceof mysqli && ($rs = $db->query("SELECT `key`, `value` FROM settings"))) {
            while ($r = $rs->fetch_row()) $all[$r[0]] = (string)$r[1];
        }
    }
    return $all;
}

function setting(string $key, ?string $default = null): string
{
    $all = settings_all();
    if (array_key_exists($key, $all) && $all[$key] !== '') return $all[$key];
    if ($default !== null) return $default;
    return (string)(settings_fields()[$key]['default'] ?? '');
}

function setting_bool(string $key): bool
{
    return in_array(setting($key), ['1', 'true', 'yes', 'on'], true);
}

/** Validates against the schema and stores. Returns an error message or null. */
function setting_save(mysqli $db, string $key, string $value): ?string
{
    $fields = settings_fields();
    if (!isset($fields[$key])) return 'Unknown setting.';
    $f = $fields[$key];
    $value = trim($value);

    switch ($f['type']) {
        case 'switch':
            $value = in_array($value, ['1', 'true', 'on'], true) ? '1' : '0';
            break;
        case 'select':
            if (!array_key_exists($value, $f['options'])) return 'Choose one of the listed options.';
            break;
        case 'number':
            if (!is_numeric($value)) return 'Enter a number.';
            if (isset($f['min']) && (float)$value < $f['min']) return 'Must be at least ' . $f['min'] . '.';
            if (isset($f['max']) && (float)$value > $f['max']) return 'Must be at most ' . $f['max'] . '.';
            break;
        case 'theme':
            $themes = require __DIR__ . '/themes.php';
            if (!isset($themes[$value]) && !preg_match('/^custom_[a-z0-9_]{1,50}$/', $value)) return 'Unknown theme.';
            break;
        case 'password':
            if ($value === '') return null; // blank keeps what is stored
            break;
        default:
            $max = (int)($f['max'] ?? 500);
            if (mb_strlen($value) > $max) return "Keep this under $max characters.";
    }
    if ($key === 'business_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) return 'Enter a valid email address.';
    if ($key === 'currency_code') $value = strtoupper($value);

    $group = 'General';
    foreach (settings_schema() as $section) if (isset($section['fields'][$key])) $group = $section['label'];
    $type = $f['type'] === 'switch' ? 'bool' : 'text';
    $desc = (string)$f['label'];
    $st = $db->prepare("INSERT INTO settings (`key`, `value`, `group`, `type`, `description`) VALUES (?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `group` = VALUES(`group`), `type` = VALUES(`type`), updated_at = CURRENT_TIMESTAMP");
    if (!$st) return 'Could not save.';
    $st->bind_param('sssss', $key, $value, $group, $type, $desc);
    $ok = $st->execute();
    $st->close();
    settings_all(true);
    return $ok ? null : 'Could not save.';
}
