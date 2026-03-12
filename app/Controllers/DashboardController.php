<?php

namespace App\Controllers;

use App\Core\SessionManager;
use App\Services\AuthorizationService; // Importante

class DashboardController {
    private AuthorizationService $auth;

    public function __construct() {
        $this->auth = new AuthorizationService();
    }

    public function index() {
        SessionManager::start();
        if (!SessionManager::isLoggedIn()) {
            header("Location: " . BASE_PATH . "/login");
            exit();
        }

        $user = SessionManager::getUser();
        $userId = (int)$user['id'];

        // Agora o $this->auth está disponível
        $dashboardData = [
            'title' => 'Dashboard',
            'user' => $user,
            'isMaster' => $this->auth->isMaster($userId),
            'isLeader' => $this->auth->hasSubordinates($userId),
            'canManageUsers' => $this->auth->can($userId, 'manage_users'),
            'canViewAudit' => $this->auth->isMasterOrCoordinator($userId),
            'canViewStructure' => $this->auth->canViewAccessOverview() || $this->auth->hasSubordinates($userId)
        ];

        $this->render('dashboard.php', $dashboardData);
    }

    private function render(string $viewPath, array $vars = []) {
        extract($vars, EXTR_SKIP);
        // Adicionamos o auth nas variáveis da view para que os IFs da dashboard funcionem
        $auth = $this->auth; 

        ob_start();
        include __DIR__ . "/../Views/{$viewPath}";
        $content = ob_get_clean();

        $title = $vars['title'] ?? 'Atlasware - Controle de Acesso';
        include __DIR__ . '/../Views/layout/base.php';
    }
}