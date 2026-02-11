<?php
use App\Core\SessionManager;
use App\Services\AuthorizationService;

SessionManager::start();
$isLoggedIn = SessionManager::isLoggedIn();
$user = $isLoggedIn ? SessionManager::getUser() : null;

$userId = (is_array($user) && isset($user['id'])) ? (int)$user['id'] : null;

$auth = $isLoggedIn ? new AuthorizationService() : null;
$currentTitle = $title ?? '';
?>

<?php if ($isLoggedIn && $auth): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= $basePath ?>/dashboard">
                <i class="bi bi-shield-check"></i> Atlasware
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link<?= $currentTitle === 'Dashboard' ? ' active' : '' ?>" href="<?= $basePath ?>/dashboard">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>

                    <!-- Link de Solicitações (Acessível a todos os usuários logados) -->
                    <li class="nav-item">
                        <a class="nav-link<?= in_array($currentTitle, ['Minhas Solicitações', 'Nova Solicitação de Acesso']) ? ' active' : '' ?>" href="<?= $basePath ?>/requests">
                            <i class="bi bi-send"></i> Solicitações
                        </a>
                    </li>

                    <?php
                    $hasAnyAccessControl = ($auth->canViewAccessOverview() || $auth->canManageTeams() || $auth->canManageRoles() || $auth->canManageUsers());

                    $accessTitles = [
                        'Visão Geral de Acesso', 
                        'Hierarquia de Liderança', 
                        'Estrutura por Áreas', 
                        'Gestão de Times', 
                        'Gestão de Cargos', 
                        'Gestão de Usuários', 
                        'Gerenciar Solicitações'
                    ];

                    $isAccessActive = in_array($currentTitle, $accessTitles);
                    ?>

                    <?php if ($hasAnyAccessControl): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?= $isAccessActive ? ' active' : '' ?>" href="#" id="accessControlDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-shield-lock"></i> Controle de Acesso
                            </a>
                            <ul class="dropdown-menu shadow">
                                <?php if ($auth->canViewAccessOverview()): ?>
                                    <li><h6 class="dropdown-header">Visualização</h6></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>/admin/access"><i class="bi bi-eye"></i> Visão Geral (4 Colunas)</a></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>/admin/organograma"><i class="bi bi-person-lines-fill"></i> Hierarquia de Liderança</a></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>/admin/teams/structure"><i class="bi bi-diagram-3"></i> Estrutura por Áreas</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>

                                <?php if ($auth->canManageTeams()): ?>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>/admin/teams"><i class="bi bi-people"></i> Times</a></li>
                                <?php endif; ?>

                                <?php if ($auth->canManageRoles()): ?>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>/admin/roles"><i class="bi bi-shield"></i> Cargos</a></li>
                                <?php endif; ?>

                                <?php if ($auth->canManageUsers()): ?>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>/admin/users"><i class="bi bi-person-badge"></i> Usuários</a></li>
                                <?php endif; ?>

                                <!-- NOVO: Link de Auditoria (Apenas Master/Coordenador) -->
                                <?php if ($auth->isMasterOrCoordinator($userId)): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item<?= $currentTitle === 'Auditoria do Sistema' ? ' active' : '' ?>" href="<?= $basePath ?>/admin/audit">
                                            <i class="bi bi-shield-check"></i> Auditoria
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Link para Aprovar Solicitações -->
                                <?php if ($auth->isMasterOrCoordinator($userId)): ?>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>/admin/requests"><i class="bi bi-check2-square"></i> Aprovar Solicitações</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item text-danger" href="<?= $basePath ?>/logout"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
<?php endif; ?>