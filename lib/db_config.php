<?php
// Live-editing the app's DB connection settings (host/user/password/database)
// from the browser — writes only to .env, never to config.php itself. Ports
// get-set-db-config-flow.ts, with real safety guards the source lacks:
//   1. Test-connects with the NEW credentials before writing anything — the
//      source writes blindly, with no verification the new settings even work.
//   2. Verifies the target actually looks like this app's database (has a
//      `teachers` table) before accepting it — the source accepts any
//      reachable database.
//   3. Backs up .env (timestamped copy alongside it) before overwriting, so
//      a bad save is a one-file SFTP revert, not a lost file.
//   4. Never returns the current password to the browser (the source's
//      getDbConfigAction does — a real leak, not replicated here).
// config.php reads .env on every request, so a bad write here still takes
// the whole app down until fixed — this only moves *what* gets edited
// (plain connection values, not PHP source), not the blast radius.

require_once __DIR__ . '/activity_log.php';

function env_file_path() {
    return __DIR__ . '/../.env';
}

// Never includes the password.
function get_current_db_config() {
    return ['host' => DB_HOST, 'user' => DB_USER, 'database' => DB_NAME];
}

function test_db_connection($host, $user, $password, $database) {
    $conn = @mysqli_connect($host, $user, $password, $database);
    if (!$conn) {
        return ['success' => false, 'error' => mysqli_connect_error()];
    }
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'teachers'");
    $looksValid = $result && mysqli_num_rows($result) > 0;
    mysqli_close($conn);
    if (!$looksValid) {
        return ['success' => false, 'error' => "Connected, but this database has no 'teachers' table — doesn't look like this app's database."];
    }
    return ['success' => true];
}

// $password: pass '' to keep the current DB_PASS unchanged.
function update_db_config($mysqli, $host, $user, $password, $database, $actorName) {
    $envPath = env_file_path();
    $finalPassword = $password !== '' ? $password : DB_PASS;

    $test = test_db_connection($host, $user, $finalPassword, $database);
    if (!$test['success']) {
        return ['success' => false, 'error' => 'Could not connect with the new settings — .env was NOT changed. ' . $test['error']];
    }

    if (is_file($envPath)) {
        $backupPath = $envPath . '.bak.' . date('YmdHis');
        if (!copy($envPath, $backupPath)) {
            return ['success' => false, 'error' => 'Could not back up .env — aborting for safety, nothing was changed.'];
        }
    } else {
        $backupPath = null;
    }

    // Strip newlines so a value can't inject extra lines/keys into the file.
    $clean = fn ($v) => str_replace(["\r", "\n"], '', $v);
    $newContent = "DB_HOST={$clean($host)}\nDB_USER={$clean($user)}\nDB_PASS={$clean($finalPassword)}\nDB_NAME={$clean($database)}\n";
    if (file_put_contents($envPath, $newContent) === false) {
        $restoreNote = $backupPath ? ' — restore from ' . basename($backupPath) . ' if needed' : '';
        return ['success' => false, 'error' => 'Writing .env failed' . $restoreNote . '.'];
    }

    log_activity($mysqli, $actorName, 'Update Database Configuration', "Changed DB connection to host={$host}, database={$database}." . ($backupPath ? ' Backup: ' . basename($backupPath) : ''));
    return ['success' => true, 'backup' => $backupPath ? basename($backupPath) : null];
}
