<?php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Models\User;

class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    private function render(string $viewPath, array $vars = []) {
        extract($vars, EXTR_SKIP);
        ob_start();
        include __DIR__ . "/../Views/{$viewPath}";
        $content = ob_get_clean();
        $title = $vars['title'] ?? 'Login';
        include __DIR__ . '/../Views/layout/base.php';
    }

    public function login()
    {
        SessionManager::start();

        // Calcula o basePath a partir da constante definida no index.php
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        // Se já estiver logado, redireciona para o dashboard
        if (SessionManager::isLoggedIn()) {
            header('Location: ' . $basePath . '/dashboard');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $login = $_POST['login'] ?? '';
            $senha = $_POST['password'] ?? '';

            $result = $this->userModel->login($login, $senha);

            if (isset($result['error'])) {
                $this->render('auth/login.php', [
                    'error'    => $result['error'],
                    'title'    => 'Login',
                    'basePath' => $basePath,
                ]);
                return;
            }

            $userData = [
                'id'    => $result['id'],
                'name'  => $result['nome'],
                'roles' => $result['roles'],
                'teams' => $result['teams'],
            ];

            SessionManager::login($userData);

            header('Location: ' . $basePath . '/dashboard');
            exit();
        }

        // GET simples (primeiro acesso à tela)
        $this->render('auth/login.php', [
            'title'    => 'Login',
            'basePath' => $basePath,
        ]);
    }

    public function logout() {
        SessionManager::logout();
        header('Location: /atlasware/public/login');
        exit();
    }
}