<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

$userId = $_SESSION['user_id'] ?? null;
$name   = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');

if ($userId) {
    $check = $pdo->prepare("SELECT user_id FROM users WHERE user_id=?");
    $check->execute([$userId]);
    if ($check->fetch()) {
        logActivity($pdo, 'LOGOUT', trim($name) . ' (student) logged out', $userId);
    }
}

session_destroy();
redirect(BASE_URL . '/app/auth/student-login.php');