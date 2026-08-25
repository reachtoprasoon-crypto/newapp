<?php
// Cross-class reporting: the school-wide "grand total" leaderboard and the
// combined class roster (marks + grades + attendance in one sheet).
// Ports get-all-students-total-flow.ts and class-roster.tsx's data assembly.

require_once __DIR__ . '/db.php';

// Splits a class name like "8A"/"10-B"/"VIII-C" into class+section parts,
// same heuristic as the source (trailing letter = section).
function split_class_section($sclass) {
    if ($sclass === null || $sclass === '') {
        return ['classPart' => '', 'sectionPart' => ''];
    }
    if (preg_match('/^(.+?)[-\s]?([A-Z])$/', $sclass, $m)) {
        return ['classPart' => $m[1], 'sectionPart' => $m[2]];
    }
    $lastChar = substr($sclass, -1);
    if (preg_match('/[A-Z]/', $lastChar)) {
        return ['classPart' => rtrim(substr($sclass, 0, -1), '-'), 'sectionPart' => $lastChar];
    }
    return ['classPart' => $sclass, 'sectionPart' => ''];
}

function get_all_students_total($mysqli) {
    if (!db_table_exists($mysqli, 'finaltotal')) {
        return [];
    }
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.schno, s.roll, s.sname, s.sclass, ft.total_marks
         FROM students s LEFT JOIN finaltotal ft ON s.sid = ft.sid
         WHERE s.sclass != '13Z'
         ORDER BY s.sclass, s.roll"
    );

    return array_map(function ($r) {
        $split = split_class_section($r['sclass'] ?? '');
        return [
            'schno' => (int) $r['schno'],
            'roll' => (int) $r['roll'],
            'sname' => $r['sname'],
            'sclass' => $r['sclass'] ?? '',
            'classPart' => $split['classPart'],
            'sectionPart' => $split['sectionPart'],
            'total_marks' => $r['total_marks'] !== null ? (float) $r['total_marks'] : null,
        ];
    }, $rows);
}

// Composes report-card data + grade subjects + per-subject grades + attendance
// into one enriched structure, matching class-roster.tsx's handleFetchRoster.
// Also persists HIC as a side effect (setHicDataAction call in the source).
function get_class_roster_data($mysqli, $sclass, $termid, $report) {
    require_once __DIR__ . '/report_card.php';
    require_once __DIR__ . '/term_schedule.php';
    require_once __DIR__ . '/grades.php';
    require_once __DIR__ . '/attendance.php';
    require_once __DIR__ . '/final_results.php';

    $reportData = get_report_card_data($mysqli, $sclass, $termid, $report);
    if (empty($reportData['studentData'])) {
        return null;
    }

    $gradeSubjects = get_scheduled_graded_subjects_for_class($mysqli, $sclass);
    $attendanceRecords = get_attendance_for_class($mysqli, $sclass, $termid, $report);

    $studentGradesMap = [];
    $studentAttendanceMap = [];
    foreach ($reportData['studentData'] as $student) {
        $sid = $student['sid'];
        $studentGradesMap[$sid] = [];
        $attendanceRecord = null;
        foreach ($attendanceRecords as $a) {
            if ($a['sid'] === $sid) { $attendanceRecord = $a; break; }
        }
        $studentAttendanceMap[$sid] = [
            'attendance' => $attendanceRecord['attendance'] ?? null,
            'totalattendance' => $attendanceRecord['totalattendance'] ?? null,
            'comid' => $attendanceRecord['comid'] ?? null,
        ];
    }

    foreach ($gradeSubjects as $gs) {
        $gradesForSubject = get_grades_for_subject($mysqli, $sclass, $gs['subid'], $termid, $report);
        foreach ($gradesForSubject as $gr) {
            if (isset($studentGradesMap[$gr['sid']])) {
                $studentGradesMap[$gr['sid']][$gs['subid']] = $gr['grade'] ?? null;
            }
        }
    }

    // Side effect, matching the source: recompute/persist HIC for this context.
    $hicStudents = array_map(function ($row) {
        return ['grandTotal' => $row['grandTotal'], 'marks' => $row['marks']];
    }, $reportData['studentData']);
    set_hic_data($mysqli, $sclass, $termid, $report, $hicStudents);

    return [
        'header' => $reportData['header'],
        'studentData' => $reportData['studentData'],
        'gradeSubjects' => $gradeSubjects,
        'studentGrades' => $studentGradesMap,
        'studentAttendance' => $studentAttendanceMap,
    ];
}
