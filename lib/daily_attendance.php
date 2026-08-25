<?php
// Daily self-attendance kiosk system (distinct from the term-based `attendance`
// table in lib/attendance.php). Ports daily-attendance-flows.ts.
//
// The source forces the DB session to IST ('+05:30') for every connection in
// this module so CURRENT_TIMESTAMP/DATE_FORMAT read/write consistently in
// school-local time regardless of server timezone; mirrored here per-request.

require_once __DIR__ . '/db.php';

function set_ist_timezone($mysqli) {
    mysqli_query($mysqli, "SET time_zone = '+05:30'");
}

function get_daily_attendance_status($mysqli, $sclass) {
    set_ist_timezone($mysqli);
    $today = date('Y-m-d');

    $settings = db_fetch_one($mysqli, "SELECT is_active FROM attendance_settings WHERE sclass = ?", 's', [$sclass]);
    $isActive = $settings !== null && (int) $settings['is_active'] === 1;

    $rows = db_fetch_all(
        $mysqli,
        "SELECT s.sid, s.roll, s.schno, s.sname, DATE_FORMAT(da.marked_at, '%h:%i %p') as marked_at_time
         FROM students s
         LEFT JOIN daily_attendance da ON s.sid = da.sid AND da.date = ?
         WHERE s.sclass = ?
         ORDER BY s.roll",
        'ss',
        [$today, $sclass]
    );

    $attendance = array_map(function ($r) {
        return [
            'sid' => (int) $r['sid'],
            'roll' => (int) $r['roll'],
            'schno' => (int) $r['schno'],
            'sname' => $r['sname'],
            'isPresent' => $r['marked_at_time'] !== null,
            'markedAt' => $r['marked_at_time'],
        ];
    }, $rows);

    return ['isActive' => $isActive, 'attendance' => $attendance];
}

function toggle_attendance_link($mysqli, $sclass, $isActive) {
    set_ist_timezone($mysqli);
    db_execute(
        $mysqli,
        "INSERT INTO attendance_settings (sclass, is_active) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)",
        'si',
        [$sclass, $isActive ? 1 : 0]
    );
    return ['success' => true];
}

// $date defaults to today (Y-m-d); admin/teacher manual override of one student's status.
function mark_student_attendance($mysqli, $sid, $isPresent, $date = null) {
    set_ist_timezone($mysqli);
    $date = $date ?: date('Y-m-d');
    if ($isPresent) {
        db_execute($mysqli, "INSERT IGNORE INTO daily_attendance (sid, date) VALUES (?, ?)", 'is', [$sid, $date]);
    } else {
        db_execute($mysqli, "DELETE FROM daily_attendance WHERE sid = ? AND date = ?", 'is', [$sid, $date]);
    }
    return ['success' => true];
}

// Public kiosk self-mark: verifies schno+dob, rejects Sundays/holidays/inactive link.
function self_mark_attendance($mysqli, $sclass, $schno, $dob) {
    set_ist_timezone($mysqli);
    $today = new DateTime();
    $dateStr = $today->format('Y-m-d');

    if ((int) $today->format('w') === 0) {
        return ['success' => false, 'error' => 'Attendance cannot be marked on Sundays.'];
    }

    $holiday = db_fetch_one(
        $mysqli,
        "SELECT id FROM attendance_holidays WHERE date = ? AND (sclass IS NULL OR sclass = ?)",
        'ss',
        [$dateStr, $sclass]
    );
    if ($holiday !== null) {
        return ['success' => false, 'error' => 'Today is a scheduled holiday.'];
    }

    $settings = db_fetch_one($mysqli, "SELECT is_active FROM attendance_settings WHERE sclass = ?", 's', [$sclass]);
    if ($settings === null || (int) $settings['is_active'] !== 1) {
        return ['success' => false, 'error' => 'The attendance link for your class is not currently active.'];
    }

    $student = db_fetch_one($mysqli, "SELECT sid, dob FROM students WHERE schno = ? AND sclass = ?", 'is', [$schno, $sclass]);
    if ($student === null) {
        return ['success' => false, 'error' => 'Invalid Scholar Number for this class.'];
    }

    $formattedDbDob = $student['dob'];
    if ($student['dob']) {
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $dt = DateTime::createFromFormat($format, $student['dob']);
            if ($dt !== false && $dt->format($format) === $student['dob']) {
                $formattedDbDob = $dt->format('d-m-Y');
                break;
            }
        }
    }

    if ($formattedDbDob !== $dob && $student['dob'] !== $dob) {
        return ['success' => false, 'error' => 'Verification failed: Date of Birth does not match.'];
    }

    db_execute($mysqli, "INSERT IGNORE INTO daily_attendance (sid, date) VALUES (?, ?)", 'is', [(int) $student['sid'], $dateStr]);
    return ['success' => true];
}

