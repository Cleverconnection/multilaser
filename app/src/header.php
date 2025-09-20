<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Multipro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <h1>Central Multipro</h1>
        <span class="subtitle">Suporte técnico, RMA e Garantia</span>
    </div>
    <?php if ($user): ?>
        <div class="user-info">
            <span><?= e($user['full_name'] ?? $user['username']) ?> (<?= e($user['role']) ?>)</span>
            <a class="button" href="index.php?page=logout">Sair</a>
        </div>
    <?php endif; ?>
</header>
<?php if ($user): ?>
<nav class="main-nav">
    <a href="index.php?page=dashboard">Dashboard</a>
    <a href="index.php?page=tickets">Chamados</a>
    <a href="index.php?page=downloads">Downloads técnicos</a>
    <?php if ($user['role'] === ROLE_MANAGER): ?>
        <a href="index.php?page=team">Equipe</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<main class="container">
    <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>
