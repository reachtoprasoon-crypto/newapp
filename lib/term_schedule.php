<?php
// Term schedule (assessment definitions) and class-subject lookups feeding
// the marks/grade-entry subject picker. Ports get-term-schedules-flow.ts,
// get-class-subjects-schedule-flow.ts, get-teacher-subjects-flow.ts,
// get-class-subjects-flow.ts, get-scheduled-graded-subjects-flow.ts.

require_once __DIR__ . '/db.php';

// $filters: optional keys sclass, subid, termid, report.
function get_term_schedules($mysqli, $filters) {
    $whereClauses = [];
    $types = '';
    $params = [];

    if (!empty($filters['sclass'])) {
        $whereClauses[] = 'ts.sclass = ?';
        $types .= 's';
        $params[] = $filters['sclass'];
    }
    if (!empty($filters['subid'])) {
        $whereClauses[] = 'ts.subid = ?';
        $types .= 'i';
        $params[] = $filters['subid'];
    }
    if (!empty($filters['termid'])) {
        $whereClauses[] = 'ts.termid = ?';
        $types .= 'i';
        $params[] = $filters['termid'];
    }
    if (isset($filters['report']) && $filters['report'] !== '') {
        $whereClauses[] = 'ts.report = ?';
        $types .= 'i';
        $params[] = $filters['report'];
    }

    $sql = "SELECT ts.termschid, ts.sclass, ts.termid, ts.subid, ts.exid, e.examname, e.examshort, ts.maxm, sub.subtype
            FROM termschedule ts
            JOIN exams e ON ts.exid = e.exid
            JOIN subjects sub ON ts.subid = sub.subid";
    if (!empty($whereClauses)) {
        $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
    }
    $sql .= ' ORDER BY e.examname';

    $rows = db_fetch_all($mysqli, $sql, $types, $params);
    foreach ($rows as &$row) {
        $row['termschid'] = (int) $row['termschid'];
        $row['termid'] = (int) $row['termid'];
        $row['subid'] = (int) $row['subid'];
        $row['exid'] = (int) $row['exid'];
        $row['maxm'] = (int) $row['maxm'];
        $row['subtype'] = (int) $row['subtype'];
    }
    return $rows;
}

// All subjects for a class with isScheduled flag (LEFT JOIN subschedule).
function get_class_subjects_schedule($mysqli, $sclass) {
    if (!db_table_exists($mysqli, 'subschedule')) {
        return [];
    }
    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.subid, s.subname, s.subshort, s.subtype, ss.schorder, ss.subschid
         FROM subjects s
         LEFT JOIN subschedule ss ON s.subid = ss.subid AND ss.sclass = ?
         ORDER BY ss.schorder ASC, s.subname ASC",
        's',
        [$sclass]
    );
    return array_map(function ($row) {
        return [
            'subschid' => $row['subschid'] === null ? null : (int) $row['subschid'],
            'subid' => (int) $row['subid'],
            'subname' => $row['subname'],
            'subshort' => $row['subshort'],
            'subtype' => (int) $row['subtype'],
            'schorder' => $row['schorder'] === null ? null : (int) $row['schorder'],
            'isScheduled' => $row['subschid'] !== null,
        ];
    }, $rows);
}

function get_teacher_subjects_for_class($mysqli, $tid, $sclass) {
    return db_fetch_all(
        $mysqli,
        "SELECT s.subid, s.subname, s.subshort, s.subtype
         FROM subjectteacher st
         JOIN subjects s ON st.subid = s.subid
         WHERE st.tid = ? AND st.sclass = ?
         ORDER BY s.subname",
        'is',
        [$tid, $sclass]
    );
}

// Special-cased subjects (Moral Science, SUPW) available only to a class's own class teacher.
function get_class_subjects_for_grading($mysqli, $isClassTeacher) {
    if (!$isClassTeacher) {
        return [];
    }
    return db_fetch_all(
        $mysqli,
        "SELECT subid, subname, subshort, subtype FROM subjects WHERE subname IN ('Moral Science', 'SUPW') ORDER BY subname"
    );
}

function get_scheduled_graded_subjects_for_class($mysqli, $sclass) {
    if (!db_table_exists($mysqli, 'subschedule')) {
        return [];
    }
    return db_fetch_all(
        $mysqli,
        "SELECT s.subid, s.subname, s.subshort, s.subtype
         FROM subjects s
         JOIN subschedule ss ON s.subid = ss.subid
         WHERE ss.sclass = ? AND s.subtype = 0
         ORDER BY ss.schorder ASC, s.subname ASC",
        's',
        [$sclass]
    );
}

