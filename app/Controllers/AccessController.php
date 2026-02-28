<?php
// app/Controllers/AccessController.php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Core\Database;
use App\Models\User;
use App\Models\Role;
use App\Services\AuthorizationService;

class AccessController
{
    private User $userModel;
    private Role $roleModel;
    private AuthorizationService $auth;

    public function __construct()
    {
        $this->userModel = new User();
        $this->roleModel = new Role();
        $this->auth      = new AuthorizationService();
    }

    /**
     * Tela principal: Visão Geral de Acesso (4 colunas)
     */
    public function index(): void
    {
        // 1) Exige login
        SessionManager::requireLogin();

        // 2) TRAVA DE SEGURANÇA: verifica permissão
        if (!$this->auth->canViewAccessOverview()) {
            $this->renderError403();
            return;
        }

        // 3) Buscar todos os roles (cargos)
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

        // 4) Buscar todos os usuários com seus roles e times
        $allUsers = $this->userModel->findAllWithRoles();

        // 5) Montar as colunas
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

            // Se não foi colocado em nenhuma coluna, vai para "sem_role"
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

        // 6) Renderizar a view
        $title = 'Visão Geral de Acesso';
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
        
        $tree = $this->buildTeamTree(null);

        $title = 'Estrutura por Áreas';
        ob_start();
        require __DIR__ . '/../Views/access/organograma_view.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layout/base.php';
    }

