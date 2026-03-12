<?php
namespace App\Controllers;

use App\Models\Team;
use App\Models\TeamPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Core\SessionManager;
use App\Models\UserTeam;
use App\Models\User;
use App\Services\AuthorizationService;

class TeamController {
    private Team $teamModel;
    private TeamPermission $teamPermissionModel;
    private Permission $permissionModel;
    private Role $roleModel;
    private UserTeam $userTeamModel;
    private User $userModel;
    private AuthorizationService $auth;

    public function __construct() {
        $this->teamModel = new Team();
        $this->teamPermissionModel = new TeamPermission();
        $this->permissionModel = new Permission();
        $this->roleModel = new Role();
        $this->userTeamModel = new UserTeam();
        $this->userModel = new User();
        $this->auth = new AuthorizationService();
        
        // Define BASE_PATH se não estiver definido para evitar erros de concatenação
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', '/atlasware/public');
        }
    }

    /**
     * Auxiliar para redirecionamento seguro usando BASE_PATH
     */
    private function redirect(string $path): void {
        header('Location: ' . BASE_PATH . $path);
        exit;
    }

    /**
     * Exige login e permissão para gerenciar times.
     */
    private function requireCanManageTeams(): bool
    {
        SessionManager::requireLogin();

        if (!$this->auth->canManageTeams()) {
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
     * Verifica se o usuário pode acessar um time específico.
     */
    private function requireCanAccessTeam(int $teamId): bool
    {
        if (!$this->auth->canManageTeamsInTeam($teamId)) {
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

    // ==== CRUD BÁSICO ====

    public function index(): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        $manageableTeamIds = $this->auth->getManageableTeamIds('manage_teams');

        if (empty($manageableTeamIds)) {
            $teams = $this->teamModel->findAll();
        } else {
            $teams = $this->teamModel->findByIds($manageableTeamIds);
        }

        $title = 'Gestão de Times';
        ob_start();
        require __DIR__ . '/../Views/access/teams_index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    public function create(): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
            $this->redirect('/dashboard');
            return;
        }

        $team = null;
        $allTeams = $this->teamModel->findAll();

        $title = 'Novo Time';
        ob_start();
        require __DIR__ . '/../Views/access/team_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    public function store(): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
            $this->redirect('/admin/teams');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/teams');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $parentTeamId = !empty($_POST['parent_team_id']) ? (int)$_POST['parent_team_id'] : null;

        if (empty($name)) {
            $_SESSION['error'] = 'O nome do time é obrigatório.';
            $this->redirect('/admin/teams/create');
            return;
        }

        $this->teamModel->create([
            'name' => $name,
            'parent_team_id' => $parentTeamId
        ]);

        $_SESSION['success'] = 'Time criado com sucesso!';
        $this->redirect('/admin/teams');
    }

    public function edit(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        $team = $this->teamModel->findById($id);
        if (!$team) {
            $_SESSION['error'] = 'Time não encontrado.';
            $this->redirect('/admin/teams');
            return;
        }

        $allTeams = $this->teamModel->findAll();

        $title = 'Editar Time';
        ob_start();
        require __DIR__ . '/../Views/access/team_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    public function update(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/teams');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $parentTeamId = !empty($_POST['parent_team_id']) ? (int)$_POST['parent_team_id'] : null;

        if (empty($name)) {
            $_SESSION['error'] = 'O nome do time é obrigatório.';
            $this->redirect("/admin/teams/$id/edit");
            return;
        }

        $this->teamModel->update($id, [
            'name' => $name,
            'parent_team_id' => $parentTeamId
        ]);

        $_SESSION['success'] = 'Time atualizado com sucesso!';
        $this->redirect('/admin/teams');
    }

    public function delete(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
            $this->redirect('/admin/teams');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/teams');
            return;
        }

        $this->teamModel->delete($id);

        $_SESSION['success'] = 'Time excluído com sucesso!';
        $this->redirect('/admin/teams');
    }

    // ==== PERMISSÕES POR TIME ====

    public function permissions(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        $team = $this->teamModel->findById($id);
        if (!$team) {
            $_SESSION['error'] = 'Time não encontrado.';
            $this->redirect('/admin/teams');
            return;
        }

        $allPermissions = $this->permissionModel->findAll();
        $teamPermissions = $this->teamPermissionModel->getPermissionsByTeam($id);
        $teamPermissionIds = array_column($teamPermissions, 'permission_id');
        $roles = $this->roleModel->findAll();

        $allowedTillRoleId = null;
        if (!empty($teamPermissions)) {
            $allowedTillRoleId = $teamPermissions[0]['allowed_till_role_id'] ?? null;
        }

        $title = 'Permissões do Time: ' . $team['name'];
        ob_start();
        require __DIR__ . '/../Views/access/team_permissions.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    public function savePermissions(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/admin/teams/$id/permissions");
            return;
        }

        // Recebe dados do formulário
        $checkedPermissionIds = $_POST['permissions'] ?? [];        // array de permission_id marcados
        $allowedInputs = $_POST['allowed'] ?? [];                   // associative: permission_id => allowed_till_role_id (string or empty)
        // Normaliza
        $checkedPermissionIds = array_map('intval', $checkedPermissionIds);

        // Dados do usuário atual
        $currentUser = \App\Core\SessionManager::getUser();
        $currentUserId = (int)($currentUser['id'] ?? 0);

        // Tenta obter o nível do usuário atual — adapte se sua AuthorizationService já tiver esse helper.
        $currentUserLevel = null;
        if (method_exists($this->auth, 'getCurrentUserRoleLevel')) {
            // ideal: AuthorizationService::getCurrentUserRoleLevel() retorna integer (ex: 2 = Supervisor)
            $currentUserLevel = (int)$this->auth->getCurrentUserRoleLevel();
        } elseif (method_exists($this->auth, 'getUserHighestRoleLevel')) {
            $currentUserLevel = (int)$this->auth->getUserHighestRoleLevel($currentUserId);
        } else {
            // Fallback conservador: tenta obter o primeiro role do usuário via User model (se houver)
            $currentUserLevel = null; // leave null -> we'll only apply strict role-level checks to non-master if we can determine level later
        }

        $errors = [];
        $permissionsMap = []; // permission_id => allowed_till_role_id|null

        foreach ($checkedPermissionIds as $pid) {
            // Busca info da permissão para validações (nome)
            $perm = $this->permissionModel->findById($pid);
            if (!$perm) {
                $errors[] = "Permissão (ID {$pid}) inválida.";
                continue;
            }
            $permName = $perm['name'];

            // Regra 1: "Ninguém concede o que não tem" (exceto Master/Coordinator)
            if (!$this->auth->isMasterOrCoordinatorCurrent()) {
                // AuthorizationService::can(userId, permissionName) existe no seu código
                if (!$this->auth->can($currentUserId, $permName)) {
                    $errors[] = "Você não possui a permissão '{$permName}' para conceder.";
                    continue;
                }
            }

            // Allowed value enviado para esta permissão (pode ser string '', meaning null)
            $allowedRaw = $allowedInputs[(string)$pid] ?? null;
            $allowedVal = ($allowedRaw === '' || $allowedRaw === null) ? null : (int)$allowedRaw;

            // Validação do allowed_till_role_id:
            if ($allowedVal !== null) {
                $role = $this->roleModel->findById($allowedVal);
                if (!$role) {
                    $errors[] = "Cargo selecionado inválido para a permissão '{$permName}'.";
                    continue;
                }

                // Se não conseguimos determinar o nível do usuário atual, tentamos obtê-lo via AuthorizationService (ou você pode adaptar aqui)
                if ($currentUserLevel === null) {
                    if (method_exists($this->auth, 'getUserHighestRoleLevel')) {
                        $currentUserLevel = (int)$this->auth->getUserHighestRoleLevel($currentUserId);
                    }
                }

                // Aplicar regra: usuário não pode permitir delegação para cargos de nível igual ou superior (mais poder)
                // Lembrete: níveis menores = mais poder (ex: 1 = Master). Portanto permitimos only role.level > currentUserLevel
                if (!$this->auth->isMasterOrCoordinatorCurrent() && $currentUserLevel !== null) {
                    $allowedRoleLevel = (int)$role['level'];
                    if ($allowedRoleLevel <= $currentUserLevel) {
                        $errors[] = "Valor de 'Até qual nível' inválido para '{$permName}'. Você não pode delegar a um cargo de nível igual ou superior ao seu.";
                        continue;
                    }
                }
            }

            // Se tudo ok, adiciona ao mapa
            $permissionsMap[$pid] = $allowedVal;
        }

        // Se houve erros, abortamos e retornamos mensagens ao usuário para correção
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $this->redirect("/admin/teams/{$id}/permissions");
            return;
        }

        // Salva no model (transação interna do model)
        try {
            // Use o método que aceita mapa associativo (conforme a extensão do TeamPermission que você adicionou)
            $this->teamPermissionModel->setPermissionsForTeamAssociative($id, $permissionsMap);
            $_SESSION['success'] = 'Permissões do time atualizadas com sucesso!';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao salvar permissões: ' . $e->getMessage();
        }

        $this->redirect("/admin/teams/{$id}/permissions");
    }

    // ==== MEMBROS DO TIME ====

    public function members(int $teamId): void
    {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->requireCanAccessTeam($teamId)) {
            return;
        }

        $team = $this->teamModel->findById($teamId);
        if (!$team) {
            $_SESSION['error'] = 'Time não encontrado.';
            $this->redirect('/admin/teams');
            return;
        }

        $members = $this->userTeamModel->getMembersByTeam($teamId);
        $allUsers = $this->userModel->findAllBasic();
        $roles = $this->roleModel->findAll();

        $title = 'Membros do Time: ' . $team['name'];
        ob_start();
        require __DIR__ . '/../Views/access/team_members.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    public function addMember(int $teamId): void
    {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->requireCanAccessTeam($teamId)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/admin/teams/$teamId/members");
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $_SESSION['error'] = 'Usuário é obrigatório.';
            $this->redirect("/admin/teams/$teamId/members");
            return;
        }

        try {
            $this->userTeamModel->addMember($teamId, $userId);
            $_SESSION['success'] = 'Membro adicionado com sucesso.';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao adicionar membro: ' . $e->getMessage();
        }

        $this->redirect("/admin/teams/$teamId/members");
    }

    public function removeMember(int $teamId, int $userId): void
    {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        if (!$this->requireCanAccessTeam($teamId)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("/admin/teams/$teamId/members");
            return;
        }

        try {
            $this->userTeamModel->removeMember($teamId, $userId);
            $_SESSION['success'] = 'Membro removido do time.';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao remover membro: ' . $e->getMessage();
        }

        $this->redirect("/admin/teams/$teamId/members");
    }
}