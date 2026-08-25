<?php
// Session-based auth. $_SESSION['auth'] shape:
//   staff:   ['type' => 'staff',   'tid', 'tname', 'ttype', 'sclass']
//   student: ['type' => 'student', 'sid', 'schno', 'sname', 'sclass', 'photo']

function current_user() {
    return $_SESSION['auth'] ?? null;
}

function is_logged_in() {
    return current_user() !== null;
}

function is_student() {
    $user = current_user();
    return $user !== null && $user['type'] === 'student';
}

function is_staff() {
    $user = current_user();
    return $user !== null && $user['type'] === 'staff';
}

// Page-context guard: redirects to login on failure. Call at the top of dashboard pages.
function require_login_page() {
    if (!is_logged_in()) {
        header('Location: /firebase_to_php/login.php');
        exit;
    }
}

function require_staff_role_page($allowedTtypes) {
    require_login_page();
    $user = current_user();
    if ($user['type'] !== 'staff' || !in_array((int) $user['ttype'], $allowedTtypes, true)) {
        header('Location: /firebase_to_php/login.php');
        exit;
    }
}

// AJAX-context guards: return a JSON error instead of redirecting. Call at the
// top of every api/*.php endpoint that needs a logged-in user.
function require_login_ajax() {
    if (!is_logged_in()) {
        json_error('Not logged in.', 401);
    }
}

function require_staff_role_ajax($allowedTtypes) {
    require_login_ajax();
    $user = current_user();
    if ($user['type'] !== 'staff' || !in_array((int) $user['ttype'], $allowedTtypes, true)) {
        json_error('You do not have permission to perform this action.', 403);
    }
}
