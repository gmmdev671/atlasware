# Atlasware - Controle de Acesso
## Documentação Técnica e de Arquitetura

---

## 1. Visão Geral do Projeto

### 1.1. Objetivo

O **Atlasware - Controle de Acesso** é um sistema de gerenciamento de permissões hierárquico e baseado em times, desenvolvido para controlar quem pode acessar quais recursos dentro de uma organização.

O sistema implementa:

- **Hierarquia de cargos**: Master > Coordenador > Gerente > Funcionário > Convidado
- **Estrutura de times**: usuários pertencem a times e têm papéis específicos dentro deles
- **Fluxo de solicitação/aprovação**: usuários podem solicitar acesso a recursos de outros times, com aprovação de líderes
- **Auditoria completa**: registro de todas as ações relacionadas a permissões

### 1.2. Tecnologias e Restrições

- **Backend**: PHP 8.x puro (sem frameworks)
- **Banco de dados**: MySQL
- **Frontend**: HTML, CSS, JavaScript básico (possivelmente jQuery para AJAX)
- **Servidor local**: XAMPP (Apache + MySQL + phpMyAdmin)
- **Arquitetura**: Monolítica (frontend e backend no mesmo projeto)
- **Acesso direto ao banco**: via PDO nas camadas Model/Controller

**Restrições importantes**:
- Sem uso de frameworks PHP (Laravel, Symfony, etc.)
- Sem API REST separada para o frontend
- Acesso via `http://localhost/atlasware/public` (sem Virtual Host)

---

## 2. Estrutura de Pastas

```
/atlasware
├── public/                 # Ponto de entrada da aplicação
│   ├── index.php           # Roteador principal
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── .htaccess           # Regras de reescrita de URL
├── app/
│   ├── Config/             # Configurações globais
│   │   ├── bootstrap.php   # Inicialização da aplicação
│   │   ├── env_loader.php  # Carregador customizado de .env
│   │   └── database.php    # Conexão com banco de dados
│   ├── Models/             # Classes para interação com BD
│   │   ├── User.php
│   │   ├── Team.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   └── AccessRequest.php
│   ├── Controllers/        # Lógica de controle
│   │   ├── AuthController.php
│   │   ├── AccessController.php
│   │   └── AdminController.php
│   ├── Views/              # Templates HTML com PHP
│   │   ├── auth/
│   │   │   └── login.php
│   │   ├── layout/
│   │   │   ├── base.php
│   │   │   ├── header.php
│   │   │   └── footer.php
│   │   ├── access/
│   │   │   └── main_access_view.php
│   │   └── errors/
│   │       └── 403.php
│   ├── Services/           # Lógica de negócio
│   │   └── AuthorizationService.php
│   ├── Helpers/            # Funções utilitárias
│   │   └── functions.php
│   └── Core/               # Componentes centrais
│       ├── Database.php
│       └── SessionManager.php
├── vendor/                 # Dependências do Composer
├── composer.json
├── composer.lock
├── .env                    # Variáveis de ambiente
└── .gitignore
```

---

## 3. Modelagem de Dados

### 3.1. Tabelas Principais

#### `tb_users`
Armazena os usuários do sistema.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | int(10) unsigned | PK, auto_increment |
| `old_id` | int(11) | ID do sistema legado (migração) |
| `name` | varchar(100) | Nome completo |
| `login` | varchar(100) | Login único |
| `email` | varchar(255) | E-mail |
| `id_lider` | int(11) | ID do líder direto (hierarquia) |
| `status` | tinyint(1) | Ativo/Inativo (default: 1) |
| `obras` | text | Dados legados |
| `cidades` | text | Dados legados |
| `password_hash` | varchar(512) | Hash da senha |
| `created_at` | timestamp | Data de criação |

**Observações**:
- `id_lider` é usado para determinar subordinados (quem pode gerenciar permissões).
- Integração com tabela legada `usuarios` durante o login (migração automática).

---

