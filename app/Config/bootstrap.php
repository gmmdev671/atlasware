<?php
// app/Config/bootstrap.php

// 1) autoload do Composer (classes PSR-4)
require_once __DIR__ . '/../../vendor/autoload.php';

// 2) loader do .env (se estiver usando o loader custom)
require_once __DIR__ . '/env_loader.php';
$root = dirname(__DIR__, 2);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
loadEnvFile($envPath);

// 3) funções de BD (mantêm como arquivo de funções)
require_once __DIR__ . '/database.php'; // contém getDbConnection()

// 4) configuração/validação básica
$required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'secretJWT'];
foreach ($required as $key) {
    if (getenv($key) === false) {
        die("ERRO: Variável obrigatória {$key} não definida no .env ou no ambiente.");
    }
}

// 5) compatibilidade com código legado (se necessário)
$GLOBALS['secretJWT'] = getenv('secretJWT');