// Marks-only (subtype=1) subjects scheduled for a class, each paired with its
// existing termschid/maxm for the given term/report/exam (null if not yet set).
// Ports get-term-schedule-for-form-flow.ts.
function get_term_schedule_for_form($mysqli, $sclass, $termid, $report, $exid) {
    $rows = db_fetch_all(
        $mysqli,
        "SELECT
            s.subid, s.subname, s.subshort, s.subtype,
            MAX(ts.termschid) as termschid,
            MAX(ts.maxm) as maxm,
            MAX(ss.schorder) as schorder
         FROM subjects s
         INNER JOIN subschedule ss ON s.subid = ss.subid AND ss.sclass = ?
         LEFT JOIN termschedule ts ON s.subid = ts.subid
           AND ts.sclass = ?
           AND ts.termid = ?
           AND ts.report = ?
           AND ts.exid = ?
         WHERE s.subtype = 1
         GROUP BY s.subid, s.subname, s.subshort, s.subtype
         ORDER BY schorder ASC, s.subname ASC",
        'ssiii',
        [$sclass, $sclass, $termid, $report, $exid]
    );
    return array_map(function ($row) {
        return [
            'subid' => (int) $row['subid'],
            'subname' => $row['subname'],
            'subshort' => $row['subshort'],
            'subtype' => (int) $row['subtype'],
            'termschid' => $row['termschid'] === null ? null : (int) $row['termschid'],
            'maxm' => $row['maxm'] === null ? null : (int) $row['maxm'],
        ];
    }, $rows);
}

// Self-heals the same way the source does: dedupes any leftover duplicate
// (sclass,termid,report,exid,subid) rows (keeping the lowest termschid) and
// ensures the unique index exists — a no-op once the table is already clean.
function cleanup_and_index_term_schedule($mysqli) {
    try {
        db_execute(
            $mysqli,
            "DELETE t1 FROM termschedule t1
             INNER JOIN termschedule t2
             WHERE t1.termschid > t2.termschid
             AND t1.sclass = t2.sclass
             AND t1.termid = t2.termid
             AND t1.subid = t2.subid
             AND t1.exid = t2.exid
             AND t1.report = t2.report"
        );

        $index = db_fetch_one($mysqli, "SHOW INDEX FROM termschedule WHERE Key_name = 'unique_schedule_context'");
        if ($index === null) {
            db_execute($mysqli, "ALTER TABLE termschedule ADD UNIQUE KEY unique_schedule_context (sclass, termid, report, exid, subid)");
        }
    } catch (Exception $e) {
        // Cleanup/index creation failing (e.g. already clean) is non-fatal, matches source.
    }
}

// Check-then-update-or-insert per subject, preserving termschid continuity
// (rather than a blind delete+reinsert) so other tables referencing termschid
// (marks, view-summary caches) aren't orphaned. Ports upsert-term-schedule-flow.ts.
// $schedules: array of ['subid'=>int, 'maxm'=>int]
function upsert_term_schedule($mysqli, $sclass, $termid, $report, $exid, $schedules) {
    mysqli_begin_transaction($mysqli);
    try {
        cleanup_and_index_term_schedule($mysqli);

        foreach ($schedules as $item) {
            $existing = db_fetch_one(
                $mysqli,
                "SELECT termschid FROM termschedule WHERE sclass = ? AND termid = ? AND subid = ? AND exid = ? AND report = ? LIMIT 1",
                'siiii',
                [$sclass, $termid, $item['subid'], $exid, $report]
            );

            if ($existing !== null) {
                db_execute($mysqli, "UPDATE termschedule SET maxm = ? WHERE termschid = ?", 'ii', [$item['maxm'], $existing['termschid']]);
            } else {
                db_execute(
                    $mysqli,
                    "INSERT INTO termschedule (sclass, termid, subid, exid, maxm, report) VALUES (?, ?, ?, ?, ?, ?)",
                    'siiiii',
                    [$sclass, $termid, $item['subid'], $exid, $item['maxm'], $report]
                );
            }
        }

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}

// Copies a class's whole term/report schedule to N target classes, replacing
// each target's existing schedule for that term/report. Ports copy-term-schedule-flow.ts.
function copy_term_schedule($mysqli, $sourceSclass, $sourceTermid, $sourceReport, $targetSclasses) {
    if (empty($targetSclasses)) {
        return ['success' => true];
    }

    mysqli_begin_transaction($mysqli);
    try {
        $sourceSchedule = db_fetch_all(
            $mysqli,
            "SELECT subid, exid, maxm FROM termschedule WHERE sclass = ? AND termid = ? AND report = ?",
            'sii',
            [$sourceSclass, $sourceTermid, $sourceReport]
        );

        if (empty($sourceSchedule)) {
            mysqli_rollback($mysqli);
            return ['success' => false, 'error' => 'Source schedule not found or is empty. Nothing to copy.'];
        }

        foreach ($targetSclasses as $targetClass) {
            db_execute(
                $mysqli,
                "DELETE FROM termschedule WHERE sclass = ? AND termid = ? AND report = ?",
                'sii',
                [$targetClass, $sourceTermid, $sourceReport]
            );

            foreach ($sourceSchedule as $row) {
                db_execute(
                    $mysqli,
                    "INSERT INTO termschedule (sclass, termid, subid, exid, maxm, report) VALUES (?, ?, ?, ?, ?, ?)",
                    'siiiii',
                    [$targetClass, $sourceTermid, $row['subid'], $row['exid'], $row['maxm'], $sourceReport]
                );
            }
        }

        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()];
    }
}
