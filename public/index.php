<?php
// public/index.php

require_once __DIR__ . '/../app/Config/bootstrap.php';

use App\Core\SessionManager;
use App\Controllers\AuthController;
use App\Controllers\RoleController;
use App\Controllers\TeamController;
use App\Controllers\DashboardController;
use App\Services\AuthorizationService;
use App\Controllers\AccessController;
use App\Controllers\AccessRequestController;
use App\Controllers\UserAdminController;
use App\Controllers\AuditController;
use App\Controllers\AccessDetailsController;
use App\Controllers\TabsController;

SessionManager::start();
SessionManager::checkSessionTimeout();

// Normalize script dir / base path
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']); // ex: /atlasware/public/index.php
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/'); // ex: /atlasware/public or /

// Use '' quando estiver na raiz para facilitar concatenações
$basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : $scriptDir;

// Obtem a URI sem query string
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = str_replace('\\', '/', $requestUri);

// Remove o basePath do início da requestUri apenas se estiver presente
if ($basePath !== '' && strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

// Normaliza múltiplas barras, remove trailing slash (exceto se for só "/")
$path = preg_replace('#/+#', '/', $path);
$path = rtrim($path, '/');
if ($path === '') $path = '/';

// Método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Define constante útil
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath);
}

// Debug para logs — útil em produção por alguns minutos
error_log("BASE_PATH = " . (BASE_PATH === '' ? '[empty]' : BASE_PATH));
error_log("REQUEST_URI = $requestUri");
error_log("Rota requisitada: $path");

// === ROTAS DE AUTENTICAÇÃO ===
if ($path === '/login' || $path === '/login/') {
    $controller = new AuthController();
    $controller->login();
    exit;
}

if ($path === '/logout') {
    $controller = new AuthController();
    $controller->logout();
    exit;
}

// === DASHBOARD ===
if ($path === '/dashboard' || $path === '/dashboard/') {
    if (!SessionManager::isLoggedIn()) {
        header('Location: ' . $basePath . '/login');
        exit;
    }
    $controller = new DashboardController();
    $controller->index();
    exit;
}

// === ROTAS DE ROLES ===
// Listar roles
if ($path === '/admin/roles' || $path === '/admin/roles/') {
    $controller = new RoleController();
    $controller->index();
    exit;
}

// Formulário de criação
if ($path === '/admin/roles/create') {
    $controller = new RoleController();
    $controller->createForm();
    exit;
}

// Salvar nova role
if ($path === '/admin/roles/store') {
    $controller = new RoleController();
    $controller->store();
    exit;
}

// Formulário de edição
if (preg_match('#^/admin/roles/(\d+)/edit$#', $path, $matches)) {
    $controller = new RoleController();
    $controller->editForm((int)$matches[1]);
    exit;
}

// Atualizar role
if (preg_match('#^/admin/roles/(\d+)/update$#', $path, $matches)) {
    $controller = new RoleController();
    $controller->update((int)$matches[1]);
    exit;
}

// Deletar role
if (preg_match('#^/admin/roles/(\d+)/delete$#', $path, $matches)) {
    $controller = new RoleController();
    $controller->delete((int)$matches[1]);
    exit;
}

// === NOVAS ROTAS - Permissões por Cargo ===
// Tela de gerenciamento de permissões
if (preg_match('#^/admin/roles/(\d+)/permissions$#', $path, $matches)) {
    $controller = new RoleController();
    $controller->permissions((int)$matches[1]);
    exit;
}

// Salvar permissões (POST)
if (preg_match('#^/admin/roles/(\d+)/permissions/save$#', $path, $matches)) {
    $controller = new RoleController();
    $controller->savePermissions((int)$matches[1]);
    exit;
}

// === ROTAS DE TEAMS ===
// Listar teams
if ($path === '/admin/teams' || $path === '/admin/teams/') {
    $controller = new TeamController();
    $controller->index();
    exit;
}

// Formulário de criação
if ($path === '/admin/teams/create') {
    $controller = new TeamController();
    $controller->create();
    exit;
}

// Salvar novo team
if ($path === '/admin/teams/store') {
    $controller = new TeamController();
    $controller->store();
    exit;
}

