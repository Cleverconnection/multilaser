<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/tickets.php';
require_once __DIR__ . '/../src/downloads.php';

$page = $_GET['page'] ?? 'dashboard';

if ($page === 'process_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $user = authenticate($pdo, $username, $password);
    if ($user) {
        login_user($user);
        set_flash('Bem-vindo(a) de volta, ' . ($user['full_name'] ?? $user['username']) . '!');
        header('Location: index.php?page=dashboard');
        exit;
    }

    set_flash('Credenciais inválidas. Tente novamente.', 'error');
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'logout') {
    logout_user();
    set_flash('Sessão encerrada com sucesso.');
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'create_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    if (!user_has_role(ROLE_CLIENT)) {
        http_response_code(403);
        exit('Apenas clientes podem abrir chamados.');
    }

    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'suporte';
    $severity = $_POST['severity'] ?? 'médio';

    if ($subject === '' || $description === '') {
        set_flash('Informe assunto e descrição para abrir o chamado.', 'error');
        header('Location: index.php?page=tickets');
        exit;
    }

    if (!in_array($type, get_ticket_types(), true) || !in_array($severity, get_ticket_severities(), true)) {
        set_flash('Tipo ou severidade inválidos.', 'error');
        header('Location: index.php?page=tickets');
        exit;
    }

    $ticketId = create_ticket($pdo, (int) current_user()['id'], $subject, $description, $type, $severity);
    set_flash('Chamado #' . $ticketId . ' criado com sucesso.');
    header('Location: index.php?page=ticket_detail&id=' . $ticketId);
    exit;
}

if ($page === 'manage_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    if (!user_has_any_role([ROLE_MANAGER, ROLE_ANALYST])) {
        http_response_code(403);
        exit('Sem permissão para atualizar o chamado.');
    }

    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $status = $_POST['status'] ?? null;
    $assignSelf = isset($_POST['assign_self']);

    $ticket = get_ticket($pdo, $ticketId);
    if (!$ticket) {
        set_flash('Chamado não encontrado.', 'error');
        header('Location: index.php?page=tickets');
        exit;
    }

    if ($status && !in_array($status, get_ticket_statuses(), true)) {
        set_flash('Status inválido.', 'error');
        header('Location: index.php?page=tickets');
        exit;
    }

    if ($status) {
        update_ticket_status($pdo, $ticketId, $status);
    }

    if (user_has_role(ROLE_MANAGER)) {
        $assignedTo = $_POST['assigned_to'] ?? '';
        $assignedToId = $assignedTo === '' ? null : (int) $assignedTo;
        assign_ticket($pdo, $ticketId, $assignedToId);
    } elseif (user_has_role(ROLE_ANALYST) && $assignSelf) {
        assign_ticket($pdo, $ticketId, (int) current_user()['id']);
    }

    set_flash('Chamado atualizado.');
    header('Location: index.php?page=tickets');
    exit;
}

