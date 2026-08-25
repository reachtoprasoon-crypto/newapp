<?php
// Dynamic data-collection forms: a teacher/admin authors a form (freeform
// field list: Text/Number/Date/Select/Radio/Checkbox), students self-submit
// via a public kiosk keyed by form id, one response per student per form.
// Ports data-collection-flows.ts.

require_once __DIR__ . '/db.php';

// $id null -> insert; non-null -> update. $fields: array of
// ['label'=>string,'type'=>string,'options'=>string,'required'=>bool].
function upsert_data_collection_form($mysqli, $id, $tid, $title, $description, $fields, $isActive) {
    $fieldsJson = json_encode($fields);
    if ($id) {
        db_execute(
            $mysqli,
            "UPDATE data_collection_forms SET title = ?, description = ?, fields_json = ?, is_active = ? WHERE id = ?",
            'sssii',
            [$title, $description ?: null, $fieldsJson, $isActive ? 1 : 0, $id]
        );
        return ['success' => true, 'id' => $id];
    }
    $result = db_execute(
        $mysqli,
        "INSERT INTO data_collection_forms (tid, title, description, fields_json, is_active) VALUES (?, ?, ?, ?, ?)",
        'isssi',
        [$tid, $title, $description ?: null, $fieldsJson, $isActive ? 1 : 0]
    );
    return ['success' => true, 'id' => $result['insert_id']];
}

function get_data_collection_forms($mysqli, $tid = null) {
    if ($tid) {
        $rows = db_fetch_all(
            $mysqli,
            "SELECT f.*, t.tname as teacher_name FROM data_collection_forms f JOIN teachers t ON f.tid = t.tid WHERE f.tid = ? ORDER BY f.created_at DESC",
            'i',
            [$tid]
        );
    } else {
        $rows = db_fetch_all(
            $mysqli,
            "SELECT f.*, t.tname as teacher_name FROM data_collection_forms f JOIN teachers t ON f.tid = t.tid ORDER BY f.created_at DESC"
        );
    }
    return array_map(function ($r) {
        $r['id'] = (int) $r['id'];
        $r['tid'] = (int) $r['tid'];
        $r['fields'] = json_decode($r['fields_json'], true);
        $r['is_active'] = (bool) $r['is_active'];
        return $r;
    }, $rows);
}

// Public: only returns active forms (used by the /collect kiosk).
function get_data_collection_form_by_id($mysqli, $id) {
    $row = db_fetch_one($mysqli, "SELECT * FROM data_collection_forms WHERE id = ? AND is_active = 1", 'i', [$id]);
    if ($row === null) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['tid'] = (int) $row['tid'];
    $row['fields'] = json_decode($row['fields_json'], true);
    return $row;
}

function delete_data_collection_form($mysqli, $id) {
    db_execute($mysqli, "DELETE FROM data_collection_forms WHERE id = ?", 'i', [$id]);
    return ['success' => true];
}

function check_student_response_exists($mysqli, $formId, $sid) {
    $row = db_fetch_one($mysqli, "SELECT id FROM data_collection_responses WHERE form_id = ? AND sid = ?", 'ii', [$formId, $sid]);
    return $row !== null;
}

// One response per student per form (delete-then-resubmit is disallowed by
// the source's UI, but the DB itself upserts via ON DUPLICATE KEY UPDATE).
function submit_data_collection_response($mysqli, $formId, $sid, $responses) {
    $responseJson = json_encode($responses);
    db_execute(
        $mysqli,
        "INSERT INTO data_collection_responses (form_id, sid, response_json) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), submitted_at = CURRENT_TIMESTAMP",
        'iis',
        [$formId, $sid, $responseJson]
    );
    return ['success' => true];
}

function get_data_collection_responses($mysqli, $formId) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT r.*, s.sname, s.schno, s.roll, s.sclass
         FROM data_collection_responses r JOIN students s ON r.sid = s.sid
         WHERE r.form_id = ? ORDER BY s.sclass, s.roll",
        'i',
        [$formId]
    );
    return array_map(function ($r) {
        $r['id'] = (int) $r['id'];
        $r['form_id'] = (int) $r['form_id'];
        $r['sid'] = (int) $r['sid'];
        $r['roll'] = (int) $r['roll'];
        $r['schno'] = (int) $r['schno'];
        $r['responses'] = json_decode($r['response_json'], true);
        return $r;
    }, $rows);
}

function delete_data_collection_response($mysqli, $id) {
    db_execute($mysqli, "DELETE FROM data_collection_responses WHERE id = ?", 'i', [$id]);
    return ['success' => true];
}