// $sclasses: array where null means "all classes" (a global holiday).
function set_holiday($mysqli, $date, $sclasses, $description, $isHoliday) {
    set_ist_timezone($mysqli);
    mysqli_begin_transaction($mysqli);
    try {
        foreach ($sclasses as $sclass) {
            if ($isHoliday) {
                db_execute(
                    $mysqli,
                    "INSERT INTO attendance_holidays (date, sclass, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)",
                    'sss',
                    [$date, $sclass, $description]
                );
            } else {
                // <=> is NULL-safe equality, matching the source's handling of the global (NULL sclass) holiday row.
                db_execute($mysqli, "DELETE FROM attendance_holidays WHERE date = ? AND (sclass <=> ?)", 'ss', [$date, $sclass]);
            }
        }
        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        throw $e;
    }
}

function set_holiday_range($mysqli, $startDate, $endDate, $sclasses, $description, $isHoliday) {
    set_ist_timezone($mysqli);
    $dates = [];
    $cursor = new DateTime($startDate);
    $end = new DateTime($endDate);
    while ($cursor <= $end) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    mysqli_begin_transaction($mysqli);
    try {
        foreach ($dates as $dateStr) {
            foreach ($sclasses as $sclass) {
                if ($isHoliday) {
                    db_execute(
                        $mysqli,
                        "INSERT INTO attendance_holidays (date, sclass, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)",
                        'sss',
                        [$dateStr, $sclass, $description]
                    );
                } else {
                    db_execute($mysqli, "DELETE FROM attendance_holidays WHERE date = ? AND (sclass <=> ?)", 'ss', [$dateStr, $sclass]);
                }
            }
        }
        mysqli_commit($mysqli);
        return ['success' => true];
    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        throw $e;
    }
}

function get_monthly_attendance_report($mysqli, $sclass, $month, $year) {
    set_ist_timezone($mysqli);

    $start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $end = (clone $start)->modify('last day of this month');

    $days = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $days[] = clone $cursor;
        $cursor->modify('+1 day');
    }

    $studentList = db_fetch_all($mysqli, "SELECT sid, roll, sname, schno FROM students WHERE sclass = ? ORDER BY roll", 's', [$sclass]);
    if (empty($studentList)) {
        return ['days' => [], 'students' => []];
    }
    $studentIds = array_map(fn($s) => (int) $s['sid'], $studentList);
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $types = str_repeat('i', count($studentIds));

    $startStr = $start->format('Y-m-d');
    $endStr = $end->format('Y-m-d');

    $attendanceRows = db_fetch_all(
        $mysqli,
        "SELECT sid, date FROM daily_attendance WHERE sid IN ($placeholders) AND date BETWEEN ? AND ?",
        $types . 'ss',
        [...$studentIds, $startStr, $endStr]
    );
    $attendanceMap = [];
    foreach ($attendanceRows as $a) {
        $d = $a['date'];
        $attendanceMap[$d][(int) $a['sid']] = true;
    }

    $grandTotalRows = db_fetch_all(
        $mysqli,
        "SELECT sid, COUNT(*) as present_days FROM daily_attendance WHERE sid IN ($placeholders) GROUP BY sid",
        $types,
        $studentIds
    );
    $grandTotalsMap = [];
    foreach ($grandTotalRows as $r) {
        $grandTotalsMap[(int) $r['sid']] = (int) $r['present_days'];
    }

    $holidayRows = db_fetch_all(
        $mysqli,
        "SELECT date, description FROM attendance_holidays WHERE (sclass IS NULL OR sclass = ?) AND date BETWEEN ? AND ?",
        'sss',
        [$sclass, $startStr, $endStr]
    );
    $holidayMap = [];
    foreach ($holidayRows as $h) {
        $holidayMap[$h['date']] = $h['description'];
    }

    $reportDays = array_map(function ($d) use ($holidayMap) {
        $dateStr = $d->format('Y-m-d');
        return [
            'date' => $dateStr,
            'day' => $d->format('j'),
            'isSunday' => (int) $d->format('w') === 0,
            'holiday' => $holidayMap[$dateStr] ?? null,
        ];
    }, $days);

    $workingDaysInMonth = count(array_filter($reportDays, fn($d) => !$d['isSunday'] && !$d['holiday']));

    $reportStudents = array_map(function ($s) use ($reportDays, $attendanceMap, $grandTotalsMap, $workingDaysInMonth) {
        $sid = (int) $s['sid'];
        $monthPresent = 0;
        $daily = array_map(function ($d) use ($sid, $attendanceMap, &$monthPresent) {
            $present = isset($attendanceMap[$d['date']][$sid]);
            if ($present && !$d['isSunday'] && !$d['holiday']) {
                $monthPresent++;
            }
            return $present;
        }, $reportDays);

        return [
            'sid' => $sid,
            'roll' => (int) $s['roll'],
            'sname' => $s['sname'],
            'schno' => (int) $s['schno'],
            'daily' => $daily,
            'monthPresent' => $monthPresent,
            'monthTotal' => $workingDaysInMonth,
            'grandPresent' => $grandTotalsMap[$sid] ?? 0,
        ];
    }, $studentList);

    return ['days' => $reportDays, 'students' => $reportStudents];
}
