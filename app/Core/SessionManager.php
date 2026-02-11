<?php
// app/Core/SessionManager.php
namespace App\Core;

class SessionManager {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Ajuste path para o seu basePath, se necessário
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/', // ou '/atlasware/public' se quiser restringir
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function login(array $userData) {
        self::start();
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['user_name'] = $userData['name'];
        $_SESSION['user_roles'] = $userData['roles'] ?? [];
        $_SESSION['user_teams'] = $userData['teams'] ?? [];
        $_SESSION['is_logged_in'] = true;
        $_SESSION['last_activity'] = time();
    }

    public static function logout() {
        self::start();
        session_unset();
        session_destroy();
    }

    public static function isLoggedIn() {
        self::start();
        return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
    }

    public static function checkSessionTimeout($timeout = 1800) {
        self::start();
        if (self::isLoggedIn()) {
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
                self::logout();
                return true;
            }
            $_SESSION['last_activity'] = time();
        }
        return false;
    }

    public static function getUser() {
        self::start();
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'roles' => $_SESSION['user_roles'],
                'teams' => $_SESSION['user_teams']
            ];
        }
        return null;
    }

    /**
     * Garante que o usuário está logado.
     * Se não estiver, redireciona para o login.
     */
    public static function requireLogin(): void {
        self::start();
        if (!self::isLoggedIn()) {
            // Pega o basePath dinamicamente
            $scriptName = $_SERVER['SCRIPT_NAME'];
            $basePath = str_replace('/index.php', '', $scriptName);
            
            header('Location: ' . $basePath . '/login');
            exit;
        }
    }
}