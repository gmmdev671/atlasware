<?php
// app/Views/access/permissions_index.php
$base = defined('BASE_PATH') ? BASE_PATH : '/atlasware/public';
?>
<div class="access-page">
    <div class="mb-3">
        <a href="<?= $base ?>/dashboard" class="text-white-50 small text-decoration-none">← Voltar para Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary fw-bold">Gestão de Permissões</h5>
            <?php if ($this->auth->isMasterOrCoordinatorCurrent()): ?>
                <a href="<?= $base ?>/admin/permissions/create" class="btn btn-primary btn-sm text-white fw-bold px-3">
                    Nova Permissão
                </a>
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
                            <th class="ps-4">Permissão</th>
                            <th class="text-center">Aba</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($permissions)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">Nenhuma permissão cadastrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($permissions as $perm): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($perm['name']) ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($perm['tab_name'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3">
                                                <?= htmlspecialchars($perm['tab_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <a href="<?= $base ?>/admin/permissions/<?= (int)$perm['id'] ?>/edit"
                                               class="btn btn-outline-secondary action-btn">Editar</a>

                                            <?php
                                            // Somente Master/Coordinator conseguem excluir (mesma regra do create)
                                            if ($this->auth->isMasterOrCoordinatorCurrent()): ?>
                                                <form action="<?= $base ?>/admin/permissions/<?= (int)$perm['id'] ?>/delete"
                                                      method="POST"
                                                      class="m-0"
                                                      onsubmit="return confirm('Tem certeza que deseja excluir esta permissão? Esta ação só é permitida se ela não estiver vinculada.');">
                                                    <button type="submit" class="btn btn-primary text-white action-btn">
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