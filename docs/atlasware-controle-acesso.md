### Atlasware — Controle de Acesso (Documentação Consolidada)

#### 1) Visão geral
O Atlasware é um sistema monolítico em PHP puro (sem framework) para **controle de acesso hierárquico e baseado em times**, com:

- Hierarquia de cargos: Master > Coordenador > Gerente > Funcionário > Convidado
- Times e associação de usuários a times com papel/cargo dentro do time
- Fluxo de solicitação/aprovação de acessos (join em time, mudança de cargo global etc.)
- Auditoria completa de ações sensíveis (solicitações e concessões/revogação)

URL local padrão:
- http://localhost/atlasware/public

Restrições:
- Sem frameworks PHP
- Sem API REST separada (monólito: controllers renderizam views)
- Acesso ao BD via PDO direto nas camadas Model/Controller


#### 2) Stack / Ambiente
- PHP 8.x
- MySQL (phpMyAdmin no XAMPP)
- HTML/CSS/JS (Bootstrap usado nas views)
- Estrutura monolítica (public/ como entrypoint)

Bootstrap e utilitários importantes:
- Loader customizado de `.env` (`app/Config/env_loader.php`)
- Conexão PDO (`app/Config/database.php` via `getDbConnection()`)
- Sessão (`app/Core/SessionManager.php`)


#### 3) Estrutura de pastas (alto nível)
- public/
  - index.php (roteador principal)
  - css/ (ex: base.css, access.css, error.css, login.css, + audit.css)
- app/
  - Controllers/
  - Models/
  - Services/
  - Views/
  - Core/
  - Config/


#### 4) Modelagem de dados (tabelas base)
Tabelas já existentes/descritas:

- tb_users
  - usuários e dados legados; inclui `id_lider` para hierarquia (subordinados)
- tb_roles
  - cargos globais (Master/Coordenador/Gerente/Funcionário/Convidado)
  - inclui `level` (menor = mais poder)
- tb_permissions
  - catálogo de permissões (strings, ex: manage_teams)
- tb_role_permissions
  - N:N entre cargos globais e permissões
- tb_teams
  - times organizacionais
- tb_user_roles
  - N:N usuários x cargos globais
- tb_user_teams
  - usuários x times com role_id (papel/cargo do usuário dentro do time)
- tb_user_permissions
  - exceções/override por usuário (uso futuro)


#### 5) Conceito-chave: “Permissão + Escopo (cargo + time)”
O sistema não é só “tem permissão ou não”; é também **onde** ela vale.

Regras práticas acordadas:
- Master/Coordenador: acesso amplo (cross-times)
- Gerente: gestão **limitada ao(s) time(s)** onde ele é membro (escopo por time)
- Funcionário/Convidado: acesso limitado (tipicamente sem gestão)

Implementação:
- `AuthorizationService` concentra checks:
  - Permissões globais via `tb_role_permissions`
  - Regras dinâmicas (ex: tem subordinados)
  - (Quando aplicado) regras de escopo por time: “pode gerir X neste teamId?”

Obs.: alguns métodos usam `userId` como parâmetro (ex: `isMasterOrCoordinator(int $userId)`), então controllers/views devem obter o id do usuário logado via SessionManager com segurança.


#### 6) Módulos principais (implementados)
##### 6.1 Autenticação / Sessão
- Login baseado em sessão (`SessionManager::requireLogin()` protege rotas sensíveis)

##### 6.2 Visão Geral de Acesso (4 colunas)
- Rota típica: `/admin/access`
- Usa `AuthorizationService::canViewAccessOverview()`
- Agrupa usuários pela prioridade do cargo global (Master > Coordenador > Gerente > Funcionário)

##### 6.3 Times (Admin)
- CRUD e gestão avançada (dependendo do estado atual do projeto):
  - times, permissões por time, membros do time
- Proteção via `AuthorizationService` (ex: `canManageTeams()` e/ou checks por escopo)

##### 6.4 Cargos (Admin)
- CRUD de roles
- Proteção via `canManageRoles()`

