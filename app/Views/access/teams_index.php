<?php
// app/Views/access/teams_index.php
?>
<div class="access-page">
    
    <div class="mb-3">
        <a href="/atlasware/public/dashboard" class="text-white-50 small text-decoration-none">← Voltar para Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary fw-bold">Gestão de Times</h5>
            <?php if ($this->auth->isMasterOrCoordinatorCurrent()): ?>
                <button type="button" 
                        class="btn btn-primary btn-sm text-white fw-bold px-3"
                        onclick="window.location.href='/atlasware/public/admin/teams/create'">
                    Novo Time
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success m-3 mb-0">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger m-3 mb-0">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nome do Time</th>
                            <th class="text-center">Time Pai</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teams)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">Nenhum time cadastrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($team['name']) ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($team['parent_team_name'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3">
                                                <?= htmlspecialchars($team['parent_team_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4" style="vertical-align: middle;">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <a href="/atlasware/public/admin/teams/<?= $team['id'] ?>/permissions"
                                            class="btn btn-outline-primary action-btn">
                                                Permissões
                                            </a>

                                            <a href="/atlasware/public/admin/teams/<?= $team['id'] ?>/members"
                                            class="btn btn-outline-info action-btn">
                                                Membros
                                            </a>

                                            <button type="button" 
                                                    class="btn btn-outline-secondary action-btn"
                                                    onclick="window.location.href='/atlasware/public/admin/teams/<?= $team['id'] ?>/edit'">
                                                Editar
                                            </button>
                                            
                                            <?php if ($this->auth->isMasterOrCoordinatorCurrent()): ?>
                                                <form action="/atlasware/public/admin/teams/delete/<?= $team['id'] ?>" 
                                                    method="POST" 
                                                    class="m-0" 
                                                    onsubmit="return confirm('Tem certeza que deseja excluir este time?')">
                                                    <button type="submit" 
                                                            class="btn btn-primary text-white action-btn">
                                                        Excluir
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>