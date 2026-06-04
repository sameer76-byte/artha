<?php
// ============================================================
//  config/db.php
//  PDO Database Connection — used by every PHP file
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'artha_db');
define('DB_USER', 'root');         // change to your MySQL user
define('DB_PASS', '');             // change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // return arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                    // use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Never expose real error in production — log it instead
    error_log("DB Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}