#### `tb_roles`
Define os cargos globais do sistema.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | int(10) unsigned | PK, auto_increment |
| `name` | varchar(100) | Nome do cargo (único) |
| `level` | int(11) | Nível hierárquico (menor = mais poder) |

**Cargos atuais**:

| ID | Nome | Level | Descrição |
|----|------|-------|-----------|
| 7 | Master | 1 | Acesso total ao sistema |
| 1 | Coordenador | 25 | Gerencia times e cargos |
| 2 | Gerente | 50 | Gerencia funcionários do time |
| 3 | Funcionário | 75 | Executa tarefas, solicita acessos |
| 4 | Convidado | 99 | Acesso limitado/temporário |

---

#### `tb_permissions`
Define as permissões disponíveis no sistema.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | int(10) unsigned | PK, auto_increment |
| `name` | varchar(100) | Nome da permissão (único) |

**Permissões atuais**:

| ID | Nome | Descrição |
|----|------|-----------|
| 2 | `view_access_overview` | Ver a Visão Geral de Acesso (4 colunas) |
| 3 | `manage_users` | CRUD de usuários (exclusivo do time RH) |
| 4 | `manage_teams` | CRUD de times |
| 5 | `manage_roles` | CRUD de cargos |
| 6 | `manage_permissions` | Ver/atualizar permissões (regra dinâmica) |

---

#### `tb_role_permissions`
Relaciona cargos com permissões (N:N).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `role_id` | int(10) unsigned | FK para `tb_roles` |
| `permission_id` | int(10) unsigned | FK para `tb_permissions` |

**Mapeamento atual**:

```sql
-- view_access_overview (id=2): Master + Coordenador + Gerente
(7, 2), -- Master
(1, 2), -- Coordenador
(2, 2); -- Gerente

-- manage_teams (id=4): Master + Coordenador
(7, 4), -- Master
(1, 4); -- Coordenador

-- manage_roles (id=5): Master + Coordenador
(7, 5), -- Master
(1, 5); -- Coordenador
```

---

#### `tb_teams`
Define os times da organização.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | int(10) unsigned | PK, auto_increment |
| `name` | varchar(100) | Nome do time |

**Exemplos**: Administrativo, Comercial, RH, TI, etc.

---

#### `tb_user_teams`
Relaciona usuários com times e seus papéis dentro do time (N:N).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `user_id` | int(10) unsigned | FK para `tb_users` |
| `team_id` | int(10) unsigned | FK para `tb_teams` |
| `role_id` | int(10) unsigned | FK para `tb_roles` (papel no time) |

**Observação**: O `role_id` aqui representa o **papel do usuário dentro do time**, não o cargo global.

---

#### `tb_user_roles`
Relaciona usuários com seus cargos globais (N:N).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `user_id` | int(10) unsigned | FK para `tb_users` |
| `role_id` | int(10) unsigned | FK para `tb_roles` |

**Observação**: Este é o **cargo global** que define a coluna na Visão Geral de Acesso.

---

#### `tb_user_permissions`
Permissões extras/exceções concedidas diretamente a usuários (N:N).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `user_id` | int(10) unsigned | FK para `tb_users` |
| `permission_id` | int(10) unsigned | FK para `tb_permissions` |

**Uso futuro**: Para acessos temporários/excepcionais a recursos de outros times.

---

### 3.2. Relacionamentos

```
tb_users
  ├─ 1:N → tb_users (id_lider, hierarquia de subordinados)
  ├─ N:N → tb_roles (via tb_user_roles, cargo global)
  ├─ N:N → tb_teams (via tb_user_teams, times + papel no time)
  └─ N:N → tb_permissions (via tb_user_permissions, exceções)

tb_roles
  ├─ N:N → tb_permissions (via tb_role_permissions)
  └─ N:N → tb_users (via tb_user_roles)

tb_teams
  └─ N:N → tb_users (via tb_user_teams)
```

---

## 4. Regras de Negócio e Autorização

### 4.1. Conceitos Fundamentais

