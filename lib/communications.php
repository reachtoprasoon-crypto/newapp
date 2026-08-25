<?php
// Teacher broadcast communications (Notice/Homework/Worksheet/Other) to one
// or more classes. Ports create-communication-flow.ts, get-communications-flow.ts,
// delete-communication-flow.ts.
//
// The live schema predates the source's current multi-class-recipients
// design (it had a single `communications.sclass` column, no join table) —
// the table was empty, so `communication_recipients` was added fresh and
// `communications.sclass` relaxed to nullable/unused rather than migrating
// any real data.

require_once __DIR__ . '/db.php';

function comm_valid_types() {
    return ['Notice', 'Homework', 'Worksheet', 'Other'];
}

function create_communication($mysqli, $tid, $sclasses, $title, $content, $attachmentName, $attachmentFile, $commType) {
    mysqli_begin_transaction($mysqli);
    try {
        $result = db_execute(
            $mysqli,
            "INSERT INTO communications (tid, title, content, attachment_name, attachment_file, comm_type) VALUES (?, ?, ?, ?, ?, ?)",
            'isssss',
            [$tid, $title, $content ?: null, $attachmentName ?: null, $attachmentFile ?: null, $commType]
        );
        $commid = $result['insert_id'];

        foreach ($sclasses as $sclass) {
            db_execute($mysqli, "INSERT INTO communication_recipients (commid, sclass) VALUES (?, ?)", 'is', [$commid, $sclass]);
        }

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

// $filters: optional 'tid' and/or 'sclass'.
function get_communications($mysqli, $filters = []) {
    $whereClauses = [];
    $types = '';
    $params = [];

    if (!empty($filters['tid'])) {
        $whereClauses[] = 'c.tid = ?';
        $types .= 'i';
        $params[] = $filters['tid'];
    }
    if (!empty($filters['sclass'])) {
        $whereClauses[] = 'cr.sclass = ?';
        $types .= 's';
        $params[] = $filters['sclass'];
    }

    $sql = "SELECT c.commid, c.tid, t.tname as teacher_name, c.title, c.content, c.attachment_name, c.attachment_file,
                   c.comm_type, c.created_at, GROUP_CONCAT(DISTINCT cr.sclass ORDER BY cr.sclass SEPARATOR ', ') as sclasses
            FROM communications c
            JOIN teachers t ON c.tid = t.tid
            JOIN communication_recipients cr ON c.commid = cr.commid";
    if (!empty($whereClauses)) {
        $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
    }
    $sql .= ' GROUP BY c.commid ORDER BY c.created_at DESC';

    $rows = db_fetch_all($mysqli, $sql, $types, $params);
    return array_map(function ($row) {
        return [
            'commid' => (int) $row['commid'],
            'tid' => (int) $row['tid'],
            'teacher_name' => $row['teacher_name'],
            'title' => $row['title'],
            'content' => $row['content'],
            'attachment_name' => $row['attachment_name'],
            'attachment_file' => $row['attachment_file'],
            'comm_type' => $row['comm_type'],
            'created_at' => $row['created_at'],
            'sclass' => $row['sclasses'],
        ];
    }, $rows);
}

// Ownership-enforced: only the authoring teacher may delete their own communication.
function delete_communication($mysqli, $commid, $tid) {
    $result = db_execute($mysqli, "DELETE FROM communications WHERE commid = ? AND tid = ?", 'ii', [$commid, $tid]);
    if ($result['affected'] === 0) {
        return ['success' => false, 'error' => 'Communication not found or you do not have permission to delete it.'];
    }
    return ['success' => true];
}
