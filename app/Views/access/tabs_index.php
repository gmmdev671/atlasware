<?php
$base = defined('BASE_PATH') ? BASE_PATH : '/atlasware/public';
?>
<div class="access-page">
    <div class="mb-3">
        <a href="<?= $base ?>/dashboard" class="text-white-50 small text-decoration-none">← Voltar para Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary fw-bold">Gestão de Abas (Módulos)</h5>
            <a href="<?= $base ?>/admin/tabs/create" class="btn btn-primary btn-sm text-white fw-bold px-3">
                Nova Aba
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success m-3 mb-0"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger m-3 mb-0"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nome da Aba</th>
                            <th>Key Name</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tabs)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Nenhuma aba cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tabs as $t): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($t['nome']) ?></td>
                                    <td><code class="small text-primary"><?= htmlspecialchars($t['key_name'] ?? '—') ?></code></td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="<?= $base ?>/admin/tabs/<?= $t['id'] ?>/edit" class="btn btn-outline-secondary action-btn">Editar</a>
                                            <form action="<?= $base ?>/admin/tabs/<?= $t['id'] ?>/delete" method="POST" class="m-0" onsubmit="return confirm('Excluir esta aba? Isso só será possível se não houver permissões vinculadas.')">
                                                <button type="submit" class="btn btn-primary text-white action-btn">Excluir</button>
                                            </form>
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