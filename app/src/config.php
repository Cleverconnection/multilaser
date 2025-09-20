<?php

declare(strict_types=1);

session_start();

date_default_timezone_set('America/Sao_Paulo');

$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_DATABASE') ?: 'multipro_support';
$dbUser = getenv('DB_USER') ?: 'multipro';
$dbPass = getenv('DB_PASSWORD') ?: 'multipro';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName),
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    echo '<h1>Database connection failed</h1>';
    echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
    exit;
}

const ROLE_MANAGER = 'manager';
const ROLE_ANALYST = 'analyst';
const ROLE_CLIENT = 'client';

$storagePath = getenv('STORAGE_PATH') ?: __DIR__ . '/../storage';
