<?php
// Term-based attendance (distinct from the daily self-attendance kiosk in
// lib/daily_attendance.php). Ports get-attendance-flow.ts, upsert-attendance-flow.ts.

require_once __DIR__ . '/db.php';

function get_attendance_for_class($mysqli, $sclass, $termid, $report) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.roll, s.sname, a.attendance, a.totalattendance, a.comid
         FROM students s
         LEFT JOIN attendance a ON s.sid = a.sid AND a.termid = ? AND a.report = ?
         WHERE s.sclass = ?
         ORDER BY s.roll",
        'iis',
        [$termid, $report, $sclass]
    );
    foreach ($rows as &$row) {
        $row['sid'] = (int) $row['sid'];
        $row['roll'] = (int) $row['roll'];
        $row['attendance'] = $row['attendance'] === null ? null : (int) $row['attendance'];
        $row['totalattendance'] = $row['totalattendance'] === null ? null : (int) $row['totalattendance'];
        $row['comid'] = $row['comid'] === null ? null : (int) $row['comid'];
    }
    return $rows;
}

// $attendanceData: array of ['sid'=>int, 'attendance'=>int|null, 'totalattendance'=>int|null, 'comid'=>int|null]
function upsert_attendance($mysqli, $sclass, $termid, $report, $attendanceData) {
    mysqli_begin_transaction($mysqli);
    try {
        db_execute(
            $mysqli,
            "DELETE a FROM attendance a INNER JOIN students s ON a.sid = s.sid WHERE s.sclass = ? AND a.termid = ? AND a.report = ?",
            'sii',
            [$sclass, $termid, $report]
        );

        foreach ($attendanceData as $record) {
            if ($record['attendance'] === null && $record['comid'] === null) {
                continue;
            }
            db_execute(
                $mysqli,
                "INSERT INTO attendance (sid, termid, report, attendance, totalattendance, comid) VALUES (?, ?, ?, ?, ?, ?)",
                'iiiiii',
                [$record['sid'], $termid, $report, $record['attendance'], $record['totalattendance'], $record['comid']]
            );
        }

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}