// Formulário de edição
if (preg_match('#^/admin/teams/(\d+)/edit$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->edit((int)$matches[1]);
    exit;
}

// Atualizar team
if (preg_match('#^/admin/teams/(\d+)/update$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->update((int)$matches[1]);
    exit;
}

// Deletar team
if (preg_match('#^/admin/teams/(\d+)/delete$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->delete((int)$matches[1]);
    exit;
}

// Gerenciar permissões do team
if (preg_match('#^/admin/teams/(\d+)/permissions$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->permissions((int)$matches[1]);
    exit;
}

// Salvar permissões do team
if (preg_match('#^/admin/teams/(\d+)/permissions/save$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->savePermissions((int)$matches[1]);
    exit;
}

// Gerenciar membros do team
if (preg_match('#^/admin/teams/(\d+)/members$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->members((int)$matches[1]);
    exit;
}

// Adicionar membro ao team
if (preg_match('#^/admin/teams/(\d+)/members/add$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->addMember((int)$matches[1]);
    exit;
}

// Remover membro do team
if (preg_match('#^/admin/teams/(\d+)/members/(\d+)/remove$#', $path, $matches)) {
    $controller = new TeamController();
    $controller->removeMember((int)$matches[1], (int)$matches[2]);
    exit;
}

// === VISÃO GERAL DE ACESSO ===
if ($path === '/admin/access' || $path === '/admin/access/') {
    $controller = new AccessController();
    $controller->index();
    exit;
}

// === GESTÃO DE USUÁRIOS ===
// Listar usuários
if ($path === '/admin/users' || $path === '/admin/users/') {
    $controller = new UserAdminController();
    $controller->index();
    exit;
}

// Editar cargos de um usuário
if (preg_match('#^/admin/users/(\d+)/roles$#', $path, $matches)) {
    $controller = new UserAdminController();
    $controller->editRoles((int)$matches[1]);
    exit;
}

// Atualizar cargos de um usuário
if (preg_match('#^/admin/users/(\d+)/roles/update$#', $path, $matches)) {
    $controller = new UserAdminController();
    $controller->updateRoles((int)$matches[1]);
    exit;
}

// Visualizar detalhes de um usuário
if (preg_match('#^/admin/users/(\d+)$#', $path, $matches)) {
    $controller = new UserAdminController();
    $controller->show((int)$matches[1]);
    exit;
}

// === SOLICITAÇÕES DE ACESSO (USUÁRIO) ===

// Listar minhas solicitações
if ($path === '/requests' || $path === '/requests/') {
    $controller = new AccessRequestController();
    $controller->index();
    exit;
}

// Formulário de nova solicitação
if ($path === '/requests/create') {
    $controller = new AccessRequestController();
    $controller->create();
    exit;
}

// Salvar solicitação
if ($path === '/requests/store') {
    $controller = new AccessRequestController();
    $controller->store();
    exit;
}

// === ADMINISTRAÇÃO DE SOLICITAÇÕES (APROVADORES) ===

// Listar solicitações pendentes
if ($path === '/admin/requests' || $path === '/admin/requests/') {
    $controller = new AccessRequestController();
    $controller->adminIndex();
    exit;
}

// Processar aprovação/rejeição (POST)
if (preg_match('#^/admin/requests/(\d+)/process$#', $path, $matches)) {
    $controller = new AccessRequestController();
    $controller->process((int)$matches[1]);
    exit;
}

// === AUDITORIA ===
// Listar logs de auditoria
if ($path === '/admin/audit' || $path === '/admin/audit/') {
    $controller = new AuditController();
    $controller->index();
    exit;
}

// Visualizar detalhes de um log específico (opcional, se você quiser criar depois)
if (preg_match('#^/admin/audit/(\d+)$#', $path, $matches)) {
    $controller = new AuditController();
    $controller->show((int)$matches[1]);
    exit;
}

// === VISÃO GERAL DE ACESSO ===
if ($path === '/admin/access' || $path === '/admin/access/') {
    $controller = new AccessController();
    $controller->index();
    exit;
}

// Rota 1: Hierarquia Humana (id_lider)
if ($path === '/admin/organograma' || $path === '/admin/organograma/') {
    $controller = new AccessController();
    $controller->organogramaHumano(); 
    exit;
}

// Rota 2: Estrutura de Times (parent_team_id)
if ($path === '/admin/teams/structure' || $path === '/admin/teams/structure/') {
    $controller = new AccessController();
    $controller->organogramaTimes(); // O método que você já tinha feito antes
    exit;
}

// Atualizar líder de um usuário (AJAX/POST)
if ($path === '/admin/users/update-leader') {
    $controller = new AccessController();
    $controller->updateLeader();
    exit;
}

// GET: buscar permissões para o modal
if ($method === 'GET' && $path === '/admin/users/get-permissions') {
    $controller = new UserAdminController();
    $controller->getPermissions();
    exit;
}

// POST: salvar permissões do modal
if ($method === 'POST' && $path === '/admin/users/update-permissions') {
    $controller = new AccessController();
    $controller->updateUserPermissionsJson();
    exit;
}

// POST: atualizar líder via formulário de detalhes
if ($method === 'POST' && preg_match('#^/admin/users/(\d+)/update-leader$#', $path, $m)) {
    $userId = (int)$m[1];
    $controller = new AccessController();
    $controller->updateLeaderFromForm($userId);
    exit;
}

// GET: Detalhes do usuário
if ($method === 'GET' && $path === '/admin/organograma/user-details') {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    AccessDetailsController::getUserDetails($userId);
    exit;
}

// === AJAX: Buscar árvore do organograma por status ===
if ($method === 'GET' && $path === '/admin/organograma/fetch-tree') {
    $status = $_GET['status'] ?? 'active';
    $controller = new AccessController();
    $controller->fetchOrganogramaTree($status);
    exit;
}

// POST: Ativar permissões
if ($method === 'POST' && ($path === '/admin/access-details/activatePermissions' || $path === '/admin/organograma/activate-permissions')) {
    AccessDetailsController::activatePermissions();
    exit;
}

// POST: Desativar permissões
if ($method === 'POST' && ($path === '/admin/access-details/deactivatePermissions' || $path === '/admin/organograma/deactivate-permissions')) {
    AccessDetailsController::deactivatePermissions();
    exit;
}

// === OBRAS / CIDADES - NOVOS ENDPOINTS PARA LISTAGEM E ATUALIZAÇÃO ===
// GET: lista/pesquisa de obras (ajax)
if ($method === 'GET' && ($path === '/admin/obras/ajaxList' || $path === '/admin/obras/ajaxList/')) {
    \App\Controllers\ObraController::ajaxList();
    exit;
}

// POST: atualizar obras associadas ao usuário
if ($method === 'POST' && ($path === '/admin/obras/updateUserObras' || $path === '/admin/obras/updateUserObras/')) {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = isset($input['userId']) ? (int)$input['userId'] : 0;
    $obraIds = $input['obraIds'] ?? [];
    $resp = \App\Controllers\ObraController::updateUserObras($userId, $obraIds);
    echo json_encode($resp);
    exit;
}

// GET: lista/pesquisa de cidades (ajax)
if ($method === 'GET' && ($path === '/admin/cidades/ajaxList' || $path === '/admin/cidades/ajaxList/')) {
    if (method_exists('\App\Controllers\CidadeController', 'ajaxList')) {
        \App\Controllers\CidadeController::ajaxList();
        exit;
    }
    // fallback: usar getCidadesForUser para retornar todas (sem paginação)
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $q = isset($_GET['q']) ? trim($_GET['q']) : null;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'items' => \App\Controllers\CidadeController::getCidadesForUser($userId, $q)]);
    exit;
}

// POST: atualizar cidades associadas ao usuário
if ($method === 'POST' && ($path === '/admin/cidades/updateUserCidades' || $path === '/admin/cidades/updateUserCidades/')) {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = isset($input['userId']) ? (int)$input['userId'] : 0;
    $cidadeIds = $input['cidadeIds'] ?? [];
    $resp = \App\Controllers\CidadeController::updateUserCidades($userId, $cidadeIds);
    echo json_encode($resp);
    exit;
}

// === TABS (ABAS) - NOVO ENDPOINT PARA LISTAGEM ===
if ($method === 'GET' && ($path === '/admin/tabs/ajaxList' || $path === '/admin/tabs/ajaxList/')) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Precisamos da conexão PDO que geralmente está no bootstrap ou global
    // Ajuste conforme sua variável de conexão (ex: $pdo ou App\Core\Database::getConnection())
    global $pdo; 
    
    $tabsCtrl = new TabsController($pdo);
    
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $nivel  = isset($_GET['nivel']) ? $_GET['nivel'] : null;

    try {
        if ($userId) {
            $list = $tabsCtrl->getTabsForUser($userId);
        } else {
            $list = $tabsCtrl->getTabsForNivel($nivel ?? '');
        }
        echo json_encode(['success' => true, 'tabs' => $list]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === AJAX: Buscar subárvore a partir de um líder específico ===
if ($method === 'GET' && $path === '/admin/organograma/fetch-leader-tree') {
    $controller = new AccessController();
    $controller->fetchOrganogramaTreeFromLeader();
    exit;
}

// === ROTA PADRÃO (FALLBACK) ===
if (SessionManager::isLoggedIn()) {
    header('Location: ' . $basePath . '/dashboard');
} else {
    header('Location: ' . $basePath . '/login');
}
exit;