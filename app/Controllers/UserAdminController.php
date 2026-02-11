<?php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Models\User;
use App\Models\Role;
use App\Models\UserTeam;
use App\Models\Team;
use App\Models\Permission;
use App\Models\TeamPermission;
use App\Services\AuthorizationService;

class UserAdminController
{
    private User $userModel;
    private Role $roleModel;
    private UserTeam $userTeamModel;
    private Team $teamModel;
    private Permission $permissionModel;
    private TeamPermission $teamPermissionModel;
    private AuthorizationService $auth;

    public function __construct()
    {
        $this->userModel = new User();
        $this->roleModel = new Role();
        $this->userTeamModel = new UserTeam();
        $this->teamModel = new Team();
        $this->permissionModel = new Permission();
        $this->teamPermissionModel = new TeamPermission();
        $this->auth = new AuthorizationService();
    }

    /**
     * Exige login e permissão para gerenciar usuários.
     * Retorna false se acesso negado (e já renderiza 403).
     */
    private function requireCanManageUsers(): bool
    {
        SessionManager::requireLogin();

        if (!$this->auth->canManageUsers()) {
            http_response_code(403);
            $title = 'Acesso negado';

            ob_start();
            require __DIR__ . '/../Views/errors/403.php';
            $content = ob_get_clean();

            require __DIR__ . '/../Views/layout/base.php';
            return false;
        }

        return true;
    }

