<?php
// Ports get-report-card-data-flow.ts (class-wide) and
// get-student-report-card-data-flow.ts (single student). Both build the same
// dynamic subject/exam header shape from termschedule, but differ in how the
// percentage denominator is computed (preserved exactly, not normalized):
//   - class-wide: per student, only counts max-marks for subjects that
//     student actually has a mark in (studentGrandMaxMarks accumulates only
//     when hasMarksForSubject).
//   - single-student: always divides by the fixed grand total of max marks
//     across every subject scheduled for the class, whether or not the
//     student has marks in it.

require_once __DIR__ . '/db.php';

// Distinct (term,report) combinations that have a schedule for this class —
// drives the term/report picker in the report-card export UI.
// Ports get-student-available-reports-flow.ts.
function get_student_available_reports($mysqli, $sclass) {
    if (!db_table_exists($mysqli, 'termschedule')) {
        return [];
    }
    $rows = db_fetch_all(
        $mysqli,
        "SELECT DISTINCT ts.termid, t.termname, ts.report
         FROM termschedule ts JOIN terms t ON ts.termid = t.termid
         WHERE ts.sclass = ? ORDER BY ts.termid, ts.report",
        's',
        [$sclass]
    );
    return array_map(fn($r) => ['termid' => (int) $r['termid'], 'termname' => $r['termname'], 'report' => (int) $r['report']], $rows);
}

// Shared: term schedules (subtype=1 "marks" subjects only) grouped by subject, in subid/exid order.
function build_subject_groups($mysqli, $sclass, $termid, $report) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT ts.termschid, ts.subid, s.subname, s.subshort, ts.exid, e.examshort, ts.maxm
         FROM termschedule ts
         JOIN subjects s ON ts.subid = s.subid
         JOIN exams e ON ts.exid = e.exid
         WHERE ts.sclass = ? AND ts.termid = ? AND ts.report = ? AND s.subtype = 1
         ORDER BY ts.subid, ts.exid",
        'sii',
        [$sclass, $termid, $report]
    );

    $groups = [];
    foreach ($rows as $r) {
        $subid = (int) $r['subid'];
        if (!isset($groups[$subid])) {
            $groups[$subid] = ['subid' => $subid, 'subname' => $r['subname'], 'subshort' => $r['subshort'], 'exams' => []];
        }
        $groups[$subid]['exams'][] = [
            'exid' => (int) $r['exid'],
            'examshort' => $r['examshort'],
            'termschid' => (int) $r['termschid'],
            'maxm' => (int) $r['maxm'],
        ];
    }
    // Already ordered by the query; exid sort is a no-op safeguard matching the source's explicit re-sort.
    foreach ($groups as &$g) {
        usort($g['exams'], fn($a, $b) => $a['exid'] <=> $b['exid']);
    }
    unset($g);

    return $groups;
}

function build_report_headers($subjectGroups, $grandMaxMarks) {
    $mainHeaders = [];
    foreach ($subjectGroups as $group) {
        $subjectMaxMarks = 0;
        $subHeaders = [];
        foreach ($group['exams'] as $e) {
            $subjectMaxMarks += $e['maxm'];
            $subHeaders[] = ['label' => $e['examshort'], 'key' => 'mark_' . $e['termschid'], 'maxm' => $e['maxm']];
        }
        $subHeaders[] = ['label' => 'Total', 'key' => 'total_' . $group['subid'], 'maxm' => $subjectMaxMarks];

        $mainHeaders[] = [
            'label' => $group['subname'],
            'subshort' => $group['subshort'],
            'colSpan' => count($subHeaders),
            'subHeaders' => $subHeaders,
            'subid' => $group['subid'],
        ];
    }

    $mainHeaders[] = ['label' => 'Grand Total', 'subshort' => 'Grand Total', 'colSpan' => 1, 'subHeaders' => [['label' => 'Total', 'key' => 'grandTotal', 'maxm' => $grandMaxMarks]]];
    $mainHeaders[] = ['label' => 'Percentage', 'subshort' => '%', 'colSpan' => 1, 'subHeaders' => [['label' => '%', 'key' => 'percentage', 'maxm' => 100]]];

    return $mainHeaders;
}

