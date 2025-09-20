<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_downloadable_files(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, title, description, file_path FROM download_files ORDER BY title');
    return $stmt->fetchAll();
}

function get_download_file(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, description, file_path FROM download_files WHERE id = :id');
    $stmt->execute(['id' => $id]);

    $file = $stmt->fetch();

    return $file ?: null;
}
