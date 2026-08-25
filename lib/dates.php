<?php
// Shared date normalization, replacing the ~6 duplicated multi-format parse
// attempts scattered across the source app's flow files.
//
// students.dob is a free-form VARCHAR written in either 'yyyy-MM-dd' or
// 'dd-MM-yyyy'; every read-side flow in the source reformats it to a single
// canonical 'dd-MM-yyyy' for display/comparison. teachers.dob is a real DATE
// column, always formatted as 'dd/MM/yyyy' for display in the source.

// Student/general VARCHAR dob -> canonical 'dd-MM-yyyy', or null if unparseable.
function normalize_dob_display($raw) {
    if ($raw === null || $raw === '') {
        return $raw;
    }
    foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt !== false && $dt->format($format) === $raw) {
            return $dt->format('d-m-Y');
        }
    }
    return $raw;
}

// teachers.dob (MySQL DATE, e.g. '2001-05-03' or null) -> 'dd/MM/yyyy' for display.
function format_teacher_dob($sqlDate) {
    if (!$sqlDate) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $sqlDate);
    return $dt !== false ? $dt->format('d/m/Y') : null;
}

// 'dd/MM/yyyy' form input -> MySQL DATE ('yyyy-MM-dd'), or null if unparseable.
function parse_teacher_dob_input($ddmmyyyy) {
    if (!$ddmmyyyy) {
        return null;
    }
    $dt = DateTime::createFromFormat('d/m/Y', $ddmmyyyy);
    return $dt !== false ? $dt->format('Y-m-d') : null;
}
