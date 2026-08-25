<?php
// Ports lib/logger.ts::logActivity.

require_once __DIR__ . '/db.php';

function log_activity($mysqli, $actorName, $action, $details) {
    db_execute(
        $mysqli,
        "INSERT INTO activity_logs (actor_name, action, details) VALUES (?, ?, ?)",
        'sss',
        [$actorName, $action, $details]
    );
}

// Ports get-activity-log-flow.ts.
function get_activity_log($mysqli) {
    if (!db_table_exists($mysqli, 'activity_logs')) {
        return [];
    }
    $rows = db_fetch_all($mysqli, "SELECT id, timestamp, actor_name, action, details FROM activity_logs ORDER BY timestamp DESC");
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
    }
    return $rows;
}