    /**
     * Lista simples de usuários para administração (cargos).
     */
    public function index(): void
    {
        if (!$this->requireCanManageUsers()) {
            return;
        }

        $users = $this->userModel->findAllWithRoles();

        $title = 'Gestão de Usuários';
        ob_start();
        require __DIR__ . '/../Views/access/users_roles_index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Detalhe do usuário: cargos, times e permissões herdadas dos times.
     */
    public function show(int $userId): void
    {
        if (!$this->requireCanManageUsers()) {
            return;
        }

        // Usuário + roles globais
        $user = $this->userModel->findWithRoles($userId);
        if (!$user) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            header('Location: /atlasware/public/admin/users');
            exit;
        }

        // TODOS os roles cadastrados
        $roles = $this->roleModel->findAll();

        // TODOS os usuários ativos (para dropdown de líder)
        $allUsers = $this->userModel->findAllActive();

        // Times do usuário
        $userTeams = $this->userTeamModel->getTeamsByUser($userId);

        // Permissões por time
        $permissionsByTeam = [];
        foreach ($userTeams as $ut) {
            $teamId = $ut['team_id'];
            $teamPerms = $this->teamPermissionModel->getPermissionsByTeam($teamId);
            $permissionsByTeam[$teamId] = $teamPerms;
        }

        // Permissões diretas do usuário (tb_user_permissions)
        $directPermissions = $this->permissionModel->getDirectPermissionsByUser($userId);

        // Permissões herdadas (flat) - todas as permissões únicas dos times
        $userPermissionsFlat = [];
        foreach ($permissionsByTeam as $teamPerms) {
            if (!empty($teamPerms['permissions'])) {
                $userPermissionsFlat = array_merge($userPermissionsFlat, $teamPerms['permissions']);
            }
        }
        $userPermissionsFlat = array_unique($userPermissionsFlat);

        $title = 'Detalhes do Usuário: ' . $user['name'];
        ob_start();
        require __DIR__ . '/../Views/access/user_detail.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Formulário de edição de cargos de um usuário.
     */
    public function editRoles(int $userId): void
    {
        if (!$this->requireCanManageUsers()) {
            return;
        }

        $user = $this->userModel->findWithRoles($userId);
        if (!$user) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            header('Location: /atlasware/public/admin/users');
            exit;
        }

        $roles = $this->roleModel->findAll();

        $title = 'Cargos do Usuário: ' . $user['name'];
        ob_start();
        require __DIR__ . '/../Views/access/user_roles_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Salva os cargos de um usuário.
     */
    public function updateRoles(int $userId): void
    {
        if (!$this->requireCanManageUsers()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /atlasware/public/admin/users/$userId/roles");
            exit;
        }

        $roleIds = $_POST['roles'] ?? [];
        $roleIds = array_map('intval', $roleIds);

        try {
            $this->userModel->setRoles($userId, $roleIds);
            $_SESSION['success'] = 'Cargos do usuário atualizados com sucesso.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao atualizar cargos: ' . $e->getMessage();
        }

        header("Location: /atlasware/public/admin/users/$userId/roles");
        exit;
    }

    /**
     * Retorna JSON com as permissões (colunas da tabela usuarios) e escopos.
     * Segue o padrão de conexão getDbConnection().
     */
    public function getPermissions(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de usuário inválido.']);
            return;
        }

        try {
            // 1. Obtém a conexão usando o padrão do seu projeto
            $db = getDbConnection();

            // 2. Carrega o mapeamento de colunas (Catálogo)
            // Ajuste o caminho se o arquivo estiver em outro local
            $permFile = __DIR__ . '/../Config/permissions_map.php';
            if (!file_exists($permFile)) {
                echo json_encode(['success' => false, 'message' => 'Configuração de permissões não encontrada.']);
                return;
            }
            require_once $permFile;

            // 3. Busca os dados do usuário na tabela 'usuarios'
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado no sistema legado.']);
                return;
            }

            // 4. Processa as permissões baseadas nas colunas (Groups)
            $permissionsOutput = [];
            foreach ($PERMISSIONS_GROUPS as $groupName => $fields) {
                $groupItems = [];
                foreach ($fields as $column => $label) {
                    $val = $user[$column] ?? null;
                    
                    // Lógica de ativação: 1 para int, ou string não vazia e diferente de '0'
                    $isActive = false;
                    if (is_numeric($val)) {
                        $isActive = ((int)$val === 1);
                    } elseif (is_string($val)) {
                        $isActive = ($val !== '' && $val !== '0');
                    }

                    $groupItems[] = [
                        'label' => $label,
                        'active' => $isActive
                    ];
                }
                $permissionsOutput[] = [
                    'group' => $groupName,
                    'items' => $groupItems
                ];
            }

            // 5. Processa os escopos (Obra, Cidade, Nível)
            $scopes = [];
            foreach ($SCOPE_FIELDS as $field => $label) {
                $val = $user[$field] ?? '';
                $scopes[] = [
                    'label' => $label,
                    'value' => is_array($val) ? implode(', ', $val) : $val
                ];
            }

            // 6. Retorno final em JSON
            echo json_encode([
                'success' => true,
                'permissions' => $permissionsOutput,
                'scopes' => $scopes
            ]);

        } catch (\Throwable $e) {
            // Segue o padrão de erro da sua outra função
            echo json_encode(['success' => false, 'message' => 'Erro ao carregar permissões: ' . $e->getMessage()]);
        }
    }

    /**
     * Retorna JSON com todas as permissões e as que o usuário tem diretamente.
     */
    public function getUserPermissionsJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de usuário inválido.']);
            return;
        }

        try {
            $db = getDbConnection();

            // Todas permissões do sistema
            $stmt = $db->prepare("SELECT id, name FROM tb_permissions ORDER BY name ASC");
            $stmt->execute();
            $allPermissions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Permissões diretas do usuário
            $stmt = $db->prepare("SELECT permission_id FROM tb_user_permissions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userPermissions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $userPermissions = array_map('intval', $userPermissions);

            echo json_encode([
                'success' => true,
                'allPermissions' => $allPermissions,
                'userPermissions' => $userPermissions,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao carregar permissões: ' . $e->getMessage()]);
        }
    }

    /**
     * Salva as permissões diretas do usuário (substitui todas).
     */
    public function updateUserPermissionsJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true);

            $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
            $permissions = $payload['permissions'] ?? [];

            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de usuário inválido.']);
                return;
            }

            if (!is_array($permissions)) $permissions = [];

            // Sanitiza IDs
            $permIds = [];
            foreach ($permissions as $p) {
                $pid = (int)$p;
                if ($pid > 0) $permIds[] = $pid;
            }
            $permIds = array_values(array_unique($permIds));

            $db = getDbConnection();
            $db->beginTransaction();

            // Remove todas as permissões diretas atuais
            $stmt = $db->prepare("DELETE FROM tb_user_permissions WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Insere as novas
            if (!empty($permIds)) {
                $stmt = $db->prepare("INSERT INTO tb_user_permissions (user_id, permission_id) VALUES (?, ?)");
                foreach ($permIds as $pid) {
                    $stmt->execute([$userId, $pid]);
                }
            }

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar permissões: ' . $e->getMessage()]);
        }
    }

    /**
     * Atualiza o líder do usuário via formulário.
     */
    public function updateLeaderFromForm(int $userId): void
    {
        if (!$this->requireCanManageUsers()) {
            return;
        }

        $leaderIdRaw = $_POST['id_lider'] ?? '';
        $leaderId = ($leaderIdRaw === '' || $leaderIdRaw === null) ? null : (int)$leaderIdRaw;

        if ($userId <= 0) {
            header('Location: /atlasware/public/admin/users');
            exit;
        }

        // Validação: não pode ser líder de si mesmo
        if ($leaderId !== null && $leaderId === $userId) {
            $_SESSION['error'] = 'Usuário não pode ser líder de si mesmo.';
            header('Location: /atlasware/public/admin/users/' . $userId);
            exit;
        }

        try {
            $db = getDbConnection();
            $stmt = $db->prepare("UPDATE tb_users SET id_lider = ? WHERE id = ?");
            $stmt->execute([$leaderId, $userId]);

            $_SESSION['success'] = 'Líder atualizado com sucesso.';
            header('Location: /atlasware/public/admin/users/' . $userId);
            exit;
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao atualizar líder: ' . $e->getMessage();
            header('Location: /atlasware/public/admin/users/' . $userId);
            exit;
        }
    }
}