function get_report_card_data($mysqli, $sclass, $termid, $report) {
    $students = db_fetch_all($mysqli, "SELECT sid, roll, sname, schno FROM students WHERE sclass = ? ORDER BY roll", 's', [$sclass]);
    if (empty($students)) {
        return ['header' => [], 'studentData' => []];
    }
    $studentIds = array_map(fn($s) => (int) $s['sid'], $students);

    $subjectGroups = build_subject_groups($mysqli, $sclass, $termid, $report);

    $marksMap = [];
    if (!empty($subjectGroups)) {
        $scheduleIds = [];
        foreach ($subjectGroups as $g) {
            foreach ($g['exams'] as $e) {
                $scheduleIds[] = $e['termschid'];
            }
        }
        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        $schedulePlaceholders = implode(',', array_fill(0, count($scheduleIds), '?'));
        $marksRows = db_fetch_all(
            $mysqli,
            "SELECT sid, termschid, marks FROM marks WHERE sid IN ($studentPlaceholders) AND termschid IN ($schedulePlaceholders)",
            str_repeat('i', count($studentIds)) . str_repeat('i', count($scheduleIds)),
            array_merge($studentIds, $scheduleIds)
        );
        foreach ($marksRows as $m) {
            $marksMap[(int) $m['sid']][(int) $m['termschid']] = (int) $m['marks'];
        }
    }

    $fixedGrandMaxMarks = 0;
    foreach ($subjectGroups as $g) {
        foreach ($g['exams'] as $e) {
            $fixedGrandMaxMarks += $e['maxm'];
        }
    }

    $header = build_report_headers($subjectGroups, $fixedGrandMaxMarks);

    $studentData = array_map(function ($student) use ($subjectGroups, $marksMap) {
        $sid = (int) $student['sid'];
        $studentMarks = $marksMap[$sid] ?? [];
        $row = ['sid' => $sid, 'roll' => (int) $student['roll'], 'sname' => $student['sname'], 'schno' => (int) $student['schno'], 'marks' => []];

        $grandTotal = 0;
        $studentGrandMaxMarks = 0;

        foreach ($subjectGroups as $group) {
            $subjectTotal = 0;
            $hasMarksForSubject = false;

            foreach ($group['exams'] as $exam) {
                $mark = $studentMarks[$exam['termschid']] ?? null;
                $row['mark_' . $exam['termschid']] = $mark;
                $row['marks'][] = ['subid' => $group['subid'], 'marksObtained' => $mark];
                if ($mark !== null) {
                    $subjectTotal += $mark;
                    $hasMarksForSubject = true;
                }
            }

            $subjectMaxForStudent = 0;
            if ($hasMarksForSubject) {
                foreach ($group['exams'] as $e) {
                    $subjectMaxForStudent += $e['maxm'];
                }
            }

            $row['total_' . $group['subid']] = $hasMarksForSubject ? $subjectTotal : null;
            $grandTotal += $subjectTotal;
            $studentGrandMaxMarks += $subjectMaxForStudent;
        }

        $row['grandTotal'] = $grandTotal;
        $row['percentage'] = $studentGrandMaxMarks > 0 ? round(($grandTotal / $studentGrandMaxMarks) * 100, 2) : 0;

        return $row;
    }, $students);

    return ['header' => $header, 'studentData' => $studentData];
}

function get_student_report_card_data($mysqli, $sid, $termid, $report) {
    $student = db_fetch_one($mysqli, "SELECT sid, roll, sname, sclass FROM students WHERE sid = ?", 'i', [$sid]);
    if ($student === null) {
        return ['header' => [], 'studentData' => []];
    }
    $sclass = $student['sclass'];

    $subjectGroups = build_subject_groups($mysqli, $sclass, $termid, $report);

    $marksMap = [];
    if (!empty($subjectGroups)) {
        $scheduleIds = [];
        foreach ($subjectGroups as $g) {
            foreach ($g['exams'] as $e) {
                $scheduleIds[] = $e['termschid'];
            }
        }
        $schedulePlaceholders = implode(',', array_fill(0, count($scheduleIds), '?'));
        $marksRows = db_fetch_all(
            $mysqli,
            "SELECT termschid, marks FROM marks WHERE sid = ? AND termschid IN ($schedulePlaceholders)",
            'i' . str_repeat('i', count($scheduleIds)),
            array_merge([$sid], $scheduleIds)
        );
        foreach ($marksRows as $m) {
            $marksMap[(int) $m['termschid']] = (int) $m['marks'];
        }
    }

    $grandMaxMarks = 0;
    foreach ($subjectGroups as $g) {
        foreach ($g['exams'] as $e) {
            $grandMaxMarks += $e['maxm'];
        }
    }

    $header = build_report_headers($subjectGroups, $grandMaxMarks);

    $row = ['sid' => (int) $student['sid'], 'roll' => (int) $student['roll'], 'sname' => $student['sname'], 'marks' => []];
    $grandTotal = 0;

    foreach ($subjectGroups as $group) {
        $subjectTotal = 0;
        $hasMarksForSubject = false;
        foreach ($group['exams'] as $exam) {
            $mark = $marksMap[$exam['termschid']] ?? null;
            $row['mark_' . $exam['termschid']] = $mark;
            $row['marks'][] = ['subid' => $group['subid'], 'marksObtained' => $mark];
            if ($mark !== null) {
                $subjectTotal += $mark;
                $hasMarksForSubject = true;
            }
        }
        $row['total_' . $group['subid']] = $hasMarksForSubject ? $subjectTotal : null;
        $grandTotal += $subjectTotal;
    }

    $row['grandTotal'] = $grandTotal;
    $row['percentage'] = $grandMaxMarks > 0 ? round(($grandTotal / $grandMaxMarks) * 100, 2) : 0;

    return ['header' => $header, 'studentData' => [$row]];
}
