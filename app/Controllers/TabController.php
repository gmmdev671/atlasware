<?php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Models\Tab;
use App\Services\AuthorizationService;

class TabController
{
    private Tab $tabModel;
    private AuthorizationService $auth;

    public function __construct()
    {
        $this->tabModel = new Tab();
        $this->auth = new AuthorizationService();

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', '/atlasware/public');
        }
    }

    private function redirect(string $path): void
    {
        header('Location: ' . BASE_PATH . $path);
        exit;
    }

    private function render403(): void
    {
        http_response_code(403);
        $title = 'Acesso negado';
        ob_start();
        require __DIR__ . '/../Views/errors/403.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    private function requireCanManageTabs(): bool
    {
        SessionManager::requireLogin();

        // Reutiliza regra usada para permissões/configurações globais
        if (!$this->auth->isMasterOrCoordinatorCurrent()) {
            $this->render403();
            return false;
        }

        return true;
    }

    /**
     * Lista (index)
     */
    public function index(): void
    {
        if (!$this->requireCanManageTabs()) return;

        $tabs = $this->tabModel->findAll();
        $title = 'Gestão de Abas';

        ob_start();
        require __DIR__ . '/../Views/access/tabs_index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        if (!$this->requireCanManageTabs()) return;

        $tab = null;
        $title = 'Nova Aba';
        ob_start();
        require __DIR__ . '/../Views/access/tab_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Persiste nova aba
     */
    public function store(): void
    {
        if (!$this->requireCanManageTabs()) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('/admin/tabs');

        $nome = trim((string)($_POST['nome'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $keyName = trim((string)($_POST['key_name'] ?? ''));

        if ($nome === '') {
            $_SESSION['error'] = 'O nome da aba é obrigatório.';
            $this->redirect('/admin/tabs/create');
        }

        // Verifica duplicidade por nome (se disponível)
        if (method_exists($this->tabModel, 'findByNome')) {
            $existing = $this->tabModel->findByNome($nome);
            if ($existing) {
                $_SESSION['error'] = 'Já existe uma aba com esse nome.';
                $this->redirect('/admin/tabs/create');
            }
        }

        // Verifica duplicidade por key_name (se informado)
        if ($keyName !== '' && method_exists($this->tabModel, 'findByKeyName')) {
            $existingKey = $this->tabModel->findByKeyName($keyName);
            if ($existingKey) {
                $_SESSION['error'] = 'Já existe uma aba com esse key_name.';
                $this->redirect('/admin/tabs/create');
            }
        }

        try {
            $this->tabModel->create([
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null,
                'key_name' => $keyName !== '' ? $keyName : null
            ]);
            $_SESSION['success'] = 'Aba criada com sucesso!';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao criar aba: ' . $e->getMessage();
        }

        $this->redirect('/admin/tabs');
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): void
    {
        if (!$this->requireCanManageTabs()) return;

        $tab = $this->tabModel->findById($id);
        if (!$tab) {
            $_SESSION['error'] = 'Aba não encontrada.';
            $this->redirect('/admin/tabs');
        }

        $title = 'Editar Aba';
        ob_start();
        require __DIR__ . '/../Views/access/tab_form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Atualiza a aba
     */
    public function update(int $id): void
    {
        if (!$this->requireCanManageTabs()) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('/admin/tabs');

        $nome = trim((string)($_POST['nome'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $keyName = trim((string)($_POST['key_name'] ?? ''));

        if ($nome === '') {
            $_SESSION['error'] = 'O nome da aba é obrigatório.';
            $this->redirect("/admin/tabs/{$id}/edit");
        }

        // Checa duplicidade por nome (ignorando o próprio registro)
        if (method_exists($this->tabModel, 'findByNome')) {
            $existing = $this->tabModel->findByNome($nome);
            if ($existing && (int)$existing['id'] !== $id) {
                $_SESSION['error'] = 'Já existe outra aba com esse nome.';
                $this->redirect("/admin/tabs/{$id}/edit");
            }
        }

        // Checa duplicidade por key_name
        if ($keyName !== '' && method_exists($this->tabModel, 'findByKeyName')) {
            $existingKey = $this->tabModel->findByKeyName($keyName);
            if ($existingKey && (int)$existingKey['id'] !== $id) {
                $_SESSION['error'] = 'Já existe outra aba com esse key_name.';
                $this->redirect("/admin/tabs/{$id}/edit");
            }
        }

        try {
            $this->tabModel->update($id, [
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null,
                'key_name' => $keyName !== '' ? $keyName : null
            ]);
            $_SESSION['success'] = 'Aba atualizada com sucesso!';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao atualizar aba: ' . $e->getMessage();
        }

        $this->redirect('/admin/tabs');
    }

    /**
     * Excluir aba (somente se não houver referências)
     */
    public function delete(int $id): void
    {
        if (!$this->requireCanManageTabs()) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('/admin/tabs');

        // Checa referências (se o model suportar)
        $refs = [];
        if (method_exists($this->tabModel, 'findReferences')) {
            try {
                $refs = $this->tabModel->findReferences($id);
            } catch (\Throwable $e) {
                $refs = [];
            }
        }

        if (!empty($refs)) {
            $_SESSION['error'] = 'Não é possível excluir: esta aba está vinculada em ' . implode(', ', array_keys($refs));
            $this->redirect('/admin/tabs');
        }

        try {
            $this->tabModel->delete($id);
            $_SESSION['success'] = 'Aba excluída com sucesso!';
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Erro ao excluir aba: ' . $e->getMessage();
        }

        $this->redirect('/admin/tabs');
    }
}