#### Cargo Global vs. Papel no Time

- **Cargo Global** (`tb_user_roles`):
  - Define a **posição hierárquica** do usuário na organização.
  - Determina em qual **coluna** ele aparece na Visão Geral de Acesso.
  - Exemplos: Master, Coordenador, Gerente, Funcionário.

- **Papel no Time** (`tb_user_teams.role_id`):
  - Define o **papel específico** do usuário dentro de um time.
  - Pode ser diferente do cargo global.
  - Exemplo: um Coordenador pode ser "Funcionário" no time Administrativo.

#### Prioridade de Cargos

Quando um usuário tem múltiplos cargos globais, a **prioridade** para definir a coluna é:

1. Master
2. Coordenador
3. Gerente
4. Funcionário
5. Convidado

O primeiro cargo encontrado nessa ordem define a coluna.

---

### 4.2. Regras de Permissão por Cargo

#### Quem pode ver a Visão Geral de Acesso?

- **Permitido**: Master, Coordenador, Gerente (e futuramente RH)
- **Negado**: Funcionário, Convidado
- **Permissão**: `view_access_overview`

#### Quem pode gerenciar usuários?

- **Permitido**: Time RH (role específica a ser criada)
- **Permissão**: `manage_users`

#### Quem pode gerenciar times?

- **Permitido**: Master, Coordenador
- **Permissão**: `manage_teams`

#### Quem pode gerenciar cargos?

- **Permitido**: Master, Coordenador
- **Permissão**: `manage_roles`

#### Quem pode ver/atualizar permissões?

- **Regra dinâmica**: Quem possuir subordinados (campo `id_lider` em `tb_users`)
- **Permissão**: `manage_permissions` (não vinculada estaticamente)
- **Lógica**: Implementada no `AuthorizationService::hasSubordinates()`

---

### 4.3. Fluxo de Solicitação/Aprovação (Futuro)

#### Cenário

Um usuário precisa executar uma tarefa em um time diferente do seu.

#### Processo

1. **Solicitação**:
   - Usuário acessa tela "Solicitar Acesso".
   - Escolhe o time alvo e o recurso/permissão necessária.
   - Sistema cria registro em `tb_access_requests` com status `pending`.

2. **Aprovação**:
   - Sistema cria duas linhas em `tb_access_request_approvals`:
     - Para o **líder do time do solicitante**.
     - Para o **líder do time alvo** (dono do recurso).
   - Cada líder vê a solicitação em sua fila.

3. **Concessão**:
   - Quando ambos aprovam, sistema grava exceção em `tb_user_permissions`.
   - Usuário passa a ter acesso temporário/específico ao recurso.

4. **Auditoria**:
   - Todas as ações são registradas em `tb_audit_log`.

#### Tabelas Futuras

- `tb_access_requests`
- `tb_access_request_approvals`
- `tb_audit_log`

---

## 5. Implementação Técnica

### 5.1. Carregamento de Ambiente (.env)

#### Problema Encontrado

A biblioteca `vlucas/phpdotenv` apresentou problemas específicos de ambiente (variáveis não sendo propagadas corretamente).

#### Solução Implementada

**Loader customizado** em `app/Config/env_loader.php`:

```php
<?php
function loadEnvFile(string $path): void {
    if (!file_exists($path)) {
        throw new RuntimeException("Arquivo .env não encontrado: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove aspas
        if (preg_match('/^(["'])(.*)\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
```

**Bootstrap** em `app/Config/bootstrap.php`:

```php
<?php
require_once __DIR__ . '/env_loader.php';

$rootPath = dirname(__DIR__, 2);
$envPath = $rootPath . '/.env';

loadEnvFile($envPath);

// Validação de variáveis obrigatórias
$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'secretJWT'];
foreach ($required as $var) {
    if (getenv($var) === false) {
        throw new RuntimeException("Variável obrigatória {$var} não definida no .env");
    }
}

// Define constantes globais
define('ROOT_PATH', $rootPath);
define('APP_PATH', $rootPath . '/app');
```

