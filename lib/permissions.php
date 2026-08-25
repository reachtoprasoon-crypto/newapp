<?php
// Shared feeding-permission logic, ported once from the near-identical copies
// duplicated in marks-feeding.tsx and attendance-feeding.tsx.

require_once __DIR__ . '/reference.php';
require_once __DIR__ . '/respond.php';

// Server-side enforcement of what the source app only restricted client-side
// (the class dropdown only ever *offered* the teacher's own classes) —
// admin/office may act on any class; other staff only on a class they teach
// (per subjectteacher) or their own homeroom class (sclass).
function has_class_access($mysqli, $sclass) {
    $user = current_user();
    if ($user === null || $user['type'] !== 'staff') {
        return false;
    }
    $ttype = (int) $user['ttype'];
    if ($ttype === 10 || $ttype === 5) {
        return true;
    }
    if ($sclass === $user['sclass']) {
        return true;
    }
    $teacherClasses = get_teacher_classes($mysqli, (int) $user['tid']);
    return in_array($sclass, $teacherClasses, true);
}

// AJAX-context guard: emits a JSON error and exits on failure.
function require_class_access_ajax($mysqli, $sclass) {
    if (current_user() === null) {
        json_error('Not logged in.', 401);
    }
    if (!has_class_access($mysqli, $sclass)) {
        json_error('You do not have access to this class.', 403);
    }
}

// Page-context guard: prints a plain-text denial and exits on failure.
function require_class_access_page($mysqli, $sclass) {
    if (current_user() === null) {
        http_response_code(401);
        die('Not logged in.');
    }
    if (!has_class_access($mysqli, $sclass)) {
        http_response_code(403);
        die('You do not have access to this class.');
    }
}

// controls row with ctype='term' AND allowed=1 -> its cval is the "active" term id.
function get_active_term_id($controls) {
    foreach ($controls as $c) {
        if ($c['ctype'] === 'term' && $c['allowed']) {
            return (int) $c['cval'];
        }
    }
    return null;
}

// controls row with ctype='report' AND allowed=1 -> its cval is the "active" report/exam period.
function get_active_report_id($controls) {
    foreach ($controls as $c) {
        if ($c['ctype'] === 'report' && $c['allowed']) {
            return (int) $c['cval'];
        }
    }
    return null;
}

function roman_to_arabic($roman) {
    $map = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50];
    $result = 0;
    $len = strlen($roman);
    for ($i = 0; $i < $len; $i++) {
        $current = $map[$roman[$i]] ?? 0;
        $next = isset($roman[$i + 1]) ? ($map[$roman[$i + 1]] ?? 0) : 0;
        if ($next && $current < $next) {
            $result -= $current;
        } else {
            $result += $current;
        }
    }
    return $result;
}

// Admin/Office always allowed. Otherwise decodes the class-level from the
// class name's numeric or roman-numeral prefix and checks the matching
// named control (Jr_School_Marks_Feeding / 9_10_Marksfeeding / 11_12_Marksfeeding).
function is_feeding_allowed_for_class($controls, $ttype, $sclassValue) {
    if ($ttype === 10 || $ttype === 5) {
        return true;
    }
    if (!$sclassValue) {
        return true;
    }

    $sclass = strtoupper(explode('-', $sclassValue)[0]);

    if (preg_match('/\d+/', $sclass, $m)) {
        $finalClass = (int) $m[0];
    } else {
        $finalClass = roman_to_arabic(preg_replace('/[^IVX]/', '', $sclass));
    }

    $controlName = '';
    if ($finalClass >= 1 && $finalClass <= 8) {
        $controlName = 'Jr_School_Marks_Feeding';
    } elseif ($finalClass >= 9 && $finalClass <= 10) {
        $controlName = '9_10_Marksfeeding';
    } elseif ($finalClass >= 11 && $finalClass <= 12) {
        $controlName = '11_12_Marksfeeding';
    }

    if ($controlName === '') {
        return true;
    }

    foreach ($controls as $c) {
        if ($c['control'] === $controlName) {
            return (bool) $c['allowed'];
        }
    }
    return false;
}
