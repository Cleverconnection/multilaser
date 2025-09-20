<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function create_ticket(PDO $pdo, int $clientId, string $subject, string $description, string $type, string $severity): int
{
    $dueDate = calculate_sla_due($severity)->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO tickets (client_id, subject, description, type, severity, sla_due_at, status)
         VALUES (:client_id, :subject, :description, :type, :severity, :sla_due_at, :status)'
    );

    $stmt->execute([
        'client_id' => $clientId,
        'subject' => $subject,
        'description' => $description,
        'type' => $type,
        'severity' => $severity,
        'sla_due_at' => $dueDate,
        'status' => 'aberto',
    ]);

    return (int) $pdo->lastInsertId();
}

function get_tickets_for_user(PDO $pdo, array $user): array
{
    if ($user['role'] === ROLE_CLIENT) {
        $stmt = $pdo->prepare('SELECT t.*, u.full_name AS client_name, a.full_name AS analyst_name
            FROM tickets t
            JOIN users u ON t.client_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE t.client_id = :client_id
            ORDER BY t.created_at DESC');
        $stmt->execute(['client_id' => $user['id']]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->query('SELECT t.*, uc.full_name AS client_name, uc.company_name, ua.full_name AS analyst_name
        FROM tickets t
        JOIN users uc ON t.client_id = uc.id
        LEFT JOIN users ua ON t.assigned_to = ua.id
        ORDER BY t.created_at DESC');

    return $stmt->fetchAll();
}

function get_ticket(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare('SELECT t.*, uc.full_name AS client_name, uc.company_name, ua.full_name AS analyst_name
        FROM tickets t
        JOIN users uc ON t.client_id = uc.id
        LEFT JOIN users ua ON t.assigned_to = ua.id
        WHERE t.id = :id');
    $stmt->execute(['id' => $ticketId]);

    $ticket = $stmt->fetch();

    return $ticket ?: null;
}

function get_ticket_updates(PDO $pdo, int $ticketId): array
{
    $stmt = $pdo->prepare('SELECT tu.*, u.full_name
        FROM ticket_updates tu
        JOIN users u ON tu.user_id = u.id
        WHERE tu.ticket_id = :ticket_id
        ORDER BY tu.created_at ASC');
    $stmt->execute(['ticket_id' => $ticketId]);

    return $stmt->fetchAll();
}

function add_ticket_update(PDO $pdo, int $ticketId, int $userId, string $message, ?string $status = null): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('INSERT INTO ticket_updates (ticket_id, user_id, message, status)
            VALUES (:ticket_id, :user_id, :message, :status)');
        $stmt->execute([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message' => $message,
            'status' => $status,
        ]);

        if ($status !== null && $status !== '') {
            $updateTicket = $pdo->prepare('UPDATE tickets SET status = :status WHERE id = :id');
            $updateTicket->execute([
                'status' => $status,
                'id' => $ticketId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }
}

function assign_ticket(PDO $pdo, int $ticketId, ?int $analystId): void
{
    $stmt = $pdo->prepare('UPDATE tickets SET assigned_to = :assigned_to WHERE id = :id');
    $stmt->execute([
        'assigned_to' => $analystId,
        'id' => $ticketId,
    ]);
}

function update_ticket_status(PDO $pdo, int $ticketId, string $status): void
{
    $stmt = $pdo->prepare('UPDATE tickets SET status = :status WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'id' => $ticketId,
    ]);
}

function get_ticket_statuses(): array
{
    return ['aberto', 'em andamento', 'aguardando cliente', 'resolvido', 'encerrado'];
}

function get_ticket_types(): array
{
    return ['suporte', 'rma', 'garantia'];
}

function get_ticket_severities(): array
{
    return ['crítico', 'alto', 'médio', 'baixo'];
}

function get_analysts(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE role = :role ORDER BY full_name');
    $stmt->execute(['role' => ROLE_ANALYST]);

    return $stmt->fetchAll();
}

function get_ticket_metrics(PDO $pdo): array
{
    $metrics = [];

    $metrics['total'] = (int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    $metrics['abertos'] = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('aberto','em andamento','aguardando cliente')")->fetchColumn();
    $metrics['encerrados'] = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('resolvido','encerrado')")->fetchColumn();
    $metrics['sla_atrasados'] = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE sla_due_at < NOW() AND status NOT IN ('resolvido','encerrado')")->fetchColumn();

    return $metrics;
}

function get_client_ticket_metrics(PDO $pdo, int $clientId): array
{
    $metrics = [];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE client_id = :client_id');
    $stmt->execute(['client_id' => $clientId]);
    $metrics['total'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE client_id = :client_id AND status IN ('aberto','em andamento','aguardando cliente')");
    $stmt->execute(['client_id' => $clientId]);
    $metrics['abertos'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE client_id = :client_id AND status IN ('resolvido','encerrado')");
    $stmt->execute(['client_id' => $clientId]);
    $metrics['encerrados'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE client_id = :client_id AND sla_due_at < NOW() AND status NOT IN ('resolvido','encerrado')");
    $stmt->execute(['client_id' => $clientId]);
    $metrics['sla_atrasados'] = (int) $stmt->fetchColumn();

    return $metrics;
}