**Uso**: Todas as configurações usam `getenv('VARIAVEL')`.

---

### 5.2. Conexão com Banco de Dados

**Arquivo**: `app/Config/database.php`

```php
<?php
function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException("Erro ao conectar ao banco: " . $e->getMessage());
        }
    }

    return $pdo;
}
```

---

### 5.3. Gerenciamento de Sessão

**Arquivo**: `app/Core/SessionManager.php`

```php
<?php
namespace App\Core;

class SessionManager {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set(string $key, $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void {
        self::start();
        session_unset();
        session_destroy();
    }

    public static function requireLogin(): void {
        if (!self::has('user_id')) {
            header('Location: /atlasware/public/login');
            exit;
        }
    }
}
```

---

### 5.4. Model: User

**Arquivo**: `app/Models/User.php`

#### Método: `findAllWithRoles()`

Retorna todos os usuários com seus cargos globais e times.

```php
public function findAllWithRoles(): array {
    $sql = "
        SELECT 
            u.id,
            u.name,
            GROUP_CONCAT(DISTINCT ur.role_id) AS role_ids,
            GROUP_CONCAT(DISTINCT t.name SEPARATOR '||') AS teams_info
        FROM tb_users u
        LEFT JOIN tb_user_roles ur ON ur.user_id = u.id
        LEFT JOIN tb_user_teams ut ON ut.user_id = u.id
        LEFT JOIN tb_teams t ON t.id = ut.team_id
        LEFT JOIN tb_roles r ON r.id = ut.role_id
        GROUP BY u.id, u.name
        ORDER BY u.name
    ";
    $stmt = $this->db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        // Converte IDs de roles em array
        $row['role_ids'] = $row['role_ids']
            ? array_map('intval', explode(',', $row['role_ids']))
            : [];

        // Converte a string de times em um array amigável
        $row['teams'] = $row['teams_info'] 
            ? explode('||', $row['teams_info']) 
            : [];
    }

    return $rows;
}
```

**Retorno**:

```php
[
    [
        'id' => 20,
        'name' => 'SANORTE TESTE',
        'role_ids' => [7],  // Master
        'teams' => ['Administrativo']
    ],
    // ...
]
```

---

### 5.5. Service: AuthorizationService

**Arquivo**: `app/Services/AuthorizationService.php`

#### Métodos Principais

```php
<?php
namespace App\Services;

use App\Models\User;
use App\Core\SessionManager;
use PDO;

class AuthorizationService {
    private User $userModel;
    private PDO $db;

    public function __construct() {
        $this->userModel = new User();
        $this->db = getDbConnection();
    }

    /**
     * Retorna o usuário logado (array) ou null.
     */
    public function getCurrentUser(): ?array {
        $userId = SessionManager::get('user_id');
        if (!$userId) {
            return null;
        }
        return $this->userModel->findById((int)$userId);
    }

    /**
     * Retorna os IDs de roles do usuário.
     */
    public function getCurrentUserRoleIds(): array {
        $user = $this->getCurrentUser();
        if (!$user) {
            return [];
        }
        return $this->userModel->getUserRoleIds((int)$user['id']);
    }

    /**
     * Verifica se o usuário atual tem uma determinada permissão.
     */
    public function hasPermission(string $permissionName): bool {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        $roleIds = $this->getCurrentUserRoleIds();
        if (empty($roleIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

        $sql = "
            SELECT COUNT(*) AS total
            FROM tb_role_permissions rp
            INNER JOIN tb_permissions p ON p.id = rp.permission_id
            WHERE rp.role_id IN ($placeholders)
              AND p.name = ?
        ";

        $stmt = $this->db->prepare($sql);
        $params = array_map('intval', $roleIds);
        $params[] = $permissionName;
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row) && (int)$row['total'] > 0;
    }

    /**
     * Verifica se o usuário atual possui subordinados.
     */
    public function hasSubordinates(): bool {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        $sql = "SELECT COUNT(*) AS total FROM tb_users WHERE id_lider = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row) && (int)$row['total'] > 0;
    }

    // ========== Métodos de Negócio ==========

    public function canViewAccessOverview(): bool {
        return $this->hasPermission('view_access_overview');
    }

    public function canManageUsers(): bool {
        return $this->hasPermission('manage_users');
    }

    public function canManageTeams(): bool {
        return $this->hasPermission('manage_teams');
    }

    public function canManageRoles(): bool {
        return $this->hasPermission('manage_roles');
    }

    public function canManagePermissions(): bool {
        return $this->hasSubordinates();
    }
}
```

