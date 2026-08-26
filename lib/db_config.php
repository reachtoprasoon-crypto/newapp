<?php
// Live-editing config.php's DB connection settings from the browser. Ports
// get-set-db-config-flow.ts, with real safety guards the source lacks:
//   1. Test-connects with the NEW credentials before writing anything — the
//      source writes blindly, with no verification the new settings even work.
//   2. Verifies the target actually looks like this app's database (has a
//      `teachers` table) before accepting it — the source accepts any
//      reachable database.
//   3. Backs up config.php (timestamped copy alongside it) before overwriting,
//      so a bad save is a one-file SFTP revert, not a lost file.
//   4. Never returns the current password to the browser (the source's
//      getDbConfigAction does — a real leak, not replicated here).
// config.php is required by every single page/endpoint in this app, so a
// bad write here is one of the highest-blast-radius mistakes possible.

require_once __DIR__ . '/activity_log.php';

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
    $configPath = __DIR__ . '/../config.php';
    $content = file_get_contents($configPath);
    if ($content === false) {
        return ['success' => false, 'error' => 'Could not read config.php.'];
    }

    $finalPassword = $password !== '' ? $password : DB_PASS;

    $test = test_db_connection($host, $user, $finalPassword, $database);
    if (!$test['success']) {
        return ['success' => false, 'error' => 'Could not connect with the new settings — config.php was NOT changed. ' . $test['error']];
    }

    $backupPath = $configPath . '.bak.' . date('YmdHis');
    if (!copy($configPath, $backupPath)) {
        return ['success' => false, 'error' => 'Could not create a backup of config.php — aborting for safety, nothing was changed.'];
    }

    $escape = fn ($v) => addslashes($v);
    $newContent = $content;
    $newContent = preg_replace("/define\('DB_HOST',\s*'[^']*'\);/", "define('DB_HOST', '" . $escape($host) . "');", $newContent, 1);
    $newContent = preg_replace("/define\('DB_USER',\s*'[^']*'\);/", "define('DB_USER', '" . $escape($user) . "');", $newContent, 1);
    $newContent = preg_replace("/define\('DB_PASS',\s*'[^']*'\);/", "define('DB_PASS', '" . $escape($finalPassword) . "');", $newContent, 1);
    $newContent = preg_replace("/define\('DB_NAME',\s*'[^']*'\);/", "define('DB_NAME', '" . $escape($database) . "');", $newContent, 1);

    if (file_put_contents($configPath, $newContent) === false) {
        return ['success' => false, 'error' => 'Backup was created but writing the new config.php failed — restore from ' . basename($backupPath) . ' if needed.'];
    }

    log_activity($mysqli, $actorName, 'Update Database Configuration', "Changed DB connection to host={$host}, database={$database}. Backup: " . basename($backupPath));
    return ['success' => true, 'backup' => basename($backupPath)];
}
