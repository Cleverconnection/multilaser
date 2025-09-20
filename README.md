# Central de suporte Multipro

Aplicação web simples, desenvolvida em PHP, para controle de chamados técnicos, processos de RMA, garantia e distribuição de materiais técnicos para ISPs clientes da Multipro.

## Arquitetura

- **PHP 8.2 + Apache** (frontend + backend) com autenticação por sessão e controle de permissões.
- **MariaDB 11** como banco de dados relacional para usuários, chamados, históricos e materiais.
- Deploy utilizando **Docker Compose**, facilitando a execução local ou em servidores on-premise.

## Funcionalidades

- Abertura e acompanhamento de chamados pelos clientes (suporte, RMA, garantia) com cálculo automático de SLA por severidade.
- Painel de gestores e analistas com indicadores, distribuição de chamados, atribuição de responsáveis e atualização de status.
- Registro de interações no chamado (linha do tempo) com comentários e alterações de status.
- Biblioteca de downloads com materiais técnicos protegidos por autenticação.
- Perfis de acesso: gerente/supervisor, analista/atendente e cliente.

## Requisitos

- Docker e Docker Compose instalados.

## Como executar

```bash
docker compose up --build
```

O serviço web ficará disponível em `http://localhost:8080`.

## Credenciais de exemplo

| Perfil | Usuário | Senha | Observações |
| --- | --- | --- | --- |
| Gerente | `gestor` | `multipro123` | Pode ver todos os chamados, atribuir responsáveis e acessar a visão de equipe |
| Analista | `analista` | `multipro123` | Pode assumir chamados, atualizar status e registrar interações |
| Cliente | `cliente` | `multipro123` | Pode abrir e acompanhar chamados do seu ISP |
| Cliente 2 | `cliente2` | `multipro123` | Outro ISP cadastrado para testes |

## Estrutura dos serviços

- `app/` contém o código PHP, assets e Dockerfile do serviço web.
- `db/init.sql` possui a estrutura inicial do banco e dados de exemplo.
- `app/storage/downloads/` armazena os arquivos disponíveis para download (montado no container em `/var/www/storage`).

## Fluxo básico

1. Faça login com um dos usuários acima.
2. Clientes podem abrir chamados em **Chamados → Abrir novo chamado**.
3. Analistas e gestores acompanham e atualizam chamados pela mesma tela ou visualizam detalhes em **Chamados → Detalhes**.
4. Materiais técnicos ficam em **Downloads técnicos** (visível após login).

## Próximos passos sugeridos

- Implementar notificações por e-mail e SLA configurável por cliente.
- Permitir anexos em chamados e personalizar formulários por tipo (suporte, RMA, garantia).
- Adicionar dashboards com gráficos e filtros avançados.
