<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_datetime(?string $dateTime): string
{
    if (!$dateTime) {
        return '-';
    }

    $date = new DateTime($dateTime);
    return $date->format('d/m/Y H:i');
}

function calculate_sla_due(string $severity): DateTime
{
    $base = new DateTimeImmutable();
    return match ($severity) {
        'crítico' => new DateTime($base->modify('+4 hours')->format(DateTime::ATOM)),
        'alto' => new DateTime($base->modify('+24 hours')->format(DateTime::ATOM)),
        'médio' => new DateTime($base->modify('+48 hours')->format(DateTime::ATOM)),
        default => new DateTime($base->modify('+72 hours')->format(DateTime::ATOM)),
    };
}

function is_overdue(?string $dueDate, ?string $status = null): bool
{
    if (!$dueDate) {
        return false;
    }

    if (in_array($status, ['resolvido', 'encerrado'], true)) {
        return false;
    }

    return (new DateTime($dueDate)) < new DateTime();
}

function set_flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}
