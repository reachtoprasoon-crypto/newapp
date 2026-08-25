<?php
require_once __DIR__ . '/config.php';

$_SESSION = [];
session_destroy();

header('Location: /firebase_to_php/login.php');
exit;
