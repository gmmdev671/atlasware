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
    }

    /**
     * Exige login e permissão para gerenciar times.
     * Retorna false se acesso negado (e já renderiza 403).
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
     * Retorna false e renderiza 403 se não puder.
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

    /**
     * Lista todos os times (ou apenas os gerenciáveis pelo usuário).
     */
    public function index(): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Pegar apenas os times que o usuário pode gerenciar
        $manageableTeamIds = $this->auth->getManageableTeamIds('manage_teams');

        if (empty($manageableTeamIds)) {
            // Se não retornou nenhum ID mas passou no canManageTeams,
            // significa que é Master/Coordenador e pode ver todos
            $teams = $this->teamModel->findAll();
        } else {
            // Gerente: filtrar apenas seus times
            $teams = $this->teamModel->findByIds($manageableTeamIds);
        }

        $title = 'Gestão de Times';
        ob_start();
        require __DIR__ . '/../Views/access/teams_index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Exibe formulário de criação de time.
     * Apenas Master/Coordenador podem criar times.
     */
    public function create(): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Apenas Master/Coordenador podem criar times
        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
            http_response_code(403);
            $title = 'Acesso negado';
            ob_start();
            require __DIR__ . '/../Views/errors/403.php';
            $content = ob_get_clean();
            require __DIR__ . '/../Views/layout/base.php';
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

    /**
     * Processa criação de time.
     * Apenas Master/Coordenador podem criar times.
     */
    public function store(): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Apenas Master/Coordenador podem criar times
        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
            http_response_code(403);
            $title = 'Acesso negado';
            ob_start();
            require __DIR__ . '/../Views/errors/403.php';
            $content = ob_get_clean();
            require __DIR__ . '/../Views/layout/base.php';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /atlasware/public/admin/teams');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $parentTeamId = !empty($_POST['parent_team_id']) ? (int)$_POST['parent_team_id'] : null;

        if (empty($name)) {
            $_SESSION['error'] = 'O nome do time é obrigatório.';
            header('Location: /atlasware/public/admin/teams/create');
            exit;
        }

        $this->teamModel->create([
            'name' => $name,
            'parent_team_id' => $parentTeamId
        ]);

        $_SESSION['success'] = 'Time criado com sucesso!';
        header('Location: /atlasware/public/admin/teams');
        exit;
    }

    /**
     * Exibe formulário de edição de time.
     */
    public function edit(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Verificar se pode acessar ESTE time específico
        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        $team = $this->teamModel->findById($id);
        if (!$team) {
            $_SESSION['error'] = 'Time não encontrado.';
            header('Location: /atlasware/public/admin/teams');
            exit;
        }

        $allTeams = $this->teamModel->findAll();

        $title = 'Editar Time';
        ob_start();
        require __DIR__ . '/../Views/access/team_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Processa atualização de time.
     */
    public function update(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Verificar se pode acessar ESTE time específico
        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /atlasware/public/admin/teams');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $parentTeamId = !empty($_POST['parent_team_id']) ? (int)$_POST['parent_team_id'] : null;

        if (empty($name)) {
            $_SESSION['error'] = 'O nome do time é obrigatório.';
            header("Location: /atlasware/public/admin/teams/edit/$id");
            exit;
        }

        $this->teamModel->update($id, [
            'name' => $name,
            'parent_team_id' => $parentTeamId
        ]);

        $_SESSION['success'] = 'Time atualizado com sucesso!';
        header('Location: /atlasware/public/admin/teams');
        exit;
    }

    /**
     * Exclui um time.
     * Apenas Master/Coordenador podem excluir times.
     */
    public function delete(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Apenas Master/Coordenador podem excluir times
        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
            http_response_code(403);
            $title = 'Acesso negado';
            ob_start();
            require __DIR__ . '/../Views/errors/403.php';
            $content = ob_get_clean();
            require __DIR__ . '/../Views/layout/base.php';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /atlasware/public/admin/teams');
            exit;
        }

        // TODO: Validar se há membros ou permissões vinculadas antes de excluir
        $this->teamModel->delete($id);

        $_SESSION['success'] = 'Time excluído com sucesso!';
        header('Location: /atlasware/public/admin/teams');
        exit;
    }

    // ==== PERMISSÕES POR TIME ====

    /**
     * Exibe tela de gerenciamento de permissões de um time.
     */
    public function permissions(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Verificar se pode acessar ESTE time específico
        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        $team = $this->teamModel->findById($id);
        if (!$team) {
            $_SESSION['error'] = 'Time não encontrado.';
            header('Location: /atlasware/public/admin/teams');
            exit;
        }

        $allPermissions = $this->permissionModel->findAll();
        $teamPermissions = $this->teamPermissionModel->getPermissionsByTeam($id);
        $teamPermissionIds = array_column($teamPermissions, 'permission_id');

        // Para o select de "até qual nível pode delegar"
        $roles = $this->roleModel->findAll();

        // Se todas usam o mesmo allowed_till_role_id,
        // pegamos o primeiro (ou null se não tiver).
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

    /**
     * Salva permissões de um time.
     */
    public function savePermissions(int $id): void {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Verificar se pode acessar ESTE time específico
        if (!$this->requireCanAccessTeam($id)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /atlasware/public/admin/teams/$id/permissions");
            exit;
        }

        $permissionIds = $_POST['permissions'] ?? [];
        $allowedTillRoleId = !empty($_POST['allowed_till_role_id']) ? (int)$_POST['allowed_till_role_id'] : null;

        $this->teamPermissionModel->setPermissionsForTeam($id, $permissionIds, $allowedTillRoleId);

        $_SESSION['success'] = 'Permissões do time atualizadas com sucesso!';
        header("Location: /atlasware/public/admin/teams/$id/permissions");
        exit;
    }

    // ==== MEMBROS DO TIME ====

    /**
     * Lista e gerencia membros de um time.
     */
    public function members(int $teamId): void
    {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Verificar se pode acessar ESTE time específico
        if (!$this->requireCanAccessTeam($teamId)) {
            return;
        }

        $team = $this->teamModel->findById($teamId);
        if (!$team) {
            $_SESSION['error'] = 'Time não encontrado.';
            header('Location: /atlasware/public/admin/teams');
            exit;
        }

        // Membros atuais
        $members = $this->userTeamModel->getMembersByTeam($teamId);

        // Todos os usuários para o select de adição
        $allUsers = $this->userModel->findAllBasic();

        // Todas as roles para o select de papel no time
        $roles = $this->roleModel->findAll();

        $title = 'Membros do Time: ' . $team['name'];
        ob_start();
        require __DIR__ . '/../Views/access/team_members.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Adiciona/atualiza membro em um time.
     */
    public function addMember(int $teamId): void
    {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Verificar se pode acessar ESTE time específico
        if (!$this->requireCanAccessTeam($teamId)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /atlasware/public/admin/teams/$teamId/members");
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($userId <= 0 || $roleId <= 0) {
            $_SESSION['error'] = 'Usuário e cargo são obrigatórios.';
            header("Location: /atlasware/public/admin/teams/$teamId/members");
            exit;
        }

        $this->userTeamModel->addOrUpdateMember($teamId, $userId, $roleId);

        $_SESSION['success'] = 'Membro adicionado/atualizado com sucesso.';
        header("Location: /atlasware/public/admin/teams/$teamId/members");
        exit;
    }

    /**
     * Remove um membro de um time.
     */
    public function removeMember(int $teamId, int $userId): void
    {
        if (!$this->requireCanManageTeams()) {
            return;
        }

        // Verificar se pode acessar ESTE time específico
        if (!$this->requireCanAccessTeam($teamId)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /atlasware/public/admin/teams/$teamId/members");
            exit;
        }

        $this->userTeamModel->removeMember($teamId, $userId);

        $_SESSION['success'] = 'Membro removido do time.';
        header("Location: /atlasware/public/admin/teams/$teamId/members");
        exit;
    }
}