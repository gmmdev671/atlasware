<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getBearerTokenFromHeader(): ?string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
    if (!$auth) return null;
    if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
        return $matches[1];
    }
    return null;
}

function getTokenFromRequest(): ?string {
    // 1) Authorization header
    $token = getBearerTokenFromHeader();
    if ($token) return $token;
    // 2) cookie
    if (!empty($_COOKIE['atlasware_jwt'])) return $_COOKIE['atlasware_jwt'];
    // 3) query param (opcional)
    if (!empty($_GET['token'])) return $_GET['token'];
    return null;
}

function validateJwtToken(string $token): ?array {
    try {
        $payload = (array) JWT::decode($token, new Key($GLOBALS['secretJWT'], 'HS256'));
        return $payload;
    } catch (Exception $e) {
        return null;
    }
}

/** Middleware simples para endpoints API */
function requireAuthJwt() {
    require_once __DIR__ . '/../vendor/autoload.php';
    $token = getTokenFromRequest();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Token ausente']);
        exit();
    }
    $payload = validateJwtToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido ou expirado']);
        exit();
    }
    // retorna payload para uso no endpoint
    return $payload;
}