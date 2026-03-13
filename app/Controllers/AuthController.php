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

    // Substitua o método login() atual por este
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

            // Se precisa configurar senha, não cria sessão — direciona para reset
            if (!empty($result['requires_password_setup'])) {
                // usa o id retornado pelo model (já é o id em tb_users)
                $_SESSION['reset_user_id'] = (int)$result['id'];
                // opcional: guardar também nome/login para a view
                $_SESSION['reset_user_login'] = $result['login'] ?? null;
                $_SESSION['reset_user_nome']  = $result['nome'] ?? null;

                header('Location: ' . $basePath . '/auth/reset-password');
                exit();
            }

            // Caso normal: montar dados de sessão e autenticar
            $userData = [
                'id'    => $result['id'],
                'name'  => $result['nome'],
                'roles' => $result['roles'] ?? [],
                'teams' => $result['teams'] ?? [],
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
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        SessionManager::logout();
        header('Location: ' . $basePath . '/login');
        exit();
    }

    // Exibe o formulário de redefinição de senha
    public function showResetPasswordForm()
    {
        SessionManager::start();
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        $userId = $_SESSION['reset_user_id'] ?? null;
        if (!$userId) {
            // se o usuário não tem o flag, manda para login
            header('Location: ' . $basePath . '/login');
            exit();
        }

        // preenche valores para a view (pode usar nome/login se gravados)
        $this->render('auth/reset-password.php', [
            'title'    => 'Redefinir senha',
            'basePath' => $basePath,
            'user_id'  => $userId,
            'user_name'=> $_SESSION['reset_user_nome'] ?? null,
            'user_login'=> $_SESSION['reset_user_login'] ?? null,
            'errors'   => []
        ]);
    }

    // Trata o POST do formulário de redefinição
    public function handleResetPassword()
    {
        SessionManager::start();
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        $userId = $_SESSION['reset_user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . $basePath . '/login');
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showResetPasswordForm();
            return;
        }

        $csrfPosted = $_POST['csrf_token'] ?? '';
        $csrfSession = $_SESSION['csrf_token'] ?? '';
        // Garantir que ambos existam antes de usar hash_equals
        if (empty($csrfSession) || empty($csrfPosted) || !hash_equals($csrfSession, $csrfPosted)) {
            $this->render('auth/reset-password.php', [
                'title'    => 'Redefinir senha',
                'basePath' => $basePath,
                'user_id'  => $userId,
                'errors'   => ['Token inválido. Por favor, recarregue a página e tente novamente.']
            ]);
            return;
        }

        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        $errors = [];

        // Validações mínimas
        if ($password !== $passwordConfirm) {
            $errors[] = 'As senhas não coincidem.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Senha muito curta. Use ao menos 8 caracteres.';
        }
        // Você pode adicionar checagem de complexidade, blacklist, etc.

        if (!empty($errors)) {
            $this->render('auth/reset-password.php', [
                'title'    => 'Redefinir senha',
                'basePath' => $basePath,
                'user_id'  => $userId,
                'errors'   => $errors
            ]);
            return;
        }

        // Salva a nova senha
        $ok = $this->userModel->setPassword((int)$userId, $password);
        if (!$ok) {
            $this->render('auth/reset-password.php', [
                'title'    => 'Redefinir senha',
                'basePath' => $basePath,
                'user_id'  => $userId,
                'errors'   => ['Erro ao salvar a nova senha. Tente novamente.']
            ]);
            return;
        }

        // limpa os flags temporários
        unset($_SESSION['reset_user_id'], $_SESSION['reset_user_login'], $_SESSION['reset_user_nome']);
        unset($_SESSION['csrf_token']);

        // Loga o usuário automaticamente: montar userData
        $userRow = $this->userModel->findWithRoles((int)$userId);
        if (!$userRow) {
            header('Location: ' . $basePath . '/login');
            exit();
        }

        $roles = $this->userModel->getUserRoleNames((int)$userId);
        $teams = $this->userModel->getUserTeamsWithRoleNames((int)$userId);

        $sessionData = [
            'id'    => (int)$userId,
            'name'  => $userRow['name'] ?? '',
            'roles' => $roles,
            'teams' => $teams
        ];

        SessionManager::login($sessionData);

        // Opcional: gravar auditoria que usuário redefiniu a senha

        header('Location: ' . $basePath . '/dashboard');
        exit();
    }
}