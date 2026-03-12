<?php
// app/Controllers/AccessController.php
namespace App\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Team;
use App\Models\Permission;
use App\Services\AuthorizationService;
use App\Core\SessionManager;

class AccessController
{
    private User $userModel;
    private Role $roleModel;
    private Team $teamModel;
    private Permission $permissionModel;
    private AuthorizationService $auth;

    public function __construct()
    {
        $this->userModel = new User();
        $this->roleModel = new Role();
        $this->teamModel = new Team();
        $this->permissionModel = new Permission();
        $this->auth      = new AuthorizationService();
    }

    /**
     * Tela principal: Visão Geral de Acesso (4 colunas)
     */
    public function index(): void
    {
        SessionManager::requireLogin();
        $currentUserId = (int)$_SESSION['user_id'];

        // 1) TRAVA DE SEGURANÇA: Verifica se pode ver a estrutura ou se é líder
        if (!$this->auth->canViewAccessOverview() && !$this->auth->hasSubordinates($currentUserId)) {
            $this->renderError403();
            return;
        }

        // 2) Buscar dados auxiliares
        $roles = $this->roleModel->findAll();
        $teams = $this->teamModel->findAll();
        $permissions = $this->permissionModel->findAll();

        // 3) Mapeamento de Níveis para as Colunas
        $roleNamesMap = [
            'master'       => ['master'],
            'coordenador'  => ['coordenador'],
            'gerente'      => ['gerente'],
            'funcionario'  => ['funcionário'],
        ];

        $roleIdToName = [];
        $roleIdsByLevel = ['master' => [], 'coordenador' => [], 'gerente' => [], 'funcionario' => []];

        foreach ($roles as $role) {
            $roleIdToName[$role['id']] = $role['name'];
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

        // 4) BUSCA FILTRADA POR ESCOPO
        if ($this->auth->isMasterOrCoordinator($currentUserId)) {
            $allUsers = $this->userModel->findAllWithRoles();
        } else {
            $allUsers = $this->getScopedUsersForManager($currentUserId);
        }

        // 5) Montar as colunas (Lógica original preservada)
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

            foreach (['master', 'coordenador', 'gerente', 'funcionario'] as $level) {
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

        // 6) Renderizar
        $title = 'Gestão de Equipe e Acessos';
        ob_start();
        require __DIR__ . '/../Views/access/main_access_view.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Renderiza a tela de erro 403 (Acesso Negado)
     */
    private function renderError403(): void
    {
        http_response_code(403);
        $title = 'Acesso Negado';

        ob_start();
        require __DIR__ . '/../Views/errors/403.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Busca a estrutura completa de times e membros para o Organograma
     */
    public function organogramaTimes(): void
    {
        SessionManager::requireLogin();
        
        $tree = $this->buildTeamTreeOptimized(null);

        $title = 'Estrutura por Áreas';
        ob_start();
        require __DIR__ . '/../Views/access/organograma_view.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layout/base.php';
    }

    private function buildTeamTreeOptimized(?int $parentId = null): array
    {
        $db = \getDbConnection();

        // 1) Todos os times
        $stmt = $db->query("SELECT id, name, parent_team_id FROM tb_teams ORDER BY name ASC");
        $allTeams = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 2) Todos os membros de todos os times (uma única query)
        $stmt2 = $db->query("
            SELECT ut.team_id,
                u.id AS user_id,
                u.name AS user_name,
                COALESCE(MIN(r.level), 9999) AS min_role_level,
                GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS role_names
            FROM tb_user_teams ut
            JOIN tb_users u ON ut.user_id = u.id
            LEFT JOIN tb_user_roles ur ON u.id = ur.user_id
            LEFT JOIN tb_roles r ON ur.role_id = r.id
            GROUP BY ut.team_id, u.id
            ORDER BY ut.team_id, min_role_level ASC, u.name ASC
        ");
        $membersRows = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        // 3) Agrupar membros por team_id
        $membersByTeam = [];
        foreach ($membersRows as $r) {
            $tid = (int)$r['team_id'];
            if (!isset($membersByTeam[$tid])) $membersByTeam[$tid] = [];
            $membersByTeam[$tid][] = [
                'id' => (int)$r['user_id'],
                'name' => $r['user_name'],
                'min_role_level' => (int)$r['min_role_level'],
                'role_names' => $r['role_names']
            ];
        }

        // 4) Montar árvore em memória
        $treeIndex = [];
        foreach ($allTeams as $team) {
            $teamId = (int)$team['id'];
            $treeIndex[$teamId] = [
                'id' => $teamId,
                'name' => $team['name'],
                'parent_team_id' => $team['parent_team_id'] === null ? null : (int)$team['parent_team_id'],
                'members' => $membersByTeam[$teamId] ?? [],
                'subs' => []
            ];
        }

        // ligar filhos aos pais
        $root = [];
        foreach ($treeIndex as $id => &$node) {
            $pid = $node['parent_team_id'];
            if ($pid === null) {
                $root[] = &$node;
            } elseif (isset($treeIndex[$pid])) {
                $treeIndex[$pid]['subs'][] = &$node;
            } else {
                // orfão — trate se necessário
                $root[] = &$node;
            }
        }
        unset($node);

        // Se quiser retornar apenas a sub-árvore a partir de $parentId:
        if ($parentId === null) return $root;

        // busca no índice o nó com id == parentId
        return isset($treeIndex[$parentId]) ? [$treeIndex[$parentId]] : [];
    }

    public function updateLeader(): void
    {
        SessionManager::requireLogin();
        
        $userId = (int)($_POST['user_id'] ?? 0);
        $leaderId = $_POST['leader_id'] === "" ? null : (int)$_POST['leader_id'];

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
            exit;
        }

        // Validação anti-ciclo simples: usuário não pode ser líder de si mesmo
        if ($userId === $leaderId) {
            echo json_encode(['success' => false, 'message' => 'Usuário não pode ser líder de si mesmo.']);
            exit;
        }

        $db = \getDbConnection();

        // 1. Buscar dados antigos
        $stmt = $db->prepare("SELECT id_lider FROM tb_users WHERE id = :uid");
        $stmt->execute([':uid' => $userId]);
        $oldUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$oldUser) {
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
            exit;
        }

        // 2. Atualizar líder
        $stmt = $db->prepare("UPDATE tb_users SET id_lider = :lid WHERE id = :uid");
        $updated = $stmt->execute([':lid' => $leaderId, ':uid' => $userId]);

        if ($updated) {
            // 3. Registrar auditoria
            $auditLog = new \App\Models\AuditLog();
            $auditLog->log(
                'ALTERAR_LIDER',           // Ação
                'tb_users',                // Tabela
                $userId,                   // ID do registro afetado
                ['id_lider' => $oldUser['id_lider']], // Valor antigo
                ['id_lider' => $leaderId]             // Valor novo
            );

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar líder.']);
        }
        exit;
    }

    public function organogramaHumano(): void
    {
        SessionManager::requireLogin();

        // Se não usa autoload, inclua o serviço manualmente:
        // require_once __DIR__ . '/../Services/OrgChartService.php';
        // use App\Services\OrgChartService; // se namespace suportado

        // 1. Árvore principal a partir de `usuarios` (inclui órfãos como raízes)
        $masters = \App\Services\OrgChartService::buildHumanHierarchyFromUsuarios();

        // 2. Lista de todos os usuários para o Select de troca de líder
        //    Agora vindo de `usuarios` (id e nome)
        $db = \getDbConnection();
        $stmt = $db->query("SELECT id, nome FROM usuarios ORDER BY nome ASC");
        $allUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $title = 'Hierarquia de Liderança';
        ob_start();
        require __DIR__ . '/../Views/access/organograma_humano_view.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Função Recursiva para montar árvore de usuários por id_lider
     */
    private function buildUserTree(?int $leaderId): array
    {
        $db = \getDbConnection();
        
        // Busca usuários que respondem ao líder atual, agregando informação de cargos globais
        $sql = "
            SELECT 
                u.id, 
                u.name, 
                COALESCE(MIN(r.level), 9999) AS min_role_level,
                GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS role_names
            FROM tb_users u
            LEFT JOIN tb_user_roles ur ON u.id = ur.user_id
            LEFT JOIN tb_roles r ON ur.role_id = r.id
            WHERE " . ($leaderId === null ? "u.id_lider IS NULL" : "u.id_lider = :lid") . "
            GROUP BY u.id
            ORDER BY min_role_level ASC, u.name ASC
        ";
                
        $stmt = $db->prepare($sql);
        if ($leaderId !== null) $stmt->bindValue(':lid', $leaderId, \PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            // A) Busca os times que essa pessoa participa (Contexto Visual)
            $stmtT = $db->prepare("
                SELECT t.name 
                FROM tb_user_teams ut
                JOIN tb_teams t ON ut.team_id = t.id
                WHERE ut.user_id = :uid
                ORDER BY t.name ASC
            ");
            $stmtT->execute([':uid' => $user['id']]);
            $user['teams'] = $stmtT->fetchAll(\PDO::FETCH_COLUMN);

            // B) Busca subordinados (Recursão)
            $user['subordinates'] = $this->buildUserTree($user['id']);
        }

        return $users;
    }

    public function getUserPermissionsJson()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!SessionManager::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
            exit;
        }

        // Permite Master/Coordenador ou quem tem manage_users
        if (!$this->auth->canManageUsers() && !$this->auth->isMasterOrCoordinatorCurrent()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
            exit;
        }

        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

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

            // Permissões diretas do usuário (global)
            $stmt = $db->prepare("SELECT permission_id FROM tb_user_permissions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userPermissions = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            // Permissões concedidas ao usuário por time (se pedir por team_id) — opcional
            $teamGrants = [];
            if ($teamId !== null && $teamId > 0) {
                $stmt = $db->prepare("
                    SELECT permission_id 
                    FROM tb_user_team_permissions
                    WHERE user_id = :u AND team_id = :t
                ");
                $stmt->execute([':u' => $userId, ':t' => $teamId]);
                $teamGrants = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
            }

            echo json_encode([
                'success' => true,
                'allPermissions' => $allPermissions,
                'userPermissions' => $userPermissions,
                'teamGrants' => $teamGrants,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao carregar permissões: ' . $e->getMessage()]);
        }
    }

    public function updateUserPermissionsJson()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!SessionManager::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
            exit;
        }

        $actor = SessionManager::getUser();
        $actorId = (is_array($actor) && isset($actor['id'])) ? (int)$actor['id'] : null;

        try {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true);

            $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
            $permissions = $payload['permissions'] ?? [];

            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de usuário inválido.']);
                return;
            }

            // AUTORIZAÇÃO: quem pode alterar as permissões deste usuário?
            if ($actorId === null || !$this->auth->canManageUser($actorId, $userId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão para alterar permissões deste usuário.']);
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

            // Captura permissões antigas para auditoria
            $stmt = $db->prepare("SELECT permission_id FROM tb_user_permissions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $oldPerms = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

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

            // Auditoria (usando AuditLog model)
            try {
                $auditLog = new \App\Models\AuditLog();
                $auditLog->log(
                    'UPDATE_USER_PERMISSIONS',
                    'tb_user_permissions',
                    $userId,
                    $oldPerms,
                    $permIds
                );
            } catch (\Throwable $ae) {
                // Não falhar o fluxo principal por erro de auditoria; apenas log
                error_log("AuditLog error in updateUserPermissionsJson: " . $ae->getMessage());
            }

            $db->commit();

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar permissões: ' . $e->getMessage()]);
        }
    }

    public function updateLeaderFromForm(int $userId): void
    {
        SessionManager::requireLogin();

        $leaderIdRaw = $_POST['id_lider'] ?? '';
        $leaderId = ($leaderIdRaw === '' || $leaderIdRaw === null) ? null : (int)$leaderIdRaw;

        if ($userId <= 0) {
            header('Location: ' . BASE_PATH . '/admin/users');
            exit;
        }

        // Validação: não pode ser líder de si mesmo
        if ($leaderId !== null && $leaderId === $userId) {
            header('Location: ' . BASE_PATH . '/admin/users/' . $userId . '?error=self_leader');
            exit;
        }

        try {
            $db = getDbConnection();
            $stmt = $db->prepare("UPDATE tb_users SET id_lider = ? WHERE id = ?");
            $stmt->execute([$leaderId, $userId]);

            header('Location: ' . BASE_PATH . '/admin/users/' . $userId . '?success=leader_updated');
            exit;
        } catch (\Throwable $e) {
            header('Location: ' . BASE_PATH . '/admin/users/' . $userId . '?error=update_failed');
            exit;
        }
    }

    /**
     * Método auxiliar para buscar usuários do escopo do gestor
     */
    private function getScopedUsersForManager(int $managerId): array
    {
        $db = \getDbConnection();
        $teamIds = $this->auth->getUserTeamIds($managerId);
        $teamIdsStr = !empty($teamIds) ? implode(',', $teamIds) : '0';

        $sql = "
            SELECT DISTINCT u.* 
            FROM tb_users u
            LEFT JOIN tb_user_teams ut ON u.id = ut.user_id
            WHERE u.id_lider = :mid 
               OR ut.team_id IN ($teamIdsStr)
               OR u.id = :mid
            ORDER BY u.name ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':mid' => $managerId]);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($users as &$u) {
            $u['role_ids'] = $this->userModel->getRoleIds((int)$u['id']);
        }

        return $users;
    }
}