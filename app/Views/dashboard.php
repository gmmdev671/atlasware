<?php
/**
 * @var \App\Services\AuthorizationService $auth
 * @var array $user Usuário logado
 * @var bool $isMaster
 * @var bool $isLeader
 * @var bool $canManageUsers
 * @var bool $canViewAudit
 * @var bool $canViewStructure
 */
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

    <!-- Card Solicitações (Para todos os usuários) -->
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-warning-subtle text-warning p-3 rounded-circle">
                        <i class="bi bi-send fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Solicitações</h5>
                <p class="card-text text-muted small">Peça acesso a novos times ou cargos e acompanhe o status.</p>
                <a href="<?= BASE_PATH ?>/requests" class="btn btn-outline-warning w-100">Acessar</a>
            </div>
        </div>
    </div>

    <!-- Card Aprovar Acessos (Master, Coordenador ou quem tem permissão de aprovar) -->
    <?php if ($isMaster || $auth->can($user['id'], 'approve_requests')): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-success-subtle text-success p-3 rounded-circle">
                        <i class="bi bi-check2-square fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Aprovar Acessos</h5>
                <p class="card-text text-muted small">Analise e aprove pedidos de acesso pendentes de sua equipe.</p>
                <a href="<?= BASE_PATH ?>/admin/requests" class="btn btn-outline-success w-100">Gerenciar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Gestão de Equipe (Visão de 4 Colunas - Liberado para quem tem subordinados ou permissão de visão) -->
    <?php if ($canViewStructure): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-dark text-white p-3 rounded-circle">
                        <i class="bi bi-diagram-3 fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Gestão de Equipe</h5>
                <p class="card-text text-muted small">Visualize e gerencie os acessos da sua estrutura de comando.</p>
                <a href="<?= BASE_PATH ?>/admin/access" class="btn btn-outline-dark w-100">Acessar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Configurações Globais (Master ou Coordenador) -->
    <?php if ($isMaster || $auth->isMasterOrCoordinator($user['id'])): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-gear fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Configurações Globais</h5>
                <p class="card-text text-muted small">Gerencie a estrutura base de Cargos, Times, Abas e Permissões do sistema.</p>

                <div class="d-flex gap-2">
                    <a href="<?= BASE_PATH ?>/admin/roles" class="btn btn-sm btn-outline-primary flex-fill">Cargos</a>
                    <a href="<?= BASE_PATH ?>/admin/teams" class="btn btn-sm btn-outline-primary flex-fill">Times</a>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <a href="<?= BASE_PATH ?>/admin/tabs" class="btn btn-sm btn-outline-primary flex-fill">Abas</a>
                    <a href="<?= BASE_PATH ?>/admin/permissions" class="btn btn-sm btn-outline-primary flex-fill">Permissões</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Auditoria -->
    <?php if ($canViewAudit): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-secondary-subtle text-secondary p-3 rounded-circle">
                        <i class="bi bi-shield-check fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Auditoria</h5>
                <p class="card-text text-muted small">Histórico completo de alterações de acessos.</p>
                <a href="<?= BASE_PATH ?>/admin/audit" class="btn btn-outline-secondary w-100">Acessar</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cards de Visualização de Estrutura (Hierarquia Humana e Áreas) -->
    <?php if ($canViewStructure): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <span class="badge bg-info-subtle text-info p-3 rounded-circle">
                        <i class="bi bi-person-lines-fill fs-3"></i>
                    </span>
                </div>
                <h5 class="card-title">Organograma</h5>
                <p class="card-text text-muted small">Visualize a hierarquia de liderança (quem responde a quem).</p>
                <a href="<?= BASE_PATH ?>/admin/organograma" class="btn btn-outline-info w-100">Visualizar</a>
            </div>
        </div>
    </div>

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