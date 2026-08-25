<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

if (is_logged_in()) {
    header('Location: /firebase_to_php/' . (is_student() ? 'student_dashboard.php' : 'dashboard.php'));
} else {
    header('Location: /firebase_to_php/login.php');
}
exit;
