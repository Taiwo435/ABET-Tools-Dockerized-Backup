<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__."../../docker");
$dotenv->load();

return [
    'dbname' => getenv('MYSQL_DATABASE'),
    'user' => getenv('MYSQL_USER'),
    'password' => getenv('MYSQL_PASS'),
    'host' => getenv('MYSQL_HOSTNAME'),
    'driver' => 'pdo_mysql',
];