if ($page === 'add_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();

    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $status = trim($_POST['status'] ?? '');

    $ticket = get_ticket($pdo, $ticketId);
    if (!$ticket) {
        set_flash('Chamado não encontrado.', 'error');
        header('Location: index.php?page=tickets');
        exit;
    }

    $user = current_user();
    if ($user['role'] === ROLE_CLIENT && (int) $ticket['client_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit('Sem permissão para acessar este chamado.');
    }

    if ($message === '' && $status === '') {
        set_flash('Informe uma mensagem ou atualize o status do chamado.', 'error');
        header('Location: index.php?page=ticket_detail&id=' . $ticketId);
        exit;
    }

    if ($status !== '' && !in_array($status, get_ticket_statuses(), true)) {
        if ($user['role'] === ROLE_CLIENT && $status === 'encerrado') {
            // permitido encerrar pelo cliente
        } else {
            set_flash('Status inválido.', 'error');
            header('Location: index.php?page=ticket_detail&id=' . $ticketId);
            exit;
        }
    }

    if ($user['role'] === ROLE_CLIENT && $status !== '' && $status !== 'encerrado') {
        $status = '';
    }

    add_ticket_update($pdo, $ticketId, (int) $user['id'], $message, $status !== '' ? $status : null);
    set_flash('Atualização registrada.');
    header('Location: index.php?page=ticket_detail&id=' . $ticketId);
    exit;
}

if ($page === 'download_file') {
    require_login();
    $id = (int) ($_GET['id'] ?? 0);
    $file = get_download_file($pdo, $id);

    if (!$file) {
        http_response_code(404);
        exit('Arquivo não encontrado.');
    }

    $fullPath = rtrim($storagePath, '/') . '/' . ltrim($file['file_path'], '/');
    if (!is_file($fullPath)) {
        http_response_code(404);
        exit('Arquivo não disponível.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

$publicPages = ['login'];
if (!is_logged_in() && !in_array($page, $publicPages, true)) {
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'login' && is_logged_in()) {
    header('Location: index.php?page=dashboard');
    exit;
}

require __DIR__ . '/../src/header.php';

$user = current_user();

switch ($page) {
    case 'login':
        ?>
        <section class="card">
            <h2>Acessar central</h2>
            <form method="post" action="index.php?page=process_login" class="form-grid">
                <label>Usuário
                    <input type="text" name="username" required>
                </label>
                <label>Senha
                    <input type="password" name="password" required>
                </label>
                <button type="submit" class="primary">Entrar</button>
            </form>
            <p class="muted">Apenas clientes Multipro, analistas e gestores credenciados.</p>
        </section>
        <?php
        break;

    case 'dashboard':
        require_login();
        if ($user['role'] === ROLE_CLIENT) {
            $metrics = get_client_ticket_metrics($pdo, (int) $user['id']);
            $tickets = array_slice(get_tickets_for_user($pdo, $user), 0, 5);
            ?>
            <section class="card">
                <h2>Resumo dos seus chamados</h2>
                <div class="metrics">
                    <div class="metric"><span>Total</span><strong><?= e((string) $metrics['total']) ?></strong></div>
                    <div class="metric"><span>Abertos</span><strong><?= e((string) $metrics['abertos']) ?></strong></div>
                    <div class="metric"><span>Encerrados</span><strong><?= e((string) $metrics['encerrados']) ?></strong></div>
                    <div class="metric"><span>SLA em risco</span><strong><?= e((string) $metrics['sla_atrasados']) ?></strong></div>
                </div>
            </section>
            <section class="card">
                <h2>Últimos chamados</h2>
                <?php if (empty($tickets)): ?>
                    <p>Você ainda não abriu chamados.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Assunto</th>
                            <th>Status</th>
                            <th>SLA</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr class="<?= is_overdue($ticket['sla_due_at'], $ticket['status']) ? 'overdue' : '' ?>">
                                <td>#<?= e((string) $ticket['id']) ?></td>
                                <td><?= e($ticket['subject']) ?></td>
                                <td><?= e($ticket['status']) ?></td>
                                <td><?= e(format_datetime($ticket['sla_due_at'])) ?></td>
                                <td><a class="button" href="index.php?page=ticket_detail&id=<?= e((string) $ticket['id']) ?>">Detalhes</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            <?php
        } else {
            $metrics = get_ticket_metrics($pdo);
            $tickets = array_slice(get_tickets_for_user($pdo, $user), 0, 10);
            ?>
            <section class="card">
                <h2>Indicadores gerais</h2>
                <div class="metrics">
                    <div class="metric"><span>Total</span><strong><?= e((string) $metrics['total']) ?></strong></div>
                    <div class="metric"><span>Em atendimento</span><strong><?= e((string) $metrics['abertos']) ?></strong></div>
                    <div class="metric"><span>Encerrados</span><strong><?= e((string) $metrics['encerrados']) ?></strong></div>
                    <div class="metric"><span>SLA vencidos</span><strong><?= e((string) $metrics['sla_atrasados']) ?></strong></div>
                </div>
            </section>
            <section class="card">
                <h2>Chamados recentes</h2>
                <?php if (empty($tickets)): ?>
                    <p>Nenhum chamado cadastrado.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Assunto</th>
                            <th>Status</th>
                            <th>Responsável</th>
                            <th>SLA</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr class="<?= is_overdue($ticket['sla_due_at'], $ticket['status']) ? 'overdue' : '' ?>">
                                <td>#<?= e((string) $ticket['id']) ?></td>
                                <td><?= e($ticket['company_name'] ?: $ticket['client_name']) ?></td>
                                <td><?= e($ticket['subject']) ?></td>
                                <td><?= e($ticket['status']) ?></td>
                                <td><?= e($ticket['analyst_name'] ?? 'Não atribuído') ?></td>
                                <td><?= e(format_datetime($ticket['sla_due_at'])) ?></td>
                                <td><a class="button" href="index.php?page=ticket_detail&id=<?= e((string) $ticket['id']) ?>">Detalhes</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
            <?php
        }
        break;

    case 'tickets':
        require_login();
        $tickets = get_tickets_for_user($pdo, $user);
        ?>
        <section class="card">
            <h2>Chamados</h2>
            <?php if (empty($tickets)): ?>
                <p>Nenhum chamado encontrado.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Assunto</th>
                        <?php if ($user['role'] !== ROLE_CLIENT): ?><th>Cliente</th><?php endif; ?>
                        <th>Status</th>
                        <th>Tipo</th>
                        <th>Severidade</th>
                        <th>SLA</th>
                        <?php if ($user['role'] !== ROLE_CLIENT): ?><th>Responsável</th><?php endif; ?>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr class="<?= is_overdue($ticket['sla_due_at'], $ticket['status']) ? 'overdue' : '' ?>">
                            <td>#<?= e((string) $ticket['id']) ?></td>
                            <td><?= e($ticket['subject']) ?></td>
                            <?php if ($user['role'] !== ROLE_CLIENT): ?><td><?= e($ticket['company_name'] ?: $ticket['client_name']) ?></td><?php endif; ?>
                            <td><?= e($ticket['status']) ?></td>
                            <td><?= e(strtoupper($ticket['type'])) ?></td>
                            <td><?= e(ucfirst($ticket['severity'])) ?></td>
                            <td><?= e(format_datetime($ticket['sla_due_at'])) ?></td>
                            <?php if ($user['role'] !== ROLE_CLIENT): ?><td><?= e($ticket['analyst_name'] ?? 'Não atribuído') ?></td><?php endif; ?>
                            <td class="actions">
                                <a class="button" href="index.php?page=ticket_detail&id=<?= e((string) $ticket['id']) ?>">Detalhes</a>
                                <?php if ($user['role'] === ROLE_MANAGER): ?>
                                    <form method="post" action="index.php?page=manage_ticket" class="inline-form">
                                        <input type="hidden" name="ticket_id" value="<?= e((string) $ticket['id']) ?>">
                                        <select name="assigned_to">
                                            <option value="">-- Responsável --</option>
                                            <?php foreach (get_analysts($pdo) as $analyst): ?>
                                                <option value="<?= e((string) $analyst['id']) ?>" <?= (int) ($ticket['assigned_to'] ?? 0) === (int) $analyst['id'] ? 'selected' : '' ?>><?= e($analyst['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="status">
                                            <option value="">-- Status --</option>
                                            <?php foreach (get_ticket_statuses() as $statusOption): ?>
                                                <option value="<?= e($statusOption) ?>" <?= $ticket['status'] === $statusOption ? 'selected' : '' ?>><?= e(ucfirst($statusOption)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Atualizar</button>
                                    </form>
                                <?php elseif ($user['role'] === ROLE_ANALYST): ?>
                                    <form method="post" action="index.php?page=manage_ticket" class="inline-form">
                                        <input type="hidden" name="ticket_id" value="<?= e((string) $ticket['id']) ?>">
                                        <select name="status">
                                            <option value="">-- Status --</option>
                                            <?php foreach (get_ticket_statuses() as $statusOption): ?>
                                                <option value="<?= e($statusOption) ?>" <?= $ticket['status'] === $statusOption ? 'selected' : '' ?>><?= e(ucfirst($statusOption)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (!$ticket['assigned_to']): ?>
                                            <button type="submit" name="assign_self" value="1">Assumir</button>
                                        <?php else: ?>
                                            <button type="submit">Atualizar</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php if ($user['role'] === ROLE_CLIENT): ?>
            <section class="card">
                <h2>Abrir novo chamado</h2>
                <form method="post" action="index.php?page=create_ticket" class="form-grid">
                    <label>Assunto
                        <input type="text" name="subject" required>
                    </label>
                    <label>Descrição
                        <textarea name="description" rows="5" required></textarea>
                    </label>
                    <label>Tipo
                        <select name="type">
                            <?php foreach (get_ticket_types() as $type): ?>
                                <option value="<?= e($type) ?>"><?= e(ucfirst($type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Severidade
                        <select name="severity">
                            <?php foreach (get_ticket_severities() as $severity): ?>
                                <option value="<?= e($severity) ?>"><?= e(ucfirst($severity)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="primary">Abrir chamado</button>
                </form>
            </section>
        <?php endif; ?>
        <?php
        break;

    case 'ticket_detail':
        require_login();
        $ticketId = (int) ($_GET['id'] ?? 0);
        $ticket = get_ticket($pdo, $ticketId);
        if (!$ticket) {
            echo '<p>Chamado não encontrado.</p>';
            break;
        }
        if ($user['role'] === ROLE_CLIENT && (int) $ticket['client_id'] !== (int) $user['id']) {
            echo '<p>Você não tem acesso a este chamado.</p>';
            break;
        }
        $updates = get_ticket_updates($pdo, $ticketId);
        ?>
        <section class="card">
            <h2>Chamado #<?= e((string) $ticket['id']) ?> - <?= e($ticket['subject']) ?></h2>
            <div class="ticket-meta">
                <div>
                    <strong>Cliente:</strong> <?= e($ticket['company_name'] ?: $ticket['client_name']) ?>
                </div>
                <div>
                    <strong>Tipo:</strong> <?= e(strtoupper($ticket['type'])) ?> | <strong>Severidade:</strong> <?= e(ucfirst($ticket['severity'])) ?>
                </div>
                <div>
                    <strong>Status atual:</strong> <?= e($ticket['status']) ?>
                </div>
                <div class="<?= is_overdue($ticket['sla_due_at'], $ticket['status']) ? 'highlight' : '' ?>">
                    <strong>SLA:</strong> <?= e(format_datetime($ticket['sla_due_at'])) ?>
                </div>
                <div>
                    <strong>Responsável:</strong> <?= e($ticket['analyst_name'] ?? 'Não atribuído') ?>
                </div>
            </div>
            <p class="description"><?= nl2br(e($ticket['description'])) ?></p>
        </section>
        <section class="card">
            <h3>Histórico do chamado</h3>
            <?php if (empty($updates)): ?>
                <p>Nenhuma atualização registrada.</p>
            <?php else: ?>
                <ul class="timeline">
                    <?php foreach ($updates as $update): ?>
                        <li>
                            <div class="timestamp"><?= e(format_datetime($update['created_at'])) ?></div>
                            <div><strong><?= e($update['full_name']) ?></strong></div>
                            <?php if ($update['status']): ?>
                                <div class="badge">Status: <?= e($update['status']) ?></div>
                            <?php endif; ?>
                            <p><?= nl2br(e($update['message'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section class="card">
            <h3>Registrar atualização</h3>
            <form method="post" action="index.php?page=add_update" class="form-grid">
                <input type="hidden" name="ticket_id" value="<?= e((string) $ticket['id']) ?>">
                <label>Mensagem
                    <textarea name="message" rows="4" placeholder="Descreva a ação realizada ou resposta ao cliente"></textarea>
                </label>
                <label>Status (opcional)
                    <select name="status">
                        <option value="">Manter atual</option>
                        <?php foreach (get_ticket_statuses() as $statusOption): ?>
                            <option value="<?= e($statusOption) ?>"><?= e(ucfirst($statusOption)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($user['role'] === ROLE_CLIENT): ?>
                    <p class="muted">Clientes somente podem encerrar chamados quando a tratativa foi concluída.</p>
                <?php endif; ?>
                <button type="submit" class="primary">Salvar atualização</button>
            </form>
        </section>
        <?php
        break;

    case 'downloads':
        require_login();
        $files = get_downloadable_files($pdo);
        ?>
        <section class="card">
            <h2>Biblioteca técnica</h2>
            <p>Materiais exclusivos para clientes Multipro com informações de firmware, manuais e procedimentos.</p>
            <?php if (empty($files)): ?>
                <p>Não há materiais cadastrados no momento.</p>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Material</th>
                        <th>Descrição</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td><?= e($file['title']) ?></td>
                            <td><?= e($file['description']) ?></td>
                            <td><a class="button" href="index.php?page=download_file&id=<?= e((string) $file['id']) ?>">Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
        break;

    case 'team':
        require_login();
        require_roles([ROLE_MANAGER]);
        $stmt = $pdo->query("SELECT full_name, role, username, company_name FROM users ORDER BY FIELD(role, 'manager','analyst','client'), full_name");
        $team = $stmt->fetchAll();
        ?>
        <section class="card">
            <h2>Equipe e clientes</h2>
            <table>
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>Perfil</th>
                    <th>Usuário</th>
                    <th>Empresa</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($team as $member): ?>
                    <tr>
                        <td><?= e($member['full_name']) ?></td>
                        <td><?= e(ucfirst($member['role'])) ?></td>
                        <td><?= e($member['username']) ?></td>
                        <td><?= e($member['company_name'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
        break;

    default:
        echo '<p>Página não encontrada.</p>';
        break;
}

require __DIR__ . '/../src/footer.php';
