<?php
// Feature-flag ("controls" table) helpers. Ports get-controls-flow.ts.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';

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

// Controls admin screen: rows are never created/deleted here, only toggled.
// Excludes ctype='theme' (Default Theme / Report Watermark) — those carry an
// image blob in `cdata` and are consumed directly by the report-card export
// UI as a per-export upload, not surfaced as a feature-flag toggle here.
function get_toggleable_controls($mysqli) {
    $rows = get_all_controls($mysqli);
    return array_values(array_filter($rows, function ($r) {
        return strtolower((string) $r['ctype']) !== 'theme';
    }));
}

// Ports update-control-flow.ts: independent, optionally-combined updates by
// conid only (never touches `control`/`ctype` names). $fields may contain
// any of 'allowed' (bool), 'cval' (int), 'cdata' (string) — only the keys
// present are written.
function update_control($mysqli, $conid, $fields, $actorName) {
    $control = get_control_by_conid($mysqli, $conid);
    if ($control === null) {
        return ['success' => false, 'error' => 'Control not found.'];
    }

    $actionDetails = null;
    if (array_key_exists('allowed', $fields)) {
        db_execute($mysqli, "UPDATE controls SET allowed = ? WHERE conid = ?", 'ii', [$fields['allowed'] ? 1 : 0, $conid]);
        $actionDetails = "Set '{$control['control']}' to " . ($fields['allowed'] ? 'Enabled' : 'Disabled');
    }
    if (array_key_exists('cval', $fields)) {
        db_execute($mysqli, "UPDATE controls SET cval = ? WHERE conid = ?", 'ii', [$fields['cval'], $conid]);
        $actionDetails = "Set '{$control['control']}' value to {$fields['cval']}";
    }
    if (array_key_exists('cdata', $fields)) {
        db_execute($mysqli, "UPDATE controls SET cdata = ? WHERE conid = ?", 'si', [$fields['cdata'], $conid]);
        $actionDetails = "Updated content data for '{$control['control']}'";
    }

    if ($actionDetails !== null) {
        log_activity($mysqli, $actorName, 'Update Setting', $actionDetails);
    }
    return ['success' => true];
}
