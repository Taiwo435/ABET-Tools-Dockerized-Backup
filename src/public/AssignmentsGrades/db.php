<?php
// Database connection settings
$host = 'MYSQL_HOSTNAME';
$dbname = 'MYSQL_DATABASE';
$username = 'MYSQL_USER';
$password = 'MYSQL_PASS';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // In production, don't expose full error
    die("Database connection failed.");
}
?>