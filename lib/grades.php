<?php
// Ports get-grades-for-subject-flow.ts, upsert-grades-flow.ts.

require_once __DIR__ . '/db.php';

function get_grades_for_subject($mysqli, $sclass, $subid, $termid, $report) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.roll, s.sname, g.grade
         FROM students s
         LEFT JOIN grades g ON s.sid = g.sid AND g.subid = ? AND g.termid = ? AND g.report = ?
         WHERE s.sclass = ?
         ORDER BY s.roll",
        'iiis',
        [$subid, $termid, $report, $sclass]
    );
    foreach ($rows as &$row) {
        $row['sid'] = (int) $row['sid'];
        $row['roll'] = (int) $row['roll'];
    }
    return $rows;
}

// $grades: array of ['sid'=>int, 'grade'=>string]
function upsert_grades($mysqli, $sclass, $subid, $termid, $report, $grades) {
    mysqli_begin_transaction($mysqli);
    try {
        db_execute(
            $mysqli,
            "DELETE g FROM grades g INNER JOIN students s ON g.sid = s.sid WHERE s.sclass = ? AND g.subid = ? AND g.termid = ? AND g.report = ?",
            'siii',
            [$sclass, $subid, $termid, $report]
        );

        foreach ($grades as $g) {
            if (empty($g['grade']) || $g['grade'] === 'N/A') {
                continue;
            }
            db_execute(
                $mysqli,
                "INSERT INTO grades (sid, subid, termid, report, grade) VALUES (?, ?, ?, ?, ?)",
                'iiiis',
                [$g['sid'], $subid, $termid, $report, $g['grade']]
            );
        }

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}
