<?php
// includes/updater.php
declare(strict_types=1);

/**
 * Pulls application updates from the git remote this install was cloned from.
 * Commands run through proc_open with an argument list: no shell, no user input.
 */

function updater_root(): string
{
    return dirname(__DIR__);
}

/** @return array{ok:bool, code:int, out:string} */
function updater_git(array $args, int $timeout = 120): array
{
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'code' => -1, 'out' => 'proc_open is disabled on this server.'];
    }
    $cmd = array_merge(['git', '-C', updater_root()], $args);
    $env = ['GIT_TERMINAL_PROMPT' => '0', 'LC_ALL' => 'C', 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin', 'HOME' => getenv('HOME') ?: sys_get_temp_dir()];
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, updater_root(), $env);
    if (!is_resource($proc)) {
        return ['ok' => false, 'code' => -1, 'out' => 'git could not be started on this server.'];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $start = time();
    do {
        $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $st = proc_get_status($proc);
        if (!$st['running']) break;
        if (time() - $start > $timeout) {
            proc_terminate($proc);
            $out .= "\n(timed out)";
            break;
        }
        usleep(100000);
    } while (true);
    $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = $st['running'] ? -1 : (int)$st['exitcode'];
    proc_close($proc);
    return ['ok' => $code === 0, 'code' => $code, 'out' => trim($out)];
}

/** Why updates can't run here, or '' when they can. */
function updater_unavailable_reason(): string
{
    if (!function_exists('proc_open')) {
        return 'This server does not allow PHP to run programs (proc_open is disabled), so updates must be uploaded manually.';
    }
    if (!is_dir(updater_root() . '/.git')) {
        return 'This copy was not installed with git. Install it with "git clone" to enable one-click updates.';
    }
    $v = updater_git(['--version'], 10);
    if (!$v['ok']) {
        return 'git is not installed on this server.';
    }
    return '';
}

function updater_branch(): string
{
    $r = updater_git(['rev-parse', '--abbrev-ref', 'HEAD'], 10);
    return $r['ok'] ? $r['out'] : '';
}

function updater_head(): string
{
    $r = updater_git(['rev-parse', '--short', 'HEAD'], 10);
    return $r['ok'] ? $r['out'] : '';
}

/** Fetches the remote and reports what a pull would bring in. */
function updater_check(): array
{
    $why = updater_unavailable_reason();
    if ($why !== '') return ['success' => false, 'message' => $why];

    $branch = updater_branch();
    if ($branch === '' || $branch === 'HEAD') {
        return ['success' => false, 'message' => 'This install is not on a branch, so it cannot be updated automatically.'];
    }
    $fetch = updater_git(['fetch', '--quiet', 'origin', $branch]);
    if (!$fetch['ok']) {
        return ['success' => false, 'message' => 'Could not reach the update server.', 'detail' => $fetch['out']];
    }
    $count = updater_git(['rev-list', '--count', 'HEAD..origin/' . $branch], 20);
    $behind = $count['ok'] ? (int)$count['out'] : 0;
    $log = updater_git(['log', '--no-merges', '--pretty=format:%h %ad %s', '--date=short', '-n', '30', 'HEAD..origin/' . $branch], 20);
    $dirty = updater_git(['status', '--porcelain', '--untracked-files=no'], 20);

    return [
        'success' => true,
        'update_available' => $behind > 0,
        'branch' => $branch,
        'current' => updater_head(),
        'commits_behind' => $behind,
        'changes' => ($log['ok'] && $log['out'] !== '') ? explode("\n", $log['out']) : [],
        'local_changes' => ($dirty['ok'] && $dirty['out'] !== '') ? explode("\n", $dirty['out']) : [],
        'message' => $behind > 0 ? "$behind update(s) available." : 'You are up to date.',
    ];
}

/** Fast-forwards to the remote branch, then runs pending database migrations. */
function updater_apply(mysqli $db): array
{
    $why = updater_unavailable_reason();
    if ($why !== '') return ['success' => false, 'message' => $why];

    $branch = updater_branch();
    $from = updater_head();

    // Fast-forward only: local commits or conflicting edits stop the update instead of being overwritten.
    $pull = updater_git(['pull', '--ff-only', '--quiet', 'origin', $branch], 300);
    if (!$pull['ok']) {
        updater_record($db, $from, $from, 'failed', $pull['out']);
        return [
            'success' => false,
            'message' => 'Update stopped: files on this server were changed by hand and conflict with the update. Nothing was changed.',
            'detail' => $pull['out'],
        ];
    }
    $to = updater_head();
    if (function_exists('opcache_reset')) @opcache_reset();

    require_once __DIR__ . '/migrations.php';
    try {
        $migrated = migrations_run($db);
    } catch (Throwable $e) {
        updater_record($db, $from, $to, 'failed', 'Code updated, database step failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Code was updated but a database step failed. Open Updates again to retry.', 'detail' => $e->getMessage(), 'from' => $from, 'to' => $to];
    }

    updater_record($db, $from, $to, 'success', "Branch $branch. Database steps: " . ($migrated ? implode(', ', $migrated) : 'none'));
    return ['success' => true, 'message' => $from === $to ? 'Already up to date.' : "Updated $from to $to.", 'from' => $from, 'to' => $to, 'migrations' => $migrated];
}

function updater_record(mysqli $db, string $from, string $to, string $status, string $notes): void
{
    $uid = (int)($_SESSION['user']['id'] ?? 0);
    $st = $db->prepare("INSERT INTO update_history (version_from, version_to, status, notes, applied_by) VALUES (?,?,?,?,?)");
    if ($st) {
        $st->bind_param('ssssi', $from, $to, $status, $notes, $uid);
        $st->execute();
        $st->close();
    }
    @file_put_contents(updater_root() . '/storage/logs/update.log', date('c') . " [$status] $from -> $to $notes\n", FILE_APPEND);
}
