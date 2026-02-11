<?php
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Permissões do Cargo: <?= htmlspecialchars($role['name']) ?></h2>
        <a href="<?= BASE_PATH ?>/admin/roles" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= BASE_PATH ?>/admin/roles/<?= $role['id'] ?>/permissions/save">
                <div class="mb-3">
                    <label class="form-label fw-bold">Selecione as Permissões:</label>
                    
                    <?php if (empty($allPermissions)): ?>
                        <p class="text-muted">Nenhuma permissão cadastrada no sistema.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($allPermissions as $perm): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input 
                                            class="form-check-input" 
                                            type="checkbox" 
                                            name="permissions[]" 
                                            value="<?= $perm['id'] ?>"
                                            id="perm_<?= $perm['id'] ?>"
                                            <?= in_array($perm['id'], $rolePermissionIds) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="perm_<?= $perm['id'] ?>">
                                            <strong><?= htmlspecialchars($perm['name']) ?></strong>
                                            <?php if (!empty($perm['description'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($perm['description']) ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Permissões
                    </button>
                    <a href="<?= BASE_PATH ?>/admin/roles" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>