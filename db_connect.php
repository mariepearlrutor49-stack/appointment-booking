<?php
/**
 * db_connect.php
 * ---------------------------------------------------------------
 * Creates the PDO database connection ($pdo) used by every page.
 *
 * It detects automatically whether the site is running on your
 * computer (XAMPP) or on InfinityFree, and uses the right
 * credentials for each.
 *
 * >>> AFTER creating your database on InfinityFree, replace the
 *     values in the "LIVE" block with the ones shown in your
 *     InfinityFree Control Panel -> MySQL Databases. <<<
 */

// Use Philippine time for "today", status-change timestamps, etc.
date_default_timezone_set('Asia/Manila');

// Are we running on localhost (XAMPP)?
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$isLocal    = in_array($serverName, ['localhost', '127.0.0.1', '::1'], true);

if ($isLocal) {
    // ---------- LOCAL (XAMPP) ----------
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', 3307);              // XAMPP MySQL runs on port 3307 on this machine
    define('DB_NAME', 'appointment_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
 
    // ---------- LIVE (InfinityFree) ----------
    define('DB_HOST', 'sql105.infinityfree.com');
    define('DB_PORT', 3306);
    define('DB_NAME', 'if0_43460016_appointment_db');
    define('DB_USER', 'if0_43460016');
    define('DB_PASS', 'secret');
}

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // arrays with column names
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]
    );
    // Keep MySQL's NOW()/CURDATE() in the same timezone as PHP
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // Do not show raw error details to visitors
    http_response_code(500);
    die('Database connection failed. Please check db_connect.php settings.');
}
