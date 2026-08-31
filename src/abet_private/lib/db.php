<?php
declare(strict_types=1);


/**
 * Returns a PDO instance connected to the database. Uses a static variable to ensure only one connection is created (singleton pattern).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . "127.0.0.1" . ';dbname=' . getenv('MYSQL_DATABASE') . ';charset=utf8mb4';

    $pdo = new PDO($dsn, getenv('MYSQL_USER'), getenv('MYSQL_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
