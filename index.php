<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/function.php';

// Route based on role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin')   redirect(BASE_URL . '/app/admin/dashboard.php');
    if ($_SESSION['role'] === 'manager') redirect(BASE_URL . '/app/manager/dashboard.php');
    if ($_SESSION['role'] === 'student') redirect(BASE_URL . '/app/user/index.php');
}

// Not logged in — send to student login by default
redirect(BASE_URL . '/app/auth/login.php');