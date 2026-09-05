<?php
// Final (end-of-year) results: combined final report card, HIC (highest-in-
// class), promotion status, and the final roster with rank computation.
// Ports get-final-report-card-data-flow.ts, get-hic-flow.ts, set-hic-flow.ts,
// get-promotion-data-flow.ts, upsert-promotion-flow.ts, get-final-roster-data-flow.ts.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dates.php';
require_once __DIR__ . '/activity_log.php';

// --- Final report card (combines all terms + persisted rank/HIC/promotion) ---

function get_final_report_card_data($mysqli, $sclass, $termid, $report) {
    $students = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.schno, s.roll, s.sname, s.pname, s.mname, s.dob, s.sclass, s.branch, s.hid, h.house, s.ht, s.wt, s.photo, s.phone
         FROM students s LEFT JOIN house h ON s.hid = h.hid
         WHERE s.sclass = ? ORDER BY s.roll",
        's',
        [$sclass]
    );
    if (empty($students)) {
        return ['students' => [], 'schedule' => [], 'orderedSubjects' => [], 'hics' => new stdClass(), 'grandThic' => 0, 'reopenText' => '________________'];
    }
    $studentIds = array_map(fn($s) => (int) $s['sid'], $students);
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $studentTypes = str_repeat('i', count($studentIds));

    $orderedSubjects = db_fetch_all(
        $mysqli,
        "SELECT s.subid, s.subname, s.subshort
         FROM subjects s JOIN subschedule ss ON s.subid = ss.subid
         WHERE ss.sclass = ? AND s.subtype = 1 ORDER BY ss.schorder",
        's',
        [$sclass]
    );

    $schedule = db_fetch_all(
        $mysqli,
        "SELECT ts.termschid, ts.subid, s.subname, s.subshort, ts.termid, t.termname, ts.exid, e.examshort, ts.maxm, ts.report
         FROM termschedule ts
         JOIN subjects s ON ts.subid = s.subid
         JOIN terms t ON ts.termid = t.termid
         JOIN exams e ON ts.exid = e.exid
         WHERE ts.sclass = ? AND s.subtype = 1
         ORDER BY ts.termid, ts.exid",
        's',
        [$sclass]
    );

    $marks = db_fetch_all($mysqli, "SELECT sid, termschid, marks FROM marks WHERE sid IN ($studentPlaceholders)", $studentTypes, $studentIds);

    $grades = db_fetch_all(
        $mysqli,
        "SELECT g.sid, g.subid, s.subname, g.grade FROM grades g JOIN subjects s ON g.subid = s.subid
         WHERE g.sid IN ($studentPlaceholders) AND g.termid = ? AND g.report = ?",
        $studentTypes . 'ii',
        [...$studentIds, $termid, $report]
    );

    $attend = db_fetch_all(
        $mysqli,
        "SELECT sid, attendance, totalattendance, comid FROM attendance WHERE sid IN ($studentPlaceholders) AND termid = ? AND report = ?",
        $studentTypes . 'ii',
        [...$studentIds, $termid, $report]
    );

    $hasPromotionTable = db_table_exists($mysqli, 'promotion');
    if ($hasPromotionTable) {
        $totals = db_fetch_all(
            $mysqli,
            "SELECT s.sid, ft.total_marks, ft.percentage, ft.rank, p.promotion as status
             FROM students s
             LEFT JOIN finaltotal ft ON s.sid = ft.sid
             LEFT JOIN promotion p ON s.sid = p.sid
             WHERE s.sid IN ($studentPlaceholders)",
            $studentTypes,
            $studentIds
        );
    } else {
        $totals = db_fetch_all(
            $mysqli,
            "SELECT s.sid, ft.total_marks, ft.percentage, ft.rank, NULL as status
             FROM students s LEFT JOIN finaltotal ft ON s.sid = ft.sid
             WHERE s.sid IN ($studentPlaceholders)",
            $studentTypes,
            $studentIds
        );
    }

    $hicRows = db_fetch_all($mysqli, "SELECT subid, hic FROM finalhic WHERE sclass = ?", 's', [$sclass]);
    $hics = new stdClass();
    foreach ($hicRows as $r) {
        $key = (string) (int) $r['subid'];
        $hics->$key = (int) $r['hic'];
    }

    $thicRow = db_fetch_one($mysqli, "SELECT thic FROM finalhictotal WHERE sclass = ?", 's', [$sclass]);
    $grandThic = $thicRow ? (float) $thicRow['thic'] : 0;

    // Self-heal: the source creates this table on the fly if missing (it's a global singleton, one row).
    if (!db_table_exists($mysqli, 'reopen')) {
        mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS reopen (id INT PRIMARY KEY, reopen VARCHAR(255))");
    }
    $reopenRow = db_fetch_one($mysqli, "SELECT reopen FROM reopen LIMIT 1");
    $reopenText = ($reopenRow && $reopenRow['reopen']) ? $reopenRow['reopen'] : '________________';

    $enriched = array_map(function ($s) use ($marks, $grades, $attend, $totals) {
        $sid = (int) $s['sid'];
        $s['sid'] = $sid;
        // The source flow returns dob raw/unformatted (unlike every sibling
        // student-fetching flow); normalizing it here avoids a mixed-format
        // DOB on the printed final report card.
        $s['dob'] = normalize_dob_display($s['dob']);
        $s['marks'] = array_values(array_filter($marks, fn($m) => (int) $m['sid'] === $sid));
        $s['snapshot'] = [
            'grades' => array_values(array_filter($grades, fn($g) => (int) $g['sid'] === $sid)),
            'attendance' => current(array_filter($attend, fn($a) => (int) $a['sid'] === $sid)) ?: null,
            'total' => current(array_filter($totals, fn($t) => (int) $t['sid'] === $sid)) ?: null,
        ];
        return $s;
    }, $students);

    return [
        'students' => $enriched,
        'schedule' => $schedule,
        'orderedSubjects' => $orderedSubjects,
        'hics' => $hics,
        'grandThic' => $grandThic,
        'reopenText' => $reopenText,
    ];
}

