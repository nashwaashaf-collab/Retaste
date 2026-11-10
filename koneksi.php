<?php
declare(strict_types=1);

$host = getenv('REJEKI_DB_HOST') ?: 'localhost';
$database = getenv('REJEKI_DB_NAME') ?: 'retas22022_rejeki_sore';
$username = getenv('REJEKI_DB_USER') ?: 'retas22022_rejeki_sore';
$password = getenv('REJEKI_DB_PASSWORD') ?: 'rejekisore';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database);

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
