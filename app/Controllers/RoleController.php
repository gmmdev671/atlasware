<?php
namespace App\Controllers;

use App\Models\Role;
use App\Core\SessionManager;
use App\Services\AuthorizationService;
use App\Models\Permission;
use App\Models\RolePermission;

class RoleController {
    private Role $roleModel;
    private Permission $permissionModel;
    private RolePermission $rolePermissionModel;
    private AuthorizationService $auth;

    public function __construct() {
        $this->roleModel = new Role();
        $this->permissionModel = new Permission();
        $this->rolePermissionModel = new RolePermission();
        $this->auth = new AuthorizationService();
    }

    /**
     * Exige login e permissão para gerenciar cargos.
     * Retorna false se acesso negado (e já renderiza 403).
     */
    private function requireCanManageRoles(): bool
    {
        SessionManager::requireLogin();

        if (!$this->auth->canManageRoles()) {
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

    private function render(string $viewPath, array $vars = []) {
        extract($vars, EXTR_SKIP);

        ob_start();
        include __DIR__ . "/../Views/{$viewPath}";
        $content = ob_get_clean();

        $title = $vars['title'] ?? 'Gestão de Cargos';
        include __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Tela para gerenciar permissões de um cargo
     */
    public function permissions(int $id) {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        $role = $this->roleModel->findById($id);
        if (!$role) {
            http_response_code(404);
            echo "Cargo não encontrado.";
            exit();
        }

        $allPermissions = $this->permissionModel->findAll();
        $rolePermissionIds = $this->rolePermissionModel->getPermissionIdsByRole($id);

        $this->render('access/role_permissions.php', [
            'role' => $role,
            'allPermissions' => $allPermissions,
            'rolePermissionIds' => $rolePermissionIds,
            'title' => "Permissões do Cargo: {$role['name']}"
        ]);
    }

    /**
     * Salvar permissões de um cargo
     */
    public function savePermissions(int $id) {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: " . BASE_PATH . "/admin/roles/{$id}/permissions");
            exit();
        }

        $permissionIds = $_POST['permissions'] ?? [];
        $permissionIds = array_map('intval', $permissionIds);

        try {
            $this->rolePermissionModel->syncPermissions($id, $permissionIds);
            $_SESSION['success'] = "Permissões atualizadas com sucesso!";
        } catch (\Exception $e) {
            $_SESSION['error'] = "Erro ao atualizar permissões: " . $e->getMessage();
        }

        header("Location: " . BASE_PATH . "/admin/roles/{$id}/permissions");
        exit();
    }

    // Listar todas as roles
    public function index() {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        $roles = $this->roleModel->findAll();
        $this->render('access/admin_roles.php', [
            'roles' => $roles,
            'title' => 'Gestão de Cargos'
        ]);
    }

    // Exibir formulário para criar nova role
    public function createForm() {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        $this->render('access/role_form.php', ['title' => 'Criar Role']);
    }

    // Salvar nova role
    public function store() {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $level = (int)($_POST['level'] ?? 99);

            if ($name === '') {
                $error = "O nome da role é obrigatório.";
                $this->render('access/role_form.php', [
                    'error' => $error,
                    'title' => 'Criar Role'
                ]);
                return;
            }

            try {
                $this->roleModel->create(['name' => $name, 'level' => $level]);
                header('Location: ' . BASE_PATH . '/admin/roles');
                exit();
            } catch (\Exception $e) {
                $error = "Erro ao criar role: " . $e->getMessage();
                $this->render('access/role_form.php', [
                    'error' => $error,
                    'title' => 'Criar Role'
                ]);
            }
        } else {
            header('Location: ' . BASE_PATH . '/admin/roles');
            exit();
        }
    }

    // Exibir formulário para editar role
    public function editForm(int $id) {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        $role = $this->roleModel->findById($id);
        if (!$role) {
            http_response_code(404);
            echo "Role não encontrada.";
            exit();
        }

        $this->render('access/role_form.php', [
            'role' => $role,
            'title' => 'Editar Role'
        ]);
    }

    // Atualizar role
    public function update(int $id) {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $level = (int)($_POST['level'] ?? 99);

            if ($name === '') {
                $error = "O nome da role é obrigatório.";
                $role = $this->roleModel->findById($id);
                $this->render('access/role_form.php', [
                    'role'  => $role,
                    'error' => $error,
                    'title' => 'Editar Role'
                ]);
                return;
            }

            try {
                $this->roleModel->update($id, ['name' => $name, 'level' => $level]);
                header('Location: ' . BASE_PATH . '/admin/roles');
                exit();
            } catch (\Exception $e) {
                $error = "Erro ao atualizar role: " . $e->getMessage();
                $role = $this->roleModel->findById($id);
                $this->render('access/role_form.php', [
                    'role'  => $role,
                    'error' => $error,
                    'title' => 'Editar Role'
                ]);
            }
        } else {
            header('Location: ' . BASE_PATH . '/admin/roles');
            exit();
        }
    }

    // Deletar role
    public function delete(int $id) {
        if (!$this->requireCanManageRoles()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_PATH . '/admin/roles');
            exit();
        }

        try {
            $this->roleModel->delete($id);
            header('Location: ' . BASE_PATH . '/admin/roles');
            exit();
        } catch (\Exception $e) {
            echo "Erro ao deletar role: " . $e->getMessage();
        }
    }
}