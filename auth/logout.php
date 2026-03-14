<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    $name = ($_SESSION['first_name'] ?? 'User') . ' ' . ($_SESSION['last_name'] ?? '');
    logActivity($pdo, $_SESSION['user_id'], 'LOGOUT', sanitize($name) . ' logged out');
} elseif (isset($_SESSION['oauth_user_id'])) {
    logActivity($pdo, $_SESSION['oauth_user_id'], 'LOGOUT', ($_SESSION['oauth_name'] ?? 'Student') . ' logged out');
}

// Clear all session data
session_unset();
session_destroy();

redirect(BASE_URL . '/index.php');