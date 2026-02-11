<?php
require_once __DIR__ . '/../app/Config/bootstrap.php';

use App\Core\SessionManager;

SessionManager::start();

echo "Sessão ativa? " . (SessionManager::isLoggedIn() ? "Sim" : "Não") . "\n";
echo "Dados da sessão:\n";
print_r($_SESSION);