<?php
namespace App\Controllers;

use App\Core\SessionManager;

class DashboardController {
    private function render(string $viewPath, array $vars = []) {
        extract($vars, EXTR_SKIP);

        ob_start();
        include __DIR__ . "/../Views/{$viewPath}";
        $content = ob_get_clean();

        $title = $vars['title'] ?? 'Atlasware - Controle de Acesso';
        include __DIR__ . '/../Views/layout/base.php';
    }

    public function index() {
        SessionManager::start();

        if (!SessionManager::isLoggedIn()) {
            echo "Usuário não está logado, redirecionando...";
            exit();
        }

        $user = SessionManager::getUser();

        $this->render('dashboard.php', [
            'title' => 'Dashboard',
            'user' => $user
        ]);
    }
}