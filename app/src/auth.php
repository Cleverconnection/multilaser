<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function authenticate(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, full_name, company_name FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        return $user;
    }

    return null;
}

function login_user(array $user): void
{
    $_SESSION['user'] = $user;
    session_regenerate_id(true);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_regenerate_id(true);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_has_role(string $role): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === $role;
}

function user_has_any_role(array $roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function require_roles(array $roles): void
{
    if (!user_has_any_role($roles)) {
        http_response_code(403);
        echo '<h1>Acesso negado</h1>';
        exit;
    }
}
