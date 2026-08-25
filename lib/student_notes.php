<?php
// Private per-student notes, one teacher's note is not visible/editable by
// another (ownership enforced on write). Ports student-notes-flow.ts.

require_once __DIR__ . '/db.php';

function get_student_notes($mysqli, $sid) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT sn.note_id, sn.sid, sn.tid, t.tname as teacher_name, sn.note_content, sn.created_at, sn.updated_at
         FROM student_notes sn JOIN teachers t ON sn.tid = t.tid
         WHERE sn.sid = ? ORDER BY sn.updated_at DESC",
        'i',
        [$sid]
    );
    foreach ($rows as &$r) {
        $r['note_id'] = (int) $r['note_id'];
        $r['sid'] = (int) $r['sid'];
        $r['tid'] = (int) $r['tid'];
    }
    return $rows;
}

// $noteId null -> insert; non-null -> update (ownership-checked via WHERE tid=?).
function upsert_student_note($mysqli, $noteId, $sid, $tid, $noteContent) {
    if ($noteId) {
        db_execute($mysqli, "UPDATE student_notes SET note_content = ? WHERE note_id = ? AND tid = ?", 'sii', [$noteContent, $noteId, $tid]);
        return ['success' => true, 'note_id' => $noteId];
    }
    $result = db_execute($mysqli, "INSERT INTO student_notes (sid, tid, note_content) VALUES (?, ?, ?)", 'iis', [$sid, $tid, $noteContent]);
    return ['success' => true, 'note_id' => $result['insert_id']];
}

// Ownership-enforced (the source's deleteStudentNoteFlow takes no tid at all
// and lets any teacher delete any note — tightened here since these are
// meant to be private per-teacher notes).
function delete_student_note($mysqli, $noteId, $tid) {
    $result = db_execute($mysqli, "DELETE FROM student_notes WHERE note_id = ? AND tid = ?", 'ii', [$noteId, $tid]);
    if ($result['affected'] === 0) {
        return ['success' => false, 'error' => 'Note not found or you do not have permission to delete it.'];
    }
    return ['success' => true];
}
