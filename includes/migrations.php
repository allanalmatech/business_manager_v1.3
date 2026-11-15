<?php
// includes/migrations.php
declare(strict_types=1);

/**
 * Tiny migration runner. Each file in /migrations is named NNN_description.php
 * and returns function (mysqli $db): void. Applied names are recorded in
 * schema_migrations, so every migration runs exactly once, in order.
 * Schema changes belong here, never inside request handlers.
 */

function mig_column_exists(mysqli $db, string $table, string $column): bool
{
    $st = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

function mig_table_exists(mysqli $db, string $table): bool
{
    $st = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $st->bind_param('s', $table);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

function mig_exec(mysqli $db, string $sql): void
{
    if (!$db->query($sql)) {
        throw new RuntimeException($db->error);
    }
}

function migrations_dir(): string
{
    return dirname(__DIR__) . '/migrations';
}

function migrations_all(): array
{
    $files = glob(migrations_dir() . '/[0-9][0-9][0-9]_*.php') ?: [];
    sort($files, SORT_STRING);
    return array_map(static function ($f) { return basename($f, '.php'); }, $files);
}

function migrations_applied(mysqli $db): array
{
    mig_exec($db, "CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $out = [];
    $rs = $db->query("SELECT name FROM schema_migrations");
    while ($rs && ($r = $rs->fetch_row())) $out[] = $r[0];
    return $out;
}

function migrations_pending(mysqli $db): array
{
    return array_values(array_diff(migrations_all(), migrations_applied($db)));
}

/** Runs every pending migration; returns the names applied. Stops at the first failure. */
function migrations_run(mysqli $db): array
{
    $done = [];
    foreach (migrations_pending($db) as $name) {
        $fn = require migrations_dir() . '/' . $name . '.php';
        if (!is_callable($fn)) {
            throw new RuntimeException("Migration $name does not return a function.");
        }
        try {
            $fn($db);
        } catch (Throwable $e) {
            throw new RuntimeException("Migration $name failed: " . $e->getMessage(), 0, $e);
        }
        $st = $db->prepare("INSERT INTO schema_migrations (name) VALUES (?)");
        $st->bind_param('s', $name);
        $st->execute();
        $st->close();
        $done[] = $name;
    }
    return $done;
}
