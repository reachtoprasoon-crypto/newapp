<?php
// Teacher CRUD + class-teacher assignment. Ports add-teacher-flow.ts,
// update-teacher-details-flow.ts, get-teacher-details-flow.ts,
// assign-class-teacher-flow.ts. (No delete-teacher flow exists in the
// source app, so none is ported here.)

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dates.php';

function add_teacher($mysqli, $tname, $tuser, $tpass, $dobInput) {
    $mysqlDate = parse_teacher_dob_input($dobInput);
    try {
        db_execute(
            $mysqli,
            "INSERT INTO teachers (tname, tuser, tpass, dob) VALUES (?, ?, ?, ?)",
            'ssss',
            [$tname, $tuser, $tpass, $mysqlDate]
        );
        return ['success' => true];
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            return ['success' => false, 'error' => 'A teacher with this username already exists.'];
        }
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

// Partial update: only fields present in $fields (associative array) are set.
// Recognized keys: tname, tuser, tpass, phone, dob ('dd/MM/yyyy' or '' to clear).
function update_teacher_details($mysqli, $tid, $fields) {
    $updates = [];
    $types = '';
    $params = [];

    foreach (['tname', 'tuser', 'tpass'] as $key) {
        if (array_key_exists($key, $fields)) {
            $updates[] = "$key = ?";
            $types .= 's';
            $params[] = $fields[$key];
        }
    }
    if (array_key_exists('phone', $fields)) {
        $updates[] = 'phone = ?';
        $types .= 's';
        $params[] = $fields['phone'] ?: null;
    }
    if (array_key_exists('dob', $fields)) {
        $updates[] = 'dob = ?';
        $types .= 's';
        $params[] = parse_teacher_dob_input($fields['dob']);
    }

    if (empty($updates)) {
        return ['success' => true];
    }

    $types .= 'i';
    $params[] = $tid;

    try {
        db_execute($mysqli, "UPDATE teachers SET " . implode(', ', $updates) . " WHERE tid = ?", $types, $params);
        return ['success' => true];
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            return ['success' => false, 'error' => 'A teacher with this username already exists.'];
        }
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

function get_teacher_details($mysqli, $tid) {
    $row = db_fetch_one(
        $mysqli,
        "SELECT tid, tname, tuser, tpass, ttype, sclass, phone, DATE_FORMAT(dob, '%d/%m/%Y') as dob
         FROM teachers WHERE tid = ?",
        'i',
        [$tid]
    );
    if ($row === null) {
        return null;
    }

    $teacher = [
        'tid' => (int) $row['tid'],
        'tname' => $row['tname'],
        'tuser' => $row['tuser'],
        'tpass' => $row['tpass'],
        'dob' => $row['dob'],
        'phone' => $row['phone'] ?: '',
        'isClassTeacherOf' => ((int) $row['ttype'] === 1) ? $row['sclass'] : null,
        'subjectsTaught' => [],
    ];

    $assignments = db_fetch_all(
        $mysqli,
        "SELECT s.subname, st.sclass
         FROM subjectteacher st
         JOIN subjects s ON st.subid = s.subid
         WHERE st.tid = ?
         ORDER BY st.sclass, s.subname",
        'i',
        [$tid]
    );
    $teacher['subjectsTaught'] = array_map(function ($r) {
        return ['subname' => $r['subname'], 'sclass' => $r['sclass']];
    }, $assignments);

    return $teacher;
}

// Unassigns any existing class teacher (ttype=1) for $sclass, then assigns $tid.
// Uses '-' (not NULL) for "no class assigned" — sclass is NOT NULL on the live
// schema, and every existing unassigned row already uses '-' as its sentinel.
function assign_class_teacher($mysqli, $tid, $sclass) {
    mysqli_begin_transaction($mysqli);
    try {
        db_execute($mysqli, "UPDATE teachers SET ttype = 0, sclass = '-' WHERE sclass = ? AND ttype = 1", 's', [$sclass]);
        db_execute($mysqli, "UPDATE teachers SET ttype = 1, sclass = ? WHERE tid = ?", 'si', [$sclass, $tid]);
        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}
