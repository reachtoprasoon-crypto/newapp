<?php
// Ports get-ht-wt-flow.ts, upsert-ht-wt-flow.ts.

require_once __DIR__ . '/db.php';

function get_ht_wt_for_class($mysqli, $sclass, $termid, $report) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, h.ht, h.wt
         FROM students s
         LEFT JOIN htwt h ON s.sid = h.sid AND h.termid = ? AND h.report = ?
         WHERE s.sclass = ?
         ORDER BY s.roll",
        'iis',
        [$termid, $report, $sclass]
    );
    foreach ($rows as &$row) {
        $row['sid'] = (int) $row['sid'];
        $row['ht'] = $row['ht'] === null ? null : (int) $row['ht'];
        $row['wt'] = $row['wt'] === null ? null : (int) $row['wt'];
    }
    return $rows;
}

// $htWtData: array of ['sid'=>int, 'ht'=>int, 'wt'=>int]
function upsert_ht_wt($mysqli, $sclass, $termid, $report, $htWtData) {
    mysqli_begin_transaction($mysqli);
    try {
        db_execute(
            $mysqli,
            "DELETE h FROM htwt h INNER JOIN students s ON h.sid = s.sid WHERE s.sclass = ? AND h.termid = ? AND h.report = ?",
            'sii',
            [$sclass, $termid, $report]
        );

        foreach ($htWtData as $record) {
            if ($record['ht'] <= 0 && $record['wt'] <= 0) {
                continue;
            }
            db_execute(
                $mysqli,
                "INSERT INTO htwt (sid, termid, report, ht, wt) VALUES (?, ?, ?, ?, ?)",
                'iiiii',
                [$record['sid'], $termid, $report, $record['ht'], $record['wt']]
            );
        }

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}
