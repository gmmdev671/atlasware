# Modelo de Dados - Controle de Acesso

## Tabelas Principais

### 1. `tb_users`
Campos relevantes:
- `id` (PK)
- `name`
- `email`
- `id_lider` (opcional, pode ser usado futuramente para relação chefe-subordinado)
- `status`
- `password_hash`

Relacionamentos:
- N:N com `tb_roles` via `tb_user_roles`
- N:N com `tb_teams` via `tb_user_teams`
- N:N com `tb_permissions` via `tb_user_permissions` (exceções específicas)

---

### 2. `tb_roles`
- `id` (PK)
- `name`
- `level` (inteiro que pode ser usado para ordenar hierarquia)

Exemplos de registros:
- `Master` (nível mais alto)
- `admin`
- `gestor`
- `colaborador`
- `viewer`

Relacionamentos:
- N:N com usuários via `tb_user_roles`
- N:N com permissões via `tb_role_permissions`
- Usado em `tb_user_teams` (role do usuário no time)
- Usado em `tb_team_permissions.allowed_till_role_id` (limite de delegação)

---

### 3. `tb_teams`
- `id` (PK)
- `name`
- `parent_team_id` (FK para `tb_teams.id`)

Permite estruturar times em árvore:
- `Administrativo`
  - `RH`
  - `Financeiro`
- `Comercial`
  - `Inside Sales`
  - `Field Sales`

Relacionamentos:
- N:N com usuários via `tb_user_teams`
- N:N com permissões via `tb_team_permissions`

---

### 4. `tb_permissions`
- `id` (PK)
- `name` (único)

Relacionamentos:
- N:N com roles via `tb_role_permissions`
- N:N com usuários via `tb_user_permissions`
- N:N com times via `tb_team_permissions`

---

## Tabelas de Associação

### 5. `tb_user_roles`
- `user_id` (PK, FK → `tb_users.id`)
- `role_id` (PK, FK → `tb_roles.id`)

Uso:
- Define os **cargos globais** do usuário.
- É a base para posicionar o usuário nas colunas da visão de acesso.

---

### 6. `tb_user_teams`
- `user_id` (PK, FK → `tb_users.id`)
- `team_id` (PK, FK → `tb_teams.id`)
- `role_id` (FK → `tb_roles.id`)

Uso:
- Define a participação do usuário em cada time.
- `role_id` define se ele é, por exemplo, **Gestor** ou **Colaborador** naquele time.

---

### 7. `tb_team_permissions`
- `team_id` (PK, FK → `tb_teams.id`)
- `permission_id` (PK, FK → `tb_permissions.id`)
- `allowed_till_role_id` (FK → `tb_roles.id`, opcional)

Uso:
- Define o **pacote de recursos** associado a um time.
- `allowed_till_role_id` pode ser usado para limitar a delegação:
  - Ex.: recursos do time RH podem descer até o nível `Gerente`.

---

### 8. `tb_user_permissions`
- `user_id` (PK, FK → `tb_users.id`)
- `permission_id` (PK, FK → `tb_permissions.id`)

Uso:
- Exceções pontuais, por usuário, fora das regras de time/role.
- Exemplo: dar acesso específico a um recurso para uma pessoa.

---

### 9. `tb_role_permissions`
- `role_id` (PK, FK → `tb_roles.id`)
- `permission_id` (PK, FK → `tb_permissions.id`)

Uso:
- Permite definir permissões padrão por cargo (role).
- Somado a `tb_team_permissions` e `tb_user_permissions`, compõe a permissão efetiva do usuário.