---

### 5.6. Controller: AccessController

**Arquivo**: `app/Controllers/AccessController.php`

#### Método: `index()` - Visão Geral de Acesso (4 colunas)

```php
<?php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Models\User;
use App\Models\Role;
use App\Services\AuthorizationService;

class AccessController {
    private User $userModel;
    private Role $roleModel;
    private AuthorizationService $auth;

    public function __construct() {
        $this->userModel = new User();
        $this->roleModel = new Role();
        $this->auth      = new AuthorizationService();
    }

    public function index(): void {
        SessionManager::requireLogin();

        // Verificar permissão
        if (!$this->auth->canViewAccessOverview()) {
            http_response_code(403);
            $title = 'Acesso negado';
            ob_start();
            require __DIR__ . '/../Views/errors/403.php';
            $content = ob_get_clean();
            require __DIR__ . '/../Views/layout/base.php';
            return;
        }

        // 1) Buscar todos os roles
        $roles = $this->roleModel->findAll();

        // Mapeia nomes que vamos usar como "níveis"
        $roleNamesMap = [
            'master'       => ['master'],
            'coordenador'  => ['coordenador'],
            'gerente'      => ['gerente'],
            'funcionario'  => ['funcionário'],
        ];

        // Mapa inverso: role_id => nome legível
        $roleIdToName = [];
        foreach ($roles as $role) {
            $roleIdToName[$role['id']] = $role['name'];
        }

        // Agrupa role_ids por nível
        $roleIdsByLevel = [
            'master'      => [],
            'coordenador' => [],
            'gerente'     => [],
            'funcionario' => [],
        ];

        foreach ($roles as $role) {
            $name = mb_strtolower($role['name']);
            foreach ($roleNamesMap as $level => $patterns) {
                foreach ($patterns as $p) {
                    if (mb_strpos($name, $p) !== false) {
                        $roleIdsByLevel[$level][] = (int)$role['id'];
                        break 2;
                    }
                }
            }
        }

        // 2) Buscar usuários com seus roles
        $allUsers = $this->userModel->findAllWithRoles();

        $columns = [
            'master'      => [],
            'coordenador' => [],
            'gerente'     => [],
            'funcionario' => [],
            'sem_role'    => [],
        ];

        foreach ($allUsers as $user) {
            $userRoleIds = $user['role_ids'] ?? [];

            $placed = false;
            $principalRoleId = null;

            // Prioridade: master > coordenador > gerente > funcionario
            foreach (['master','coordenador','gerente','funcionario'] as $level) {
                if (!empty($roleIdsByLevel[$level])) {
                    $intersection = array_intersect($userRoleIds, $roleIdsByLevel[$level]);
                    if (!empty($intersection)) {
                        $principalRoleId = reset($intersection);
                        $user['principal_role_name'] = $roleIdToName[$principalRoleId] ?? 'Desconhecido';
                        $columns[$level][] = $user;
                        $placed = true;
                        break;
                    }
                }
            }

            if (!$placed) {
                if (!empty($userRoleIds)) {
                    $firstRoleId = reset($userRoleIds);
                    $user['principal_role_name'] = $roleIdToName[$firstRoleId] ?? 'Desconhecido';
                } else {
                    $user['principal_role_name'] = null;
                }
                $columns['sem_role'][] = $user;
            }
        }

        $title = 'Visão Geral de Acesso';
        ob_start();
        require __DIR__ . '/../Views/access/main_access_view.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }
}
```

