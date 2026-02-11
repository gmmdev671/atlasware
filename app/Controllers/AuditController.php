<?php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Models\AuditLog;
use App\Services\AuthorizationService;

class AuditController
{
    private AuditLog $logModel;
    private AuthorizationService $auth;

    public function __construct()
    {
        $this->logModel = new AuditLog();
        $this->auth = new AuthorizationService();
    }

    /**
     * Lista os logs de auditoria (apenas Master/Coordenador).
     */
    public function index(): void
    {
        SessionManager::requireLogin();

        $user = SessionManager::getUser();
        $userId = (int)$user['id'];

        // Apenas Master ou Coordenador podem ver logs
        if (!$this->auth->isMasterOrCoordinator($userId)) {
            $_SESSION['error'] = 'Você não tem permissão para acessar a auditoria.';
            header('Location: /atlasware/public/dashboard');
            exit;
        }

        // Você pode adicionar filtros aqui no futuro (ex: por ação, por usuário, por data)
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $logs = $this->logModel->findAll($limit);

        $title = 'Auditoria do Sistema';
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        ob_start();
        require __DIR__ . '/../Views/audit/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Exibe detalhes de um log específico (opcional).
     */
    public function show(int $id): void
    {
        SessionManager::requireLogin();

        $user = SessionManager::getUser();
        $userId = (int)$user['id'];

        if (!$this->auth->isMasterOrCoordinator($userId)) {
            $_SESSION['error'] = 'Você não tem permissão para acessar a auditoria.';
            header('Location: /atlasware/public/dashboard');
            exit;
        }

        $log = $this->logModel->findById($id);

        if (!$log) {
            $_SESSION['error'] = 'Log não encontrado.';
            header('Location: /atlasware/public/admin/audit');
            exit;
        }

        $title = 'Detalhes do Log #' . $id;
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        ob_start();
        require __DIR__ . '/../Views/audit/show.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }
}