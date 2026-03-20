<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'working_schema');
define('BASE_URL', 'http://localhost/Anonymous_Student_Feedback_System');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Sync MySQL timezone with Philippine Time
    $pdo->exec("SET time_zone = '+08:00'");

} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}