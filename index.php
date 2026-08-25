<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

if (is_logged_in()) {
    header('Location: /newapp/' . (is_student() ? 'student_dashboard.php' : 'dashboard.php'));
} else {
    header('Location: /newapp/login.php');
}
exit;