**Lógica**:

1. Verifica se o usuário tem permissão `view_access_overview`.
2. Carrega todos os cargos e mapeia por nível.
3. Carrega todos os usuários com seus cargos globais e times.
4. Para cada usuário, determina a coluna pela **prioridade de cargo**.
5. Renderiza a view com 4 colunas + área de "sem role mapeada".

---

### 5.7. View: Visão Geral de Acesso

**Arquivo**: `app/Views/access/main_access_view.php`

#### Estrutura

- 4 colunas principais:
  - **Master** (fundo escuro)
  - **Coordenação** (azul)
  - **Gerentes** (amarelo)
  - **Funcionários** (verde)
- Cada card exibe:
  - Nome do usuário
  - ID
  - Badges com os **nomes dos times** (sem o papel)
- Área extra: "Usuários sem role mapeada"

#### Exemplo de Card

```php
<div class="card bg-white border-0 shadow-sm hover-elevate">
    <div class="card-body py-2 px-3">
        <div class="d-flex justify-content-between align-items-start">
            <div class="fw-semibold text-dark">
                <?= htmlspecialchars($user['name']) ?>
            </div>
            <span class="text-muted" style="font-size: 0.7rem;">
                ID: <?= $user['id'] ?>
            </span>
        </div>

        <?php if (!empty($user['teams'])): ?>
            <div class="mt-2 d-flex flex-wrap gap-1">
                <?php foreach ($user['teams'] as $teamInfo): ?>
                    <span class="badge bg-light text-primary border border-primary-subtle"
                          style="font-size: 0.65rem; font-weight: 500;">
                        <?= htmlspecialchars($teamInfo) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
```

---

## 6. Roadmap / Próximos Passos

### 6.1. Bloco A – Políticas de Permissão (Em Andamento)

- [x] Definir permissões iniciais em `tb_permissions`
- [x] Criar mapeamento em `tb_role_permissions`
- [x] Implementar `AuthorizationService` básico
- [ ] Aplicar checks de permissão em todas as controllers relevantes
- [ ] Criar role específica para RH e vincular `manage_users`

### 6.2. Bloco B – CRUDs Administrativos

- [ ] **CRUD de Usuários**:
  - Listar, criar, editar, desativar usuários
  - Atribuir cargos globais
  - Atribuir times e papéis
  - Protegido por `canManageUsers()` (RH)

- [ ] **CRUD de Times**:
  - Listar, criar, editar times
  - Gerenciar membros do time
  - Protegido por `canManageTeams()` (Master/Coordenador)

- [ ] **CRUD de Cargos**:
  - Listar, criar, editar cargos
  - Definir níveis hierárquicos
  - Protegido por `canManageRoles()` (Master/Coordenador)

- [ ] **Gestão de Permissões**:
  - Tela para visualizar/editar permissões de subordinados
  - Protegido por `canManagePermissions()` (quem tem subordinados)

### 6.3. Bloco C – Fluxo de Solicitação/Aprovação

- [ ] Criar tabelas:
  - `tb_access_requests`
  - `tb_access_request_approvals`

- [ ] Implementar telas:
  - Solicitar acesso a recurso de outro time
  - Fila de aprovações para líderes
  - Histórico de solicitações

- [ ] Lógica de aprovação:
  - Notificação aos aprovadores
  - Concessão automática após aprovações
  - Registro em `tb_user_permissions`

### 6.4. Bloco D – Auditoria

- [ ] Criar tabela `tb_audit_log`
- [ ] Implementar helper `AuditLog::record()`
- [ ] Registrar eventos:
  - Concessão/revogação de permissões
  - Aprovação/rejeição de solicitações
  - Mudanças de cargo/time
- [ ] Criar relatórios de auditoria

### 6.5. Bloco E – Relatórios Avançados

