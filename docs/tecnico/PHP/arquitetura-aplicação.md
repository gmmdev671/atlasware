# Arquitetura da Aplicação (PHP Puro)

## Estrutura de Pastas

- `public/`
  - `index.php` → roteador principal
  - `css/`, `js/`, `.htaccess`

- `app/`
  - `Config/`
    - `database.php` → `getDbConnection()`
    - `env_loader.php`, `bootstrap.php`
  - `Core/`
    - `Database.php` (se usado)
    - `SessionManager.php`
  - `Models/`
    - `User.php`
    - `Role.php`
    - `Permission.php`
    - `Team.php`
    - `TeamPermission.php`
    - `UserTeam.php`
    - (futuro) `AuditLog.php`
  - `Controllers/`
    - `AuthController.php`
    - `DashboardController.php`
    - `TeamController.php`
    - `AccessController.php`
    - `UserAdminController.php` (gestão de cargos)
  - `Views/`
    - `layout/base.php`
    - `auth/login.php`
    - `access/main_access_view.php`
    - `access/teams_index.php`
    - `access/team_form.php`
    - `access/team_permissions.php`
    - `access/team_members.php`
    - `access/users_roles_index.php`
    - `access/user_roles_form.php`

## Fluxo de Requisição (Exemplo)

1. Navegador chama `/atlasware/public/admin/access`.
2. `public/index.php` interpreta `$path` e cria `AccessController`.
3. `AccessController::index()`:
   - Garante login (via `SessionManager::requireLogin()`).
   - Usa `Role` + `User` para buscar dados.
   - Monta o array `$columns` para a view.
4. Carrega `app/Views/access/main_access_view.php` dentro de `layout/base.php`.

## Sessão e Autenticação
- `SessionManager::login($userId, $name, ...)`
- `SessionManager::requireLogin()` em toda rota protegida.
- Usuário logado é armazenado em `$_SESSION['user']`.

## Organização de Regras de Negócio

- **Regras de autenticação:** em `AuthController` + `User` (login, senha, etc.).
- **Regras de times e membros:** em `Team`, `UserTeam`, `TeamPermission`.
- **Regras de hierarquia e visão geral:** em `AccessController` (montagem das 4 colunas).