    private function buildTeamTree(?int $parentId): array
    {
        $db = \getDbConnection();
        
        // Busca times do nível atual
        $sql = "SELECT id, name FROM tb_teams WHERE " . ($parentId === null ? "parent_team_id IS NULL" : "parent_team_id = :pid");
        $stmt = $db->prepare($sql);
        if ($parentId !== null) $stmt->bindValue(':pid', $parentId);
        $stmt->execute();
        $teams = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($teams as &$team) {
            // A) Busca membros deste time (ordenados por level do cargo)
            $stmtM = $db->prepare("
                SELECT u.name, r.name as role_name, r.level
                FROM tb_user_teams ut
                JOIN tb_users u ON ut.user_id = u.id
                JOIN tb_roles r ON ut.role_id = r.id
                WHERE ut.team_id = :tid
                ORDER BY r.level ASC
            ");
            $stmtM->execute([':tid' => $team['id']]);
            $team['members'] = $stmtM->fetchAll(\PDO::FETCH_ASSOC);

            // B) Busca sub-times (Recursão)
            $team['subs'] = $this->buildTeamTree($team['id']);
        }

        return $teams;
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

        // 1. Árvore principal
        $masters = \App\Services\OrgChartService::buildHumanHierarchyFromUsuarios();

        // 2. Lista de todos os usuários (usada no modal de troca de líder)
        $db = \getDbConnection();
        $stmt = $db->query("SELECT id, nome FROM usuarios ORDER BY nome ASC");
        $allUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Líderes Reais
        $leaders = $this->userModel->getActiveLeaders();

        // 4. Todas as abas para o filtro do Offcanvas
        $stmtTabs = $db->query("SELECT id, nome FROM tb_tabs ORDER BY nome ASC");
        $allTabs = $stmtTabs->fetchAll(\PDO::FETCH_ASSOC);

        // 5. Lista de permissões legadas para o filtro do Offcanvas
        $allPermissions = [
            'editarss'          => 'Editar SS',
            'editarRH'          => 'Editar RH',
            'editarAlmox'       => 'Editar Almoxarifado',
            'editarLancamento'  => 'Editar Lançamento',
            'editarEquipamento' => 'Editar Equipamento',
            'admEquipamento'    => 'Adm Equipamento',
            'equipMaster'       => 'Equip Master',
            'equipAC'           => 'Equip AC',
            'cadEquipamento'    => 'Cad Equipamento',
            'editarCTE'         => 'Editar CTE',
            'editarEquipe'      => 'Editar Equipe',
            'editarCargo'       => 'Editar Cargo',
            'editarnota'        => 'Editar Nota',
            'editarNum'         => 'Editar Número',
            'criarNum'          => 'Criar Número',
            'editarConc'        => 'Editar Conc',
            'aprovarConc'       => 'Aprovar Conc',
            'aprovarFE'         => 'Aprovar FE',
            'aprovarLOC'        => 'Aprovar LOC',
            'editarCons'        => 'Editar Cons',
            'editarDataC'       => 'Editar Data C',
            'editarRHDoc'       => 'Editar RH Doc',
            'editarRHSit'       => 'Editar RH Sit',
            'excluirAnexo'      => 'Excluir Anexo',
            'editarAnexo'       => 'Editar Anexo',
            'editarLibFunc'     => 'Editar Lib Func',
            'editarDtRetSS'     => 'Editar Dt Ret SS',
            'alterarEqRH'       => 'Alterar Eq RH',
            'editarTST'         => 'Editar TST',
            'aprovarNFZ'        => 'Aprovar NFZ',
            'equipSit'          => 'Equip Sit',
            'cadNF'             => 'Cad NF',
            'abrirNF'           => 'Abrir NF',
            'relAtestado'       => 'Rel Atestado',
            'editarStatusEmp'   => 'Editar Status Emp',
            'editarEmpresa'     => 'Editar Empresa',
            'editarImovel'      => 'Editar Imóvel',
            'editarLic'         => 'Editar Lic',
            'cadCT'             => 'Cad CT',
            'orcLic'            => 'Orc Lic',
            'propriosPlaca'     => 'Próprios Placa',
        ];

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
        
        // Busca usuários que respondem ao líder atual
        $sql = "SELECT u.id, u.name, r.name as role_name, r.level as role_level
                FROM tb_users u
                LEFT JOIN tb_user_roles ur ON u.id = ur.user_id
                LEFT JOIN tb_roles r ON ur.role_id = r.id
                WHERE " . ($leaderId === null ? "u.id_lider IS NULL" : "u.id_lider = :lid") . "
                ORDER BY r.level ASC, u.name ASC";
                
        $stmt = $db->prepare($sql);
        if ($leaderId !== null) $stmt->bindValue(':lid', $leaderId);
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($users as &$user) {
            // A) Busca os times que essa pessoa participa (Contexto Visual)
            $stmtT = $db->prepare("
                SELECT t.name 
                FROM tb_user_teams ut
                JOIN tb_teams t ON ut.team_id = t.id
                WHERE ut.user_id = :uid
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

        // não redirecionar em endpoint
        if (!SessionManager::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
            exit;
        }

        // se quiser restringir por permissão:
        if (!$this->auth->canManageUsers()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
            exit;
        }

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

            // Normaliza para int
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

    public function updateUserPermissionsJson()
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

    public function fetchOrganogramaTree(string $status): void
    {
        SessionManager::requireLogin();

        $includeOrphans = ($_GET['includeOrphans'] ?? 'false') === 'true';
        $roots = \App\Services\OrgChartService::buildHumanHierarchyByStatus($status, $includeOrphans);

        // --- FUNÇÃO DE ENRIQUECIMENTO ---
        $enrich = function (&$nodes) use (&$enrich) {
            $db = getDbConnection(); // Usando o seu padrão de conexão
            
            foreach ($nodes as &$n) {
                $userId = (int)($n['id'] ?? 0);
                
                if ($userId > 0) {
                    // Buscamos o nivel_acesso diretamente do banco para garantir
                    $stmt = $db->prepare("SELECT nivel_acesso FROM tb_users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $val = $stmt->fetchColumn();
                    
                    // Atribuímos ao campo 'abas' que a view espera
                    $n['abas'] = $val ?: '';
                }

                // Faz o mesmo para os subordinados (recursivo)
                if (!empty($n['subordinates']) && is_array($n['subordinates'])) {
                    $enrich($n['subordinates']);
                }
            }
        };
        
        // Executa a busca para toda a árvore
        $enrich($roots);
        // --- FIM DO ENRIQUECIMENTO ---

        require_once __DIR__ . '/../Views/access/_tree_partial.php';
        header('Content-Type: text/html; charset=utf-8');
        ob_clean();
        renderUserNode($roots, $roots);
        exit;
    }

    public function fetchOrganogramaTreeFromLeader(): void
    {
        SessionManager::requireLogin();

        $leaderId = (int)($_GET['leaderId'] ?? 0);
        $status   = $_GET['status'] ?? 'active';

        if ($leaderId <= 0) {
            http_response_code(400);
            echo "leaderId inválido";
            exit;
        }

        $roots = \App\Services\OrgChartService::buildHierarchyFromLeaderByStatus($leaderId, $status);

        // --- FUNÇÃO DE ENRIQUECIMENTO ---
        $enrich = function (&$nodes) use (&$enrich) {
            $db = getDbConnection(); // Usando o seu padrão de conexão
            
            foreach ($nodes as &$n) {
                $userId = (int)($n['id'] ?? 0);
                
                if ($userId > 0) {
                    // Buscamos o nivel_acesso diretamente do banco para garantir
                    $stmt = $db->prepare("SELECT nivel_acesso FROM tb_users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $val = $stmt->fetchColumn();
                    
                    // Atribuímos ao campo 'abas' que a view espera
                    $n['abas'] = $val ?: '';
                }

                // Faz o mesmo para os subordinados (recursivo)
                if (!empty($n['subordinates']) && is_array($n['subordinates'])) {
                    $enrich($n['subordinates']);
                }
            }
        };
        
        // Executa a busca para toda a árvore
        $enrich($roots);
        // --- FIM DO ENRIQUECIMENTO ---

        require_once __DIR__ . '/../Views/access/_tree_partial.php';
        header('Content-Type: text/html; charset=utf-8');
        ob_clean();
        renderUserNode($roots, $roots);
        exit;
    }
}