- [ ] **"Pessoas externas usando recursos do time X"**:
  - Query cruzando times, permissões e exceções
  - Tela com filtro por time
  - Exibir: usuário, time oficial, recursos acessados, origem do acesso

- [ ] **Dashboard de permissões**:
  - Visão consolidada de acessos por time
  - Gráficos de solicitações pendentes/aprovadas

---

## 7. Decisões Técnicas Importantes

### 7.1. Por que PHP puro sem frameworks?

- Requisito do projeto (restrição técnica).
- Controle total sobre a arquitetura.
- Curva de aprendizado menor para equipe pequena.

### 7.2. Por que loader customizado de .env?

- `vlucas/phpdotenv` apresentou problemas de propagação de variáveis no ambiente específico (XAMPP/Windows).
- Loader customizado garante que variáveis sejam propagadas para `putenv()`, `$_ENV` e `$_SERVER`.

### 7.3. Por que separar "cargo global" de "papel no time"?

- **Cargo global** define a posição hierárquica e permissões gerais.
- **Papel no time** define responsabilidades específicas dentro de um contexto.
- Exemplo: um Coordenador pode ser "Funcionário" em um time específico para executar tarefas operacionais.

### 7.4. Por que usar `id_lider` para subordinados?

- Campo já existente na tabela legada.
- Permite hierarquia direta (1:N).
- Facilita queries de "quem pode gerenciar permissões".

---

## 8. Troubleshooting / Problemas Comuns

### 8.1. Erro 404 ao acessar a aplicação

**Causa**: `.htaccess` não está sendo processado ou `mod_rewrite` desabilitado.

**Solução**:
1. Verificar se `mod_rewrite` está habilitado no Apache.
2. Confirmar `AllowOverride All` no `httpd.conf` para o diretório `htdocs`.
3. Reiniciar Apache.

### 8.2. Variáveis de ambiente não carregadas

**Causa**: Arquivo `.env` não encontrado ou loader não executado.

**Solução**:
1. Verificar se `.env` está na raiz do projeto.
2. Confirmar que `bootstrap.php` está sendo incluído em `public/index.php`.
3. Adicionar debug temporário:
   ```php
   var_dump(getenv('DB_HOST'));
   ```

### 8.3. Usuário não aparece na coluna correta

**Causa**: Mapeamento de cargos em `$roleNamesMap` não bate com os nomes em `tb_roles`.

**Solução**:
1. Verificar nomes exatos em `tb_roles` (atenção a maiúsculas/minúsculas e acentos).
2. Ajustar `$roleNamesMap` no `AccessController`.
3. Usar `mb_strtolower()` para comparações case-insensitive.

### 8.4. Badge mostrando "(Funcionário)" em vez de só o nome do time

**Causa**: Query em `User::findAllWithRoles()` concatenando `t.name` com `r.name`.

**Solução**:
Trocar:
```php
GROUP_CONCAT(DISTINCT CONCAT(t.name, ' (', r.name, ')') SEPARATOR '||') AS teams_info
```

Por:
```php
GROUP_CONCAT(DISTINCT t.name SEPARATOR '||') AS teams_info
```

---

## 9. Referências e Recursos

### 9.1. Documentação Oficial

- [PHP Manual](https://www.php.net/manual/pt_BR/)
- [PDO Documentation](https://www.php.net/manual/pt_BR/book.pdo.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)

### 9.2. Padrões de Código

- PSR-4: Autoloading (se usar Composer)
- PSR-12: Coding Style Guide

### 9.3. Segurança

- Sempre usar **prepared statements** (PDO) para prevenir SQL Injection.
- Hash de senhas com `password_hash()` e verificação com `password_verify()`.
- Sanitização de inputs com `htmlspecialchars()` nas views.
- Validação de permissões em **todas** as ações sensíveis.

---

## 10. Contatos e Suporte

Para dúvidas ou sugestões sobre este projeto, entre em contato com a equipe de desenvolvimento.

---

**Última atualização**: 2026-01-05  
**Versão do documento**: 1.0
