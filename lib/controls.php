<?php
// Feature-flag ("controls" table) helpers. Ports get-controls-flow.ts.

require_once __DIR__ . '/db.php';

// Full control list, self-healing the same way the source flow does:
// adds the cdata column if missing, seeds the two theme rows if absent.
function get_all_controls($mysqli) {
    try {
        mysqli_query($mysqli, "ALTER TABLE controls ADD COLUMN cdata MEDIUMTEXT DEFAULT NULL");
    } catch (Throwable $e) {
        // ignore — column already exists
    }

    $existing = db_fetch_all(
        $mysqli,
        "SELECT control FROM controls WHERE control IN ('Default Theme', 'Report Watermark')"
    );
    $existingNames = array_column($existing, 'control');

    if (!in_array('Default Theme', $existingNames, true)) {
        db_execute($mysqli, "INSERT INTO controls (control, cval, allowed, ctype) VALUES ('Default Theme', 0, 1, 'theme')");
    }
    if (!in_array('Report Watermark', $existingNames, true)) {
        db_execute($mysqli, "INSERT INTO controls (control, cval, allowed, ctype) VALUES ('Report Watermark', 0, 1, 'theme')");
    }

    $rows = db_fetch_all($mysqli, "SELECT conid, control, cval, allowed, ctype, cdata FROM controls ORDER BY ctype, conid");
    foreach ($rows as &$row) {
        $row['allowed'] = (bool) $row['allowed'];
    }
    return $rows;
}

// Single control row lookup by conid.
function get_control_by_conid($mysqli, $conid) {
    return db_fetch_one($mysqli, "SELECT conid, control, cval, allowed, ctype, cdata FROM controls WHERE conid = ?", 'i', [$conid]);
}

// Staff login gate: controls row with ctype='login' AND cval=10.
function is_staff_login_allowed($mysqli) {
    $row = db_fetch_one($mysqli, "SELECT allowed FROM controls WHERE ctype = 'login' AND cval = 10 LIMIT 1");
    return $row !== null && (bool) $row['allowed'];
}

// Student login gate: controls row with conid=17 (hardcoded id, matches source).
function is_student_login_allowed($mysqli) {
    $row = db_fetch_one($mysqli, "SELECT allowed FROM controls WHERE conid = 17");
    return $row !== null && (int) $row['allowed'] === 1;
}

// Password-reset gate: controls row named 'Password Reset'.
function is_password_reset_allowed($mysqli) {
    $row = db_fetch_one($mysqli, "SELECT allowed FROM controls WHERE control = 'Password Reset' LIMIT 1");
    return $row !== null && (bool) $row['allowed'];
}