##### 6.5 Solicitações de Acesso (Requests)
Rotas:
- `/requests` — lista solicitações do usuário
- `/requests/create` — formulário nova solicitação
- `/admin/requests` — fila administrativa de pendências (Master/Coordenador)
- `/admin/requests/{id}/process` — aprovar/rejeitar (POST)

Controller:
- `AccessRequestController`
  - `index()` lista do usuário
  - `create()` carrega times/roles para selects
  - `store()` cria solicitação
  - `adminIndex()` lista pendentes (restrito)
  - `process($id)` aprova/rejeita + aplica mudança
  - `applyAccessChange($requestId)` aplica:
    - `team_join` => add/update em `tb_user_teams`
    - `role_change` => atualiza cargo global (ex: `tb_user_roles` via `User::setRoles()`)

Tipos atuais suportados:
- `team_join` (entrar em time com role_id dentro do time)
- `role_change` (mudança de cargo global)


#### 7) Auditoria (Audit Logs)
Objetivo:
- Registrar eventos sensíveis: criação de solicitação, decisão (aprovar/rejeitar), e aplicação da mudança (concessão efetiva).

Rotas:
- `/admin/audit` — lista logs (restrito a Master/Coordenador)
- `/admin/audit/{id}` — detalhe do log (opcional, se implementado)

Controller:
- `AuditController`
  - `index()` lista logs recentes
  - `show($id)` (opcional) mostra detalhes

Model:
- `AuditLog`
  - `log(action, table_name, record_id, old_values, new_values)`
  - `findAll(limit)`
  - `findById(id)` (opcional)

Tabela (implementada no projeto):
- `tb_audit_logs`
  - `user_id` (quem executou)
  - `action` (ex: ACCESS_REQUEST_APPROVE)
  - `table_name`, `record_id`
  - `old_values` (JSON), `new_values` (JSON)
  - `ip_address`, `user_agent`
  - `created_at`

Exemplos de actions usados:
- `ACCESS_REQUEST_CREATE`
- `ACCESS_REQUEST_APPROVE`
- `ACCESS_REQUEST_REJECT`
- `ACCESS_APPLY_TEAM_JOIN`
- `ACCESS_APPLY_ROLE_CHANGE`
- `ACCESS_APPLY_FAILED`


#### 8) UI / CSS (Auditoria)
Foi criado um CSS dedicado para a tela de auditoria:
- `public/css/audit.css`

Objetivo:
- Melhorar contraste do cabeçalho no fundo azul
- Personalizar botão “Atualizar”
- Arredondar cantos e melhorar sombra do card central (`.audit-card`)


#### 9) Rotas principais (resumo)
- Público:
  - `/login`, `/logout`
- Usuário logado:
  - `/dashboard`
  - `/requests`, `/requests/create`
- Admin:
  - `/admin/access`
  - `/admin/teams`
  - `/admin/roles`
  - `/admin/users` (quando estiver concluído)
  - `/admin/requests`
  - `/admin/audit`


#### 10) Checklist de testes (rápido)
1) Login como Master/Coordenador
- Ver “Controle de Acesso” na navbar
- Acessar `/admin/requests` (ver pendentes)
- Aprovar/Rejeitar uma solicitação
- Acessar `/admin/audit` e confirmar que os logs aparecem

2) Login como Funcionário
- Não ver links administrativos
- Não acessar `/admin/requests` nem `/admin/audit` (redirect + mensagem de erro)

3) Testar aplicação de acesso
- Aprovar `team_join` e conferir em `tb_user_teams`
- Aprovar `role_change` e conferir em `tb_user_roles`


#### 11) Roadmap (alto nível)
- CRUD completo de Usuários com escopo por time (RH/global)
- Gestão de permissões por cargo (UI para tb_role_permissions)
- Regras de escopo refinadas no AuthorizationService (ex: canManageXInTeam(teamId))
- Relatórios avançados (acessos cross-team, exceções, etc.)
- Melhorias de UI globais e padronização de CSS