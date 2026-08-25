<?php
// Aptitude test marks + a combined logsheet (aptitude + Mathematics/Computer
// Applications term averages). Ports aptitude-management-flows.ts.
//
// sclass matching is intentionally fuzzy (exact / trimmed / space-stripped),
// mirroring the source's defensive handling of inconsistent sclass data —
// preserved as-is rather than "cleaned up" since it's covering for real data
// quality issues elsewhere, not a bug in this feature.

require_once __DIR__ . '/db.php';

function get_aptitude_marks($mysqli, $sclass) {
    $stripped = preg_replace('/\s+/', '', $sclass);
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.roll, s.schno, s.sname, a.marks
         FROM students s
         LEFT JOIN aptitude_marks a ON s.sid = a.sid
         WHERE s.sclass = ? OR TRIM(s.sclass) = ? OR REPLACE(s.sclass, ' ', '') = ?
         ORDER BY s.roll",
        'sss',
        [$sclass, trim($sclass), $stripped]
    );

    $seen = [];
    $result = [];
    foreach ($rows as $r) {
        $sid = (int) $r['sid'];
        if (isset($seen[$sid])) {
            continue;
        }
        $seen[$sid] = true;
        $result[] = [
            'sid' => $sid,
            'roll' => (int) $r['roll'],
            'schno' => (int) $r['schno'],
            'sname' => $r['sname'],
            'marks' => $r['marks'] === null ? null : (int) $r['marks'],
        ];
    }
    return $result;
}

// $marks: array of ['sid'=>int, 'marks'=>int]
function upsert_aptitude_marks($mysqli, $marks) {
    mysqli_begin_transaction($mysqli);
    try {
        $studentIds = array_map(fn($m) => $m['sid'], $marks);
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            db_execute($mysqli, "DELETE FROM aptitude_marks WHERE sid IN ($placeholders)", str_repeat('i', count($studentIds)), $studentIds);

            foreach ($marks as $m) {
                db_execute($mysqli, "INSERT INTO aptitude_marks (sid, marks) VALUES (?, ?)", 'ii', [$m['sid'], $m['marks']]);
            }
        }
        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function get_aptitude_logsheet_data($mysqli, $sclass) {
    $stripped = preg_replace('/\s+/', '', $sclass);
    $studentRows = db_fetch_all(
        $mysqli,
        "SELECT sid, roll, schno, sname FROM students
         WHERE sclass = ? OR TRIM(sclass) = ? OR REPLACE(sclass, ' ', '') = ?
         ORDER BY roll",
        'sss',
        [$sclass, trim($sclass), $stripped]
    );

    $seen = [];
    $studentList = [];
    foreach ($studentRows as $s) {
        $sid = (int) $s['sid'];
        if (isset($seen[$sid])) continue;
        $seen[$sid] = true;
        $studentList[] = ['sid' => $sid, 'roll' => (int) $s['roll'], 'schno' => (int) $s['schno'], 'sname' => $s['sname']];
    }
    if (empty($studentList)) {
        return [];
    }
    $studentIds = array_map(fn($s) => $s['sid'], $studentList);
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));

    $aptitudeRows = db_fetch_all($mysqli, "SELECT sid, marks FROM aptitude_marks WHERE sid IN ($studentPlaceholders)", str_repeat('i', count($studentIds)), $studentIds);
    $aptitudeMap = [];
    foreach ($aptitudeRows as $r) {
        $aptitudeMap[(int) $r['sid']] = (int) $r['marks'];
    }

    $subjectRows = db_fetch_all($mysqli, "SELECT subid, subname FROM subjects WHERE subname IN ('Mathematics', 'Maths', 'Computer Applications', 'Computer')");
    $mathsSubIds = [];
    $compSubIds = [];
    $targetSubIds = [];
    foreach ($subjectRows as $s) {
        $subid = (int) $s['subid'];
        $targetSubIds[] = $subid;
        $lower = strtolower($s['subname']);
        if (str_contains($lower, 'math')) $mathsSubIds[] = $subid;
        if (str_contains($lower, 'comp')) $compSubIds[] = $subid;
    }

    $studentAverages = [];
    if (!empty($targetSubIds)) {
        $subPlaceholders = implode(',', array_fill(0, count($targetSubIds), '?'));
        $marksRows = db_fetch_all(
            $mysqli,
            "SELECT m.sid, ts.subid, SUM(m.marks) as total_obt, SUM(ts.maxm) as total_max
             FROM marks m JOIN termschedule ts ON m.termschid = ts.termschid
             WHERE m.sid IN ($studentPlaceholders) AND ts.subid IN ($subPlaceholders)
             GROUP BY m.sid, ts.subid",
            str_repeat('i', count($studentIds)) . str_repeat('i', count($targetSubIds)),
            array_merge($studentIds, $targetSubIds)
        );

        foreach ($marksRows as $row) {
            $sid = (int) $row['sid'];
            $subid = (int) $row['subid'];
            if (!isset($studentAverages[$sid])) {
                $studentAverages[$sid] = ['math' => null, 'comp' => null];
            }
            $totalMax = (float) $row['total_max'];
            $perc = $totalMax > 0 ? (int) round(((float) $row['total_obt'] / $totalMax) * 100) : null;

            if (in_array($subid, $mathsSubIds, true)) $studentAverages[$sid]['math'] = $perc;
            if (in_array($subid, $compSubIds, true)) $studentAverages[$sid]['comp'] = $perc;
        }
    }

    return array_map(function ($s) use ($aptitudeMap, $studentAverages) {
        $apt = $aptitudeMap[$s['sid']] ?? null;
        $math = $studentAverages[$s['sid']]['math'] ?? null;
        $comp = $studentAverages[$s['sid']]['comp'] ?? null;
        $total = ($apt !== null || $math !== null || $comp !== null) ? (($apt ?: 0) + ($math ?: 0) + ($comp ?: 0)) : null;

        return [
            'roll' => $s['roll'],
            'schno' => $s['schno'],
            'sname' => $s['sname'],
            'aptitude' => $apt,
            'mathsAvg' => $math,
            'compAvg' => $comp,
            'total' => $total,
        ];
    }, $studentList);
}
