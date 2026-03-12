<?php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Models\Permission;
use App\Models\Tab;
use App\Services\AuthorizationService;

class PermissionController
{
    private Permission $permissionModel;
    private Tab $tabModel;
    private AuthorizationService $auth;

    public function __construct()
    {
        $this->permissionModel = new Permission();
        $this->tabModel = new Tab();
        $this->auth = new AuthorizationService();

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', '/atlasware/public');
        }
    }

    /**
     * Redirecionamento simples centralizado
     */
    private function redirect(string $path): void
    {
        header('Location: ' . BASE_PATH . $path);
        exit;
    }

    /**
     * Verifica se o usuário está logado e tem permissão para gerenciar permissões.
     * Apenas Master/Coordinator (ou regra equivalente) devem poder acessar CRUD de permissões.
     */
    private function requireCanManagePermissions(): bool
    {
        SessionManager::requireLogin();

        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
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
     * Lista todas as permissões (index).
     */
    public function index(): void
    {
        if (!$this->requireCanManagePermissions()) return;

        $permissions = $this->permissionModel->findAll();
        $title = 'Permissões do Sistema';

        ob_start();
        require __DIR__ . '/../Views/access/permissions_index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Formulário de criação.
     */
    public function create(): void
    {
        if (!$this->requireCanManagePermissions()) return;

        $tabs = $this->tabModel->findAll();
        $permission = null;
        $title = 'Nova Permissão';

        ob_start();
        require __DIR__ . '/../Views/access/permission_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Persiste nova permissão.
     */
    public function store(): void
    {
        if (!$this->requireCanManagePermissions()) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('/admin/permissions');

        $name = trim((string)($_POST['name'] ?? ''));
        $tabId = isset($_POST['tab_id']) && $_POST['tab_id'] !== '' ? (int)$_POST['tab_id'] : null;

        if ($name === '') {
            $_SESSION['error'] = 'O nome da permissão é obrigatório.';
            $this->redirect('/admin/permissions/create');
        }

        // Checa duplicidade (se o model tiver findByName)
        if (method_exists($this->permissionModel, 'findByName')) {
            $existing = $this->permissionModel->findByName($name);
            if ($existing) {
                $_SESSION['error'] = 'Já existe uma permissão com esse nome.';
                $this->redirect('/admin/permissions/create');
            }
        }

        try {
            $this->permissionModel->create(['name' => $name, 'tab_id' => $tabId]);
            $_SESSION['success'] = 'Permissão criada com sucesso!';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao criar permissão: ' . $e->getMessage();
        }

        $this->redirect('/admin/permissions');
    }

    /**
     * Formulário de edição.
     */
    public function edit(int $id): void
    {
        if (!$this->requireCanManagePermissions()) return;

        $permission = $this->permissionModel->findById($id);
        if (!$permission) {
            $_SESSION['error'] = 'Permissão não encontrada.';
            $this->redirect('/admin/permissions');
        }

        $tabs = $this->tabModel->findAll();
        $title = 'Editar Permissão';

        ob_start();
        require __DIR__ . '/../Views/access/permission_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Atualiza permissão.
     */
    public function update(int $id): void
    {
        if (!$this->requireCanManagePermissions()) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('/admin/permissions');

        $name = trim((string)($_POST['name'] ?? ''));
        $tabId = isset($_POST['tab_id']) && $_POST['tab_id'] !== '' ? (int)$_POST['tab_id'] : null;

        if ($name === '') {
            $_SESSION['error'] = 'O nome da permissão é obrigatório.';
            $this->redirect("/admin/permissions/{$id}/edit");
        }

        // Checa duplicidade por nome (se model suportar)
        if (method_exists($this->permissionModel, 'findByName')) {
            $existing = $this->permissionModel->findByName($name);
            if ($existing && (int)$existing['id'] !== $id) {
                $_SESSION['error'] = 'Já existe outra permissão com esse nome.';
                $this->redirect("/admin/permissions/{$id}/edit");
            }
        }

        try {
            $this->permissionModel->update($id, ['name' => $name, 'tab_id' => $tabId]);
            $_SESSION['success'] = 'Permissão atualizada com sucesso!';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao atualizar permissão: ' . $e->getMessage();
        }

        $this->redirect('/admin/permissions');
    }

    /**
     * Remove permissão — somente se não houver referências.
     */
    public function delete(int $id): void
    {
        if (!$this->requireCanManagePermissions()) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('/admin/permissions');

        // Se o model suportar findReferences, utilize-o para impedir exclusão quando houver vínculos
        $refs = [];
        if (method_exists($this->permissionModel, 'findReferences')) {
            try {
                $refs = $this->permissionModel->findReferences($id);
            } catch (\Throwable $e) {
                // ignore, deixamos $refs vazio para tentativa de exclusão direta
                $refs = [];
            }
        }

        if (!empty($refs)) {
            $_SESSION['error'] = 'Não é possível excluir: permissão referenciada em ' . implode(', ', array_keys($refs));
            $this->redirect('/admin/permissions');
        }

        try {
            $this->permissionModel->delete($id);
            $_SESSION['success'] = 'Permissão excluída com sucesso!';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao excluir permissão: ' . $e->getMessage();
        }

        $this->redirect('/admin/permissions');
    }
}