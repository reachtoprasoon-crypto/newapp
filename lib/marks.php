<?php
// Ports upsert-marks-flow.ts (incl. its termhic recompute side-effect),
// get-students-with-marks-flow.ts, get-marks-feeding-status-flow.ts,
// get-view-fed-marks-summary-flow.ts.

require_once __DIR__ . '/db.php';

function get_students_with_marks($mysqli, $sclass, $termschid) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.roll, s.sname, MAX(m.marks) as marks
         FROM students s
         LEFT JOIN marks m ON s.sid = m.sid AND m.termschid = ?
         WHERE s.sclass = ?
         GROUP BY s.sid, s.roll, s.sname
         ORDER BY s.roll",
        'is',
        [$termschid, $sclass]
    );
    foreach ($rows as &$row) {
        $row['sid'] = (int) $row['sid'];
        $row['roll'] = (int) $row['roll'];
        $row['marks'] = $row['marks'] === null ? null : (int) $row['marks'];
    }
    return $rows;
}

// $marks: array of ['sid'=>int, 'marks'=>int]. Also recomputes termhic (the
// highest grand-total-across-all-scheduled-assessments in the class/term/report)
// as a side effect, matching the source's combined upsert+recalculate flow.
function upsert_marks($mysqli, $termschid, $marks) {
    mysqli_begin_transaction($mysqli);
    try {
        db_execute($mysqli, "DELETE FROM marks WHERE termschid = ?", 'i', [$termschid]);

        foreach ($marks as $m) {
            db_execute($mysqli, "INSERT INTO marks (sid, termschid, marks) VALUES (?, ?, ?)", 'iii', [$m['sid'], $termschid, $m['marks']]);
        }

        $context = db_fetch_one($mysqli, "SELECT sclass, termid, report FROM termschedule WHERE termschid = ?", 'i', [$termschid]);
        if ($context === null) {
            mysqli_rollback($mysqli);
            return ['success' => false, 'error' => 'Could not find context for the assessment.'];
        }
        $sclass = $context['sclass'];
        $termid = (int) $context['termid'];
        $report = (int) $context['report'];

        $studentRows = db_fetch_all($mysqli, "SELECT sid FROM students WHERE sclass = ?", 's', [$sclass]);
        $studentIds = array_map(fn($r) => (int) $r['sid'], $studentRows);

        $scheduleRows = db_fetch_all(
            $mysqli,
            "SELECT termschid FROM termschedule WHERE sclass = ? AND termid = ? AND report = ?",
            'sii',
            [$sclass, $termid, $report]
        );
        $scheduleIds = array_map(fn($r) => (int) $r['termschid'], $scheduleRows);

        if (empty($studentIds) || empty($scheduleIds)) {
            mysqli_commit($mysqli);
            return ['success' => true];
        }

        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        $schedulePlaceholders = implode(',', array_fill(0, count($scheduleIds), '?'));
        $allMarks = db_fetch_all(
            $mysqli,
            "SELECT sid, marks FROM marks WHERE sid IN ($studentPlaceholders) AND termschid IN ($schedulePlaceholders)",
            str_repeat('i', count($studentIds)) . str_repeat('i', count($scheduleIds)),
            array_merge($studentIds, $scheduleIds)
        );

        $studentTotals = array_fill_keys($studentIds, 0);
        foreach ($allMarks as $mark) {
            $sid = (int) $mark['sid'];
            if (isset($studentTotals[$sid])) {
                $studentTotals[$sid] += (int) $mark['marks'];
            }
        }

        $highestTotal = empty($studentTotals) ? 0 : max(0, max($studentTotals));

        db_execute(
            $mysqli,
            "INSERT INTO termhic (sclass, termid, report, thic) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE thic = VALUES(thic)",
            'siii',
            [$sclass, $termid, $report, $highestTotal]
        );

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

function get_marks_feeding_status($mysqli, $termid, $report, $sclass) {
    $whereClauses = ['ts.termid = ?', 'ts.report = ?', 's.subtype != 0'];
    $types = 'ii';
    $params = [$termid, $report];

    if ($sclass && $sclass !== 'all') {
        $whereClauses[] = 'ts.sclass = ?';
        $types .= 's';
        $params[] = $sclass;
    }

    $rows = db_fetch_all(
        $mysqli,
        "SELECT
            ts.sclass,
            s.subname,
            e.examname,
            t.tname AS teacherName,
            (SELECT COUNT(*) FROM students WHERE sclass = ts.sclass) AS totalStudents,
            COUNT(DISTINCT m.sid) AS marksEntered
         FROM termschedule ts
         JOIN subjects s ON ts.subid = s.subid
         JOIN exams e ON ts.exid = e.exid
         LEFT JOIN subjectteacher st ON ts.sclass = st.sclass AND ts.subid = st.subid
         LEFT JOIN teachers t ON st.tid = t.tid
         LEFT JOIN marks m ON ts.termschid = m.termschid
         WHERE " . implode(' AND ', $whereClauses) . "
         GROUP BY ts.termschid, ts.sclass, s.subname, e.examname, t.tname
         ORDER BY ts.sclass, s.subname, e.examname",
        $types,
        $params
    );

    return array_map(function ($row) {
        return [
            'sclass' => $row['sclass'],
            'subname' => $row['subname'],
            'examname' => $row['examname'],
            'teacherName' => $row['teacherName'] ?: 'Unassigned',
            'totalStudents' => (int) $row['totalStudents'],
            'marksEntered' => (int) $row['marksEntered'],
        ];
    }, $rows);
}

function get_view_fed_marks_summary($mysqli, $sclass, $subid) {
    $studentList = db_fetch_all($mysqli, "SELECT sid, roll, sname FROM students WHERE sclass = ? ORDER BY roll", 's', [$sclass]);
    if (empty($studentList)) {
        return ['headers' => [], 'students' => []];
    }
    $studentIds = array_map(fn($r) => (int) $r['sid'], $studentList);

    $subject = db_fetch_one($mysqli, "SELECT subname, subtype FROM subjects WHERE subid = ?", 'i', [$subid]);
    $isGraded = $subject && ((int) $subject['subtype'] === 0 || $subject['subname'] === 'Moral Science' || $subject['subname'] === 'SUPW');

    $headers = [];
    $resultStudents = [];
    foreach ($studentList as $s) {
        $resultStudents[(int) $s['sid']] = [
            'sid' => (int) $s['sid'],
            'roll' => (int) $s['roll'],
            'sname' => $s['sname'],
            'values' => new stdClass(),
            'total' => null,
        ];
    }

    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));

    if ($isGraded) {
        $gradeRows = db_fetch_all(
            $mysqli,
            "SELECT g.sid, g.grade, t.termname, g.report
             FROM grades g
             JOIN terms t ON g.termid = t.termid
             WHERE g.sid IN ($studentPlaceholders) AND g.subid = ?
             ORDER BY g.termid, g.report",
            str_repeat('i', count($studentIds)) . 'i',
            [...$studentIds, $subid]
        );

        $seenLabels = [];
        foreach ($gradeRows as $r) {
            $label = $r['termname'] . ' (R' . $r['report'] . ')';
            if (!isset($seenLabels[$label])) {
                $seenLabels[$label] = true;
                $headers[] = ['key' => $label, 'label' => $label];
            }
        }

        foreach ($gradeRows as $r) {
            $sid = (int) $r['sid'];
            $label = $r['termname'] . ' (R' . $r['report'] . ')';
            if (isset($resultStudents[$sid])) {
                $resultStudents[$sid]['values']->$label = $r['grade'];
            }
        }
    } else {
        $schedules = db_fetch_all(
            $mysqli,
            "SELECT ts.termschid, t.termname, e.examshort, ts.maxm, ts.report
             FROM termschedule ts
             JOIN subjects s ON ts.subid = s.subid
             JOIN terms t ON ts.termid = t.termid
             JOIN exams e ON ts.exid = e.exid
             WHERE ts.sclass = ? AND ts.subid = ?
             ORDER BY ts.termid, ts.report, ts.exid",
            'si',
            [$sclass, $subid]
        );

        foreach ($schedules as $sch) {
            $key = 'sch_' . $sch['termschid'];
            $label = $sch['termname'] . ' (R' . $sch['report'] . ') ' . $sch['examshort'] . ' [' . $sch['maxm'] . ']';
            $headers[] = ['key' => $key, 'label' => $label];
        }

        if (!empty($schedules)) {
            $scheduleIds = array_map(fn($s) => (int) $s['termschid'], $schedules);
            $schedulePlaceholders = implode(',', array_fill(0, count($scheduleIds), '?'));
            $marksRows = db_fetch_all(
                $mysqli,
                "SELECT sid, termschid, marks FROM marks WHERE sid IN ($studentPlaceholders) AND termschid IN ($schedulePlaceholders)",
                str_repeat('i', count($studentIds)) . str_repeat('i', count($scheduleIds)),
                array_merge($studentIds, $scheduleIds)
            );

            $bySid = [];
            foreach ($marksRows as $m) {
                $bySid[(int) $m['sid']][] = $m;
            }

            foreach ($resultStudents as $sid => &$rs) {
                $rowSum = 0;
                $hasAnyMarks = false;
                foreach ($bySid[$sid] ?? [] as $m) {
                    $key = 'sch_' . $m['termschid'];
                    $rs['values']->$key = (int) $m['marks'];
                    $rowSum += (int) $m['marks'];
                    $hasAnyMarks = true;
                }
                $rs['total'] = $hasAnyMarks ? $rowSum : null;
            }
            unset($rs);
        }
    }

    return ['headers' => $headers, 'students' => array_values($resultStudents)];
}
