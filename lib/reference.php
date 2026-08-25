<?php
// Reference-data lookups shared across the whole app (classes, subjects,
// teachers, houses, terms, exams, comments). Ports the ~9 trivial "get all X"
// flows 1:1 — same queries, same ordering.

require_once __DIR__ . '/db.php';

function get_all_classes($mysqli) {
    $rows = db_fetch_all($mysqli, "SELECT sclass FROM classes ORDER BY clid");
    return array_column($rows, 'sclass');
}

function get_all_subjects($mysqli) {
    return db_fetch_all($mysqli, "SELECT subid, subname, subshort, subtype FROM subjects ORDER BY subname");
}

function get_all_teachers($mysqli) {
    return db_fetch_all($mysqli, "SELECT tid, tname FROM teachers ORDER BY tname");
}

function get_all_houses($mysqli) {
    return db_fetch_all($mysqli, "SELECT hid, house FROM house ORDER BY house");
}

function get_all_terms($mysqli) {
    return db_fetch_all($mysqli, "SELECT termid, termname, termshort FROM terms ORDER BY termid");
}

function get_all_exams($mysqli) {
    if (!db_table_exists($mysqli, 'exams')) {
        return [];
    }
    return db_fetch_all($mysqli, "SELECT exid, examname, examshort FROM exams ORDER BY exid");
}

function get_all_comments($mysqli) {
    if (!db_table_exists($mysqli, 'comments')) {
        return [];
    }
    return db_fetch_all($mysqli, "SELECT comid, comment FROM comments ORDER BY comid");
}

// Unique classes a given teacher teaches, sorted by the global class order (classes.clid).
function get_teacher_classes($mysqli, $tid) {
    $allClassOrder = get_all_classes($mysqli);
    $rows = db_fetch_all($mysqli, "SELECT DISTINCT sclass FROM subjectteacher WHERE tid = ?", 'i', [$tid]);
    $teacherClasses = array_column($rows, 'sclass');

    usort($teacherClasses, function ($a, $b) use ($allClassOrder) {
        $indexA = array_search($a, $allClassOrder, true);
        $indexB = array_search($b, $allClassOrder, true);
        if ($indexA === false) return 1;
        if ($indexB === false) return -1;
        return $indexA <=> $indexB;
    });

    return $teacherClasses;
}

// Teacher search with subject/class assignments attached. Ports search-teachers-flow.ts.
function search_teachers($mysqli, $query) {
    require_once __DIR__ . '/dates.php';

    $searchQuery = '%' . $query . '%';
    $teacherRows = db_fetch_all(
        $mysqli,
        "SELECT tid, tname, tuser, tpass, dob, sclass, ttype FROM teachers WHERE tname LIKE ? OR tuser LIKE ?",
        'ss',
        [$searchQuery, $searchQuery]
    );

    $teachers = [];
    foreach ($teacherRows as $row) {
        $teachers[(int) $row['tid']] = [
            'tid' => (int) $row['tid'],
            'tname' => $row['tname'],
            'tuser' => $row['tuser'],
            'tpass' => $row['tpass'],
            'dob' => format_teacher_dob($row['dob']),
            'isClassTeacherOf' => ((int) $row['ttype'] === 1) ? $row['sclass'] : null,
            'subjectsTaught' => [],
        ];
    }

    if (empty($teachers)) {
        return [];
    }

    $teacherIds = array_keys($teachers);
    $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
    $types = str_repeat('i', count($teacherIds));

    $assignmentRows = db_fetch_all(
        $mysqli,
        "SELECT st.tid, s.subname, st.sclass
         FROM subjectteacher st
         JOIN subjects s ON st.subid = s.subid
         WHERE st.tid IN ($placeholders)
         ORDER BY st.sclass, s.subname",
        $types,
        $teacherIds
    );

    foreach ($assignmentRows as $row) {
        $tid = (int) $row['tid'];
        if (isset($teachers[$tid])) {
            $teachers[$tid]['subjectsTaught'][] = [
                'subname' => $row['subname'],
                'sclass' => $row['sclass'],
            ];
        }
    }

    return array_values($teachers);
}
