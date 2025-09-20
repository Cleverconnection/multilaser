CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('manager','analyst','client') NOT NULL,
    company_name VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    subject VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('suporte','rma','garantia') NOT NULL DEFAULT 'suporte',
    severity ENUM('crítico','alto','médio','baixo') NOT NULL DEFAULT 'médio',
    status VARCHAR(30) NOT NULL DEFAULT 'aberto',
    sla_due_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_client FOREIGN KEY (client_id) REFERENCES users(id),
    CONSTRAINT fk_tickets_analyst FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(30) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_updates_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_updates_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE download_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password_hash, full_name, role, company_name) VALUES
('gestor', '$2y$12$.0uvoDFdNm0Q7k0NaFg6KupVJMQwC6GwJxUUjpDP.GFmq99hoNNbi', 'Ana Souza', 'manager', NULL),
('analista', '$2y$12$.0uvoDFdNm0Q7k0NaFg6KupVJMQwC6GwJxUUjpDP.GFmq99hoNNbi', 'Bruno Lima', 'analyst', NULL),
('cliente', '$2y$12$.0uvoDFdNm0Q7k0NaFg6KupVJMQwC6GwJxUUjpDP.GFmq99hoNNbi', 'ISP Conecta', 'client', 'ConectaNet'),
('cliente2', '$2y$12$.0uvoDFdNm0Q7k0NaFg6KupVJMQwC6GwJxUUjpDP.GFmq99hoNNbi', 'Fibra Total', 'client', 'Fibra Total Telecom');

INSERT INTO tickets (client_id, assigned_to, subject, description, type, severity, status, sla_due_at)
VALUES
(3, 2, 'Intermitência em OLT GPON', 'Clientes da região centro estão com quedas recorrentes. Precisamos de análise dos logs.', 'suporte', 'alto', 'em andamento', DATE_ADD(NOW(), INTERVAL 12 HOUR)),
(3, NULL, 'Solicitação de RMA ONU modelo X', 'ONU apresenta falha de energia após 3 meses de uso. Solicito processo de RMA.', 'rma', 'médio', 'aberto', DATE_ADD(NOW(), INTERVAL 48 HOUR)),
(4, 2, 'Garantia - Módulo DWDM 100G', 'Módulo apresenta perda óptica acima do esperado. Verificar elegibilidade de garantia.', 'garantia', 'crítico', 'em andamento', DATE_ADD(NOW(), INTERVAL 4 HOUR));

INSERT INTO ticket_updates (ticket_id, user_id, message, status) VALUES
(1, 2, 'Análise inicial realizada. Coletados logs da OLT.', 'em andamento'),
(1, 3, 'Enviei arquivos adicionais via e-mail.', NULL),
(3, 2, 'Teste de bancada agendado para hoje às 16h.', 'em andamento');

INSERT INTO download_files (title, description, file_path) VALUES
('Guia de atualização GPON', 'Procedimento oficial para atualização de firmware das OLTs e ONUs.', 'downloads/firmware_update.txt');