// --- HIC (highest-in-class) for one term/report ---

function get_hic_data($mysqli, $sclass, $termid, $report) {
    $subjectHicRows = db_fetch_all($mysqli, "SELECT subid, hic FROM hic WHERE sclass = ? AND termid = ? AND report = ?", 'sii', [$sclass, $termid, $report]);
    $subjectHics = new stdClass();
    foreach ($subjectHicRows as $r) {
        $key = (string) (int) $r['subid'];
        $subjectHics->$key = (int) $r['hic'];
    }

    $termHicRow = db_fetch_one($mysqli, "SELECT thic FROM termhic WHERE sclass = ? AND termid = ? AND report = ?", 'sii', [$sclass, $termid, $report]);
    $termHic = $termHicRow ? (int) $termHicRow['thic'] : 0;

    return ['subjectHics' => $subjectHics, 'termHic' => $termHic];
}

// $students: array of ['grandTotal'=>int, 'marks'=>[['subid'=>int,'marksObtained'=>int|null], ...]]
function set_hic_data($mysqli, $sclass, $termid, $report, $students) {
    $highestTermTotal = 0;
    $subjectHighestMarks = [];

    foreach ($students as $student) {
        $subjectTotals = [];
        foreach ($student['marks'] as $mark) {
            if ($mark['marksObtained'] !== null) {
                $subjectTotals[$mark['subid']] = ($subjectTotals[$mark['subid']] ?? 0) + $mark['marksObtained'];
            }
        }
        foreach ($subjectTotals as $subid => $total) {
            if (!isset($subjectHighestMarks[$subid]) || $total > $subjectHighestMarks[$subid]) {
                $subjectHighestMarks[$subid] = $total;
            }
        }
        if ($student['grandTotal'] > $highestTermTotal) {
            $highestTermTotal = $student['grandTotal'];
        }
    }

    mysqli_begin_transaction($mysqli);
    try {
        db_execute($mysqli, "DELETE FROM hic WHERE sclass = ? AND termid = ? AND report = ?", 'sii', [$sclass, $termid, $report]);
        db_execute($mysqli, "DELETE FROM termhic WHERE sclass = ? AND termid = ? AND report = ?", 'sii', [$sclass, $termid, $report]);

        foreach ($subjectHighestMarks as $subid => $hic) {
            db_execute($mysqli, "INSERT INTO hic (sclass, subid, termid, report, hic) VALUES (?, ?, ?, ?, ?)", 'siiii', [$sclass, $subid, $termid, $report, $hic]);
        }

        db_execute($mysqli, "INSERT INTO termhic (sclass, termid, report, thic) VALUES (?, ?, ?, ?)", 'siii', [$sclass, $termid, $report, $highestTermTotal]);

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

// --- Promotion status ---

function get_promotion_data($mysqli, $sclass) {
    if (!db_table_exists($mysqli, 'promotion')) {
        $rows = db_fetch_all($mysqli, "SELECT sid, roll, sname, schno FROM students WHERE sclass = ? ORDER BY roll", 's', [$sclass]);
        return array_map(function ($r) {
            $r['status'] = null;
            return $r;
        }, $rows);
    }
    return db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.roll, s.sname, s.schno, p.promotion as status
         FROM students s LEFT JOIN promotion p ON s.sid = p.sid
         WHERE s.sclass = ? ORDER BY s.roll",
        's',
        [$sclass]
    );
}

// $promotions: array of ['sid'=>int, 'status'=>string]
function upsert_promotion($mysqli, $sclass, $promotions, $actorId, $actorName) {
    $valid = array_values(array_filter($promotions, fn($p) => !empty($p['status'])));
    $studentIds = array_map(fn($p) => (int) $p['sid'], $valid);

    mysqli_begin_transaction($mysqli);
    try {
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            db_execute($mysqli, "DELETE FROM promotion WHERE sid IN ($placeholders)", str_repeat('i', count($studentIds)), $studentIds);

            foreach ($valid as $p) {
                db_execute($mysqli, "INSERT INTO promotion (sid, promotion) VALUES (?, ?)", 'is', [(int) $p['sid'], $p['status']]);
            }
        }
        mysqli_commit($mysqli);

        log_activity(
            $mysqli,
            $actorName,
            'Update Promotion Records',
            "Updated official promotion status for " . count($valid) . " students in class $sclass using Delete/Insert pattern."
        );

        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// --- Final roster (cross-term averages, rank computation, HIC persistence) ---
// NOTE: this "get" also WRITES — it recomputes and overwrites finalhic,
// finalhictotal, finaltotal for the class as a side effect, exactly like the source.

function get_final_roster_data($mysqli, $sclass) {
    $students = db_fetch_all($mysqli, "SELECT sid, roll, sname, schno FROM students WHERE sclass = ? ORDER BY roll", 's', [$sclass]);
    if (empty($students)) {
        return ['header' => [], 'studentData' => [], 'gradeSubjects' => []];
    }
    $studentIds = array_map(fn($s) => (int) $s['sid'], $students);
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $studentTypes = str_repeat('i', count($studentIds));

    $marksRows = db_fetch_all(
        $mysqli,
        "SELECT m.sid, ts.subid, ts.termid, SUM(m.marks) as term_total, SUM(ts.maxm) as term_max
         FROM marks m JOIN termschedule ts ON m.termschid = ts.termschid
         WHERE m.sid IN ($studentPlaceholders) AND ts.sclass = ?
         GROUP BY m.sid, ts.subid, ts.termid",
        $studentTypes . 's',
        [...$studentIds, $sclass]
    );
    $marks = array_map(function ($r) {
        return ['sid' => (int) $r['sid'], 'subid' => (int) $r['subid'], 'termid' => (int) $r['termid'], 'term_total' => (float) $r['term_total'], 'term_max' => (float) $r['term_max']];
    }, $marksRows);

    $activeTermIds = array_values(array_unique(array_map(fn($m) => $m['termid'], $marks)));
    if (empty($activeTermIds)) {
        return ['header' => [], 'studentData' => [], 'gradeSubjects' => []];
    }
    $termPlaceholders = implode(',', array_fill(0, count($activeTermIds), '?'));
    $termTypes = str_repeat('i', count($activeTermIds));

    $terms = db_fetch_all($mysqli, "SELECT termid, termname FROM terms WHERE termid IN ($termPlaceholders) ORDER BY termid", $termTypes, $activeTermIds);
    foreach ($terms as &$t) {
        $t['termid'] = (int) $t['termid'];
    }
    unset($t);

    $subjects = db_fetch_all(
        $mysqli,
        "SELECT s.subid, s.subname, s.subshort FROM subjects s JOIN subschedule ss ON s.subid = ss.subid
         WHERE ss.sclass = ? AND s.subtype = 1 ORDER BY ss.schorder",
        's',
        [$sclass]
    );
    foreach ($subjects as &$sub) {
        $sub['subid'] = (int) $sub['subid'];
    }
    unset($sub);

    $scheduleMaxRows = db_fetch_all(
        $mysqli,
        "SELECT subid, termid, SUM(maxm) as term_max FROM termschedule WHERE sclass = ? AND termid IN ($termPlaceholders) GROUP BY subid, termid",
        's' . $termTypes,
        [$sclass, ...$activeTermIds]
    );
    $scheduleMax = array_map(fn($r) => ['subid' => (int) $r['subid'], 'termid' => (int) $r['termid'], 'term_max' => (float) $r['term_max']], $scheduleMaxRows);

    $lastScheduleRow = db_fetch_one(
        $mysqli,
        "SELECT termid, report FROM termschedule WHERE sclass = ? AND termid IN ($termPlaceholders) ORDER BY termid DESC, report DESC LIMIT 1",
        's' . $termTypes,
        [$sclass, ...$activeTermIds]
    );
    $lastContext = $lastScheduleRow
        ? ['termid' => (int) $lastScheduleRow['termid'], 'report' => (int) $lastScheduleRow['report']]
        : ['termid' => end($activeTermIds), 'report' => 1];

    // Build header
    $header = [];
    foreach ($subjects as $sub) {
        $subHeaders = [];
        foreach ($terms as $idx => $term) {
            $maxRec = current(array_filter($scheduleMax, fn($sm) => $sm['subid'] === $sub['subid'] && $sm['termid'] === $term['termid']));
            $tMax = $maxRec ? $maxRec['term_max'] : 0;
            $subHeaders[] = ['label' => 'T' . ($idx + 1), 'key' => 'sub_' . $sub['subid'] . '_term_' . $term['termid'], 'maxm' => $tMax];
        }
        if (count($terms) > 1) {
            $subHeaders[] = ['label' => 'Avg', 'key' => 'sub_' . $sub['subid'] . '_avg', 'maxm' => 100];
        }
        $header[] = ['label' => $sub['subshort'] ?: $sub['subname'], 'subid' => $sub['subid'], 'subHeaders' => $subHeaders];
    }

    $gradeSubjects = db_fetch_all(
        $mysqli,
        "SELECT s.subid, s.subname, s.subshort, s.subtype FROM subjects s JOIN subschedule ss ON s.subid = ss.subid
         WHERE ss.sclass = ? AND s.subtype = 0 ORDER BY ss.schorder",
        's',
        [$sclass]
    );
    foreach ($gradeSubjects as &$gs) {
        $gs['subid'] = (int) $gs['subid'];
    }
    unset($gs);

    $gradesRows = db_fetch_all(
        $mysqli,
        "SELECT sid, subid, grade FROM grades WHERE sid IN ($studentPlaceholders) AND termid = ? AND report = ?",
        $studentTypes . 'ii',
        [...$studentIds, $lastContext['termid'], $lastContext['report']]
    );

    $attendanceRows = db_fetch_all(
        $mysqli,
        "SELECT sid, attendance, totalattendance, comid FROM attendance WHERE sid IN ($studentPlaceholders) AND termid = ? AND report = ?",
        $studentTypes . 'ii',
        [...$studentIds, $lastContext['termid'], $lastContext['report']]
    );

    $subjectHics = [];
    $classGrandHic = 0;

    $studentData = array_map(function ($student) use ($subjects, $terms, $marks, $scheduleMax, $gradeSubjects, $gradesRows, $attendanceRows, &$subjectHics, &$classGrandHic) {
        $sid = (int) $student['sid'];
        $sData = ['sid' => $sid, 'roll' => (int) $student['roll'], 'sname' => $student['sname'], 'schno' => (int) $student['schno'], 'marks' => [], 'isEligibleForRank' => true];

        $studentSumOfPercentages = 0;
        $studentSumOfPossiblePercentage = 0;
        $termObtainedTotals = array_fill_keys(array_map(fn($t) => $t['termid'], $terms), 0);
        $termMaxTotals = array_fill_keys(array_map(fn($t) => $t['termid'], $terms), 0);

        foreach ($subjects as $sub) {
            $subTotalMarksObtained = 0;
            $subTotalMaxPossible = 0;
            $studentAppearedTerms = 0;

            foreach ($terms as $term) {
                $markRecord = current(array_filter($marks, fn($m) => $m['sid'] === $sid && $m['subid'] === $sub['subid'] && $m['termid'] === $term['termid']));
                $maxRecord = current(array_filter($scheduleMax, fn($sm) => $sm['subid'] === $sub['subid'] && $sm['termid'] === $term['termid']));
                $key = 'sub_' . $sub['subid'] . '_term_' . $term['termid'];

                if ($markRecord) {
                    $marksVal = $markRecord['term_total'];
                    $sData[$key] = $marksVal;
                    $sData['marks'][] = ['subid' => $sub['subid'], 'marksObtained' => $marksVal];
                    $subTotalMarksObtained += $marksVal;
                    $studentAppearedTerms++;
                    if ($maxRecord) {
                        $subTotalMaxPossible += $maxRecord['term_max'];
                        $termMaxTotals[$term['termid']] += $maxRecord['term_max'];
                    }
                    $termObtainedTotals[$term['termid']] += $marksVal;
                } else {
                    $sData[$key] = null;
                }
            }

            if ($studentAppearedTerms > 0 && $subTotalMaxPossible > 0) {
                $subAvgPerc = (int) round(($subTotalMarksObtained / $subTotalMaxPossible) * 100);
                $sData['sub_' . $sub['subid'] . '_avg'] = $subAvgPerc;
                $studentSumOfPercentages += $subAvgPerc;
                $studentSumOfPossiblePercentage += 100;
                $subjectHics[$sub['subid']] = max($subjectHics[$sub['subid']] ?? 0, $subAvgPerc);
                if ($subAvgPerc < 40) {
                    $sData['isEligibleForRank'] = false;
                }
            } else {
                $sData['sub_' . $sub['subid'] . '_avg'] = null;
            }
        }

        foreach ($terms as $term) {
            $obt = $termObtainedTotals[$term['termid']];
            $max = $termMaxTotals[$term['termid']];
            if ($max > 0 && $obt < ($max * 0.4)) {
                $sData['isEligibleForRank'] = false;
            }
        }

        $sData['grandTotal'] = $studentSumOfPercentages;
        $sData['percentage'] = $studentSumOfPossiblePercentage > 0 ? round(($studentSumOfPercentages / $studentSumOfPossiblePercentage) * 100, 2) : 0;
        $classGrandHic = max($classGrandHic, $sData['grandTotal']);

        if ($sData['percentage'] < 40) {
            $sData['isEligibleForRank'] = false;
        }

        foreach ($gradeSubjects as $gs) {
            $grade = current(array_filter($gradesRows, fn($g) => (int) $g['sid'] === $sid && (int) $g['subid'] === $gs['subid']));
            $sData['grade_' . $gs['subid']] = $grade ? $grade['grade'] : 'N/A';
        }

        $attend = current(array_filter($attendanceRows, fn($a) => (int) $a['sid'] === $sid));
        $sData['attendance'] = $attend ? ($attend['attendance'] . '/' . $attend['totalattendance']) : 'N/A';
        $sData['cmid'] = $attend ? (int) $attend['comid'] : 'N/A';

        return $sData;
    }, $students);

    // Rank calculation
    $eligible = array_values(array_filter($studentData, fn($s) => $s['isEligibleForRank']));
    usort($eligible, fn($a, $b) => $b['grandTotal'] <=> $a['grandTotal']);

    foreach ($studentData as &$student) {
        if (!$student['isEligibleForRank']) {
            $student['rank'] = 'N/A';
        } else {
            $r = 0;
            foreach ($eligible as $i => $e) {
                if ($e['grandTotal'] === $student['grandTotal']) {
                    $r = $i + 1;
                    break;
                }
            }
            $student['rank'] = $r > 0 ? $r : 'N/A';
        }
    }
    unset($student);

    $finalGrandMax = count($subjects) * 100;
    $header[] = ['label' => 'Grand Total', 'subHeaders' => [['label' => 'Total', 'key' => 'grandTotal', 'maxm' => $finalGrandMax]]];
    $header[] = ['label' => 'Percentage', 'subHeaders' => [['label' => '%', 'key' => 'percentage', 'maxm' => 100]]];
    $header[] = ['label' => 'Rank', 'subHeaders' => [['label' => 'Rk', 'key' => 'rank']]];

    // Persist: overwrite finalhic/finalhictotal/finaltotal for this class, matching the source's side effect.
    mysqli_begin_transaction($mysqli);
    try {
        db_execute($mysqli, "DELETE FROM finalhic WHERE sclass = ?", 's', [$sclass]);
        db_execute($mysqli, "DELETE FROM finalhictotal WHERE sclass = ?", 's', [$sclass]);
        if (!empty($studentIds)) {
            db_execute($mysqli, "DELETE FROM finaltotal WHERE sid IN ($studentPlaceholders)", $studentTypes, $studentIds);
        }

        foreach ($subjectHics as $subid => $hic) {
            db_execute($mysqli, "INSERT INTO finalhic (sclass, subid, hic) VALUES (?, ?, ?)", 'sii', [$sclass, $subid, $hic]);
        }

        foreach ($studentData as $s) {
            $rank = $s['rank'] === 'N/A' ? null : $s['rank'];
            db_execute(
                $mysqli,
                "INSERT INTO finaltotal (sid, total_marks, percentage, `rank`) VALUES (?, ?, ?, ?)",
                'iidi',
                [$s['sid'], $s['grandTotal'], $s['percentage'], $rank]
            );
        }

        db_execute($mysqli, "INSERT INTO finalhictotal (sclass, thic) VALUES (?, ?)", 'si', [$sclass, $classGrandHic]);

        mysqli_commit($mysqli);
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        throw $e;
    }

    return ['header' => $header, 'studentData' => $studentData, 'gradeSubjects' => $gradeSubjects];
}
