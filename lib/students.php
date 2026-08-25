<?php
// Student CRUD/search. Ports get-students-flow.ts, search-students-flow.ts,
// add-student-flow.ts, update-student-flow.ts, delete-student-flow.ts,
// update-student-roll-numbers-flow.ts, bulk-update-student-photos-flow.ts.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dates.php';
require_once __DIR__ . '/controls.php';

// Shared schno+DOB verification, ported once from student-credential-verification.ts.
// Reused by login (api/auth/login.php, inlined there) and by any other public
// kiosk that needs to re-verify a student's identity server-side (e.g. the
// data-collection response submit — the source trusted a client-held sid
// after an earlier one-time verify step; this closes that gap by requiring
// the same schno+dob check again at the point of the actual write).
function verify_student_credentials($mysqli, $schno, $dob) {
    if (!is_student_login_allowed($mysqli)) {
        return ['isValid' => false, 'error' => 'Student login is currently disabled by the administrator.'];
    }

    $student = db_fetch_one($mysqli, "SELECT sid, schno, sname, sclass, dob, photo FROM students WHERE schno = ?", 'i', [$schno]);
    if ($student === null) {
        return ['isValid' => false, 'error' => 'Invalid Scholar Number or Date of Birth.'];
    }

    $formattedDob = normalize_dob_display($student['dob']);
    if ($formattedDob !== $dob) {
        return ['isValid' => false, 'error' => 'Invalid Scholar Number or Date of Birth.'];
    }

    return [
        'isValid' => true,
        'student' => [
            'sid' => (int) $student['sid'],
            'schno' => (int) $student['schno'],
            'sname' => $student['sname'],
            'sclass' => $student['sclass'],
            'photo' => $student['photo'],
        ],
    ];
}

function map_student_row($row) {
    return [
        'sid' => (int) $row['sid'],
        'schno' => (int) $row['schno'],
        'roll' => (int) $row['roll'],
        'sname' => $row['sname'],
        'pname' => $row['pname'],
        'mname' => $row['mname'],
        'dob' => normalize_dob_display($row['dob']),
        'sclass' => $row['sclass'],
        'branch' => $row['branch'],
        'phone' => $row['phone'],
        'hid' => (int) $row['hid'],
        'house' => $row['house'] ?? null,
        'ht' => (int) $row['ht'],
        'wt' => (int) $row['wt'],
        'photo' => $row['photo'],
    ];
}

function get_students_by_class($mysqli, $sclass) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.schno, s.roll, s.sname, s.pname, s.mname, s.dob, s.sclass, s.branch, s.phone, s.hid, h.house, s.ht, s.wt, s.photo
         FROM students s
         LEFT JOIN house h ON s.hid = h.hid
         WHERE s.sclass = ?
         ORDER BY s.roll",
        's',
        [$sclass]
    );
    return array_map('map_student_row', $rows);
}

function search_students($mysqli, $query) {
    $searchQuery = '%' . $query . '%';
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.schno, s.roll, s.sname, s.pname, s.mname, s.dob, s.sclass, s.branch, s.phone, s.hid, h.house, s.ht, s.wt, s.photo
         FROM students s
         LEFT JOIN house h ON s.hid = h.hid
         WHERE s.sname LIKE ? OR s.pname LIKE ? OR s.schno LIKE ?",
        'sss',
        [$searchQuery, $searchQuery, $searchQuery]
    );
    return array_map('map_student_row', $rows);
}

// dob is accepted as-submitted ('dd-MM-yyyy') and stored as-is, matching the
// source's comment: the VARCHAR column is written directly without reformatting.
function add_student($mysqli, $input) {
    try {
        db_execute(
            $mysqli,
            "INSERT INTO students (schno, roll, sname, pname, mname, dob, sclass, branch, phone, hid, ht, wt, photo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'iisssssssiiis',
            [
                $input['schno'], $input['roll'], $input['sname'],
                $input['pname'] ?: null, $input['mname'] ?: null, $input['dob'],
                $input['sclass'], $input['branch'] ?: null, $input['phone'] ?: null,
                $input['hid'], $input['ht'], $input['wt'], $input['photo'] ?: null,
            ]
        );
        return ['success' => true];
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            return ['success' => false, 'error' => 'A student with this scholar number or roll number in this class already exists.'];
        }
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

function update_student($mysqli, $input) {
    try {
        db_execute(
            $mysqli,
            "UPDATE students
             SET schno = ?, roll = ?, sname = ?, pname = ?, mname = ?, dob = ?, sclass = ?, branch = ?, phone = ?, hid = ?, ht = ?, wt = ?, photo = ?
             WHERE sid = ?",
            'iisssssssiiisi',
            [
                $input['schno'], $input['roll'], $input['sname'],
                $input['pname'] ?: null, $input['mname'] ?: null, $input['dob'],
                $input['sclass'], $input['branch'] ?: null, $input['phone'] ?: null,
                $input['hid'], $input['ht'], $input['wt'], $input['photo'] ?: null,
                $input['sid'],
            ]
        );
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

// Soft delete: archives the student into class "13Z" rather than a real DELETE.
function delete_student($mysqli, $sid) {
    try {
        db_execute($mysqli, "UPDATE students SET sclass = '13Z' WHERE sid = ?", 'i', [$sid]);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

function update_student_roll_numbers($mysqli, $students) {
    if (empty($students)) {
        return ['success' => true];
    }
    mysqli_begin_transaction($mysqli);
    try {
        foreach ($students as $s) {
            db_execute($mysqli, "UPDATE students SET roll = ? WHERE sid = ?", 'ii', [$s['roll'], $s['sid']]);
        }
        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

function bulk_update_student_photos($mysqli, $updates) {
    mysqli_begin_transaction($mysqli);
    try {
        $updatedCount = 0;
        foreach ($updates as $u) {
            $result = db_execute($mysqli, "UPDATE students SET photo = ? WHERE schno = ?", 'si', [$u['photo'], $u['schno']]);
            if ($result['affected'] > 0) {
                $updatedCount++;
            }
        }
        mysqli_commit($mysqli);
        return ['success' => true, 'updatedCount' => $updatedCount];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'updatedCount' => 0, 'error' => $e->getMessage()];
    }
}
