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
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 text-primary mb-1">Bem-vindo ao Atlasware</h1>
                <p class="text-muted">Olá, <strong><?= htmlspecialchars($user['name']) ?></strong>. O que deseja gerenciar hoje?</p>
            </div>
        </div>
    </div>

    <!-- Card Solicitações (NOVO - Para todos os usuários) -->
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-warning-subtle text-warning p-3 rounded-circle">
                        <i class="bi bi-send fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Solicitações</h5>
                <p class="card-text text-muted small">
                    Peça acesso a novos times ou cargos e acompanhe o status.
                </p>
                <a href="<?= BASE_PATH ?>/requests" class="btn btn-outline-warning w-100">Acessar</a>
            </div>
        </div>
    </div>

    <!-- Card Aprovar Acessos (NOVO - Apenas Master/Coordenador) -->
    <?php if ($auth->isMasterOrCoordinator($userId)): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-success-subtle text-success p-3 rounded-circle">
                        <i class="bi bi-check2-square fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Aprovar Acessos</h5>
                <p class="card-text text-muted small">
                    Analise e aprove pedidos de acesso pendentes de usuários.
                </p>
                <a href="<?= BASE_PATH ?>/admin/requests" class="btn btn-outline-success w-100">Gerenciar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Roles -->
    <?php if ($auth->canManageRoles()): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-person-badge fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Cargos</h5>
                <p class="card-text text-muted small">Gerencie funções, níveis hierárquicos e permissões base.</p>
                <a href="<?= BASE_PATH ?>/admin/roles" class="btn btn-outline-primary w-100">Acessar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Teams -->
    <?php if ($auth->canManageTeams()): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-people fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Times</h5>
                <p class="card-text text-muted small">Organize a hierarquia de times e lideranças.</p>
                <a href="<?= BASE_PATH ?>/admin/teams" class="btn btn-outline-primary w-100">Acessar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Acesso Geral -->
    <?php if ($auth->canViewAccessOverview()): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-dark text-white p-3 rounded-circle">
                        <i class="bi bi-diagram-3 fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Visão de Acesso</h5>
                <p class="card-text text-muted small">Visualize a hierarquia Master > Coordenação > Gerentes.</p>
                <a href="<?= BASE_PATH ?>/admin/access" class="btn btn-outline-dark w-100">Acessar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Auditoria (Atualizado) -->
    <?php if ($auth->isMasterOrCoordinator($user['id'])): ?>
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="badge bg-secondary-subtle text-secondary p-3 rounded-circle">
                            <i class="bi bi-shield-check fs-3"></i>
                        </span>
                    </div>
                    <h5 class="card-title">Auditoria</h5>
                    <p class="card-text text-muted small">Histórico completo de ações e alterações de acessos.</p>
                    <a href="<?= BASE_PATH ?>/admin/audit" class="btn btn-outline-secondary w-100">Acessar</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($auth->canViewAccessOverview()): ?>
        <!-- Card Organograma Humano -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="badge bg-info-subtle text-info p-3 rounded-circle">
                            <i class="bi bi-person-lines-fill fs-3"></i>
                        </span>
                    </div>
                    <h5 class="card-title">Hierarquia de Liderança</h5>
                    <p class="card-text text-muted small">Visualize quem responde a quem na estrutura de comando.</p>
                    <a href="<?= BASE_PATH ?>/admin/organograma" class="btn btn-outline-info w-100">Visualizar</a>
                </div>
            </div>
        </div>

        <!-- Card Estrutura por Times -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <span class="badge bg-primary-subtle text-primary p-3 rounded-circle">
                            <i class="bi bi-diagram-3 fs-3"></i>
                        </span>
                    </div>
                    <h5 class="card-title">Estrutura por Áreas</h5>
                    <p class="card-text text-muted small">Visualize a divisão da empresa por times e sub-times.</p>
                    <a href="<?= BASE_PATH ?>/admin/teams/structure" class="btn btn-outline-primary w-100">Visualizar</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>