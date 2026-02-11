<?php
// app/Views/access/user_roles_form.php

$currentRoleIds = $user['role_ids'] ?? [];
?>
<div class="access-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-white mb-0">
            Cargos do Usuário
        </h4>
        <a href="/atlasware/public/admin/users" class="text-white-50 small text-decoration-none">
            ← Voltar para lista de usuários
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 text-primary fw-bold">
                    <?= htmlspecialchars($user['name']) ?>
                </h5>
                <small class="text-muted">
                    ID: <?= $user['id'] ?><?= $user['email'] ? ' • ' . htmlspecialchars($user['email']) : '' ?>
                </small>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/atlasware/public/admin/users/<?= $user['id'] ?>/roles/update">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cargos / Nível hierárquico</label>
                    <div class="border rounded p-3">
                        <?php if (empty($roles)): ?>
                            <p class="text-muted small mb-0">Nenhum cargo cadastrado.</p>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <?php
                                $checked = in_array($role['id'], $currentRoleIds) ? 'checked' : '';
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="roles[]"
                                           value="<?= $role['id'] ?>"
                                           id="role_<?= $role['id'] ?>"
                                           <?= $checked ?>>
                                    <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                        <?= htmlspecialchars($role['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">
                        Estes cargos serão usados para posicionar o usuário na visão de acesso (Master, Coordenação, Gerente, Funcionário).
                    </small>
                </div>

                <button type="submit" class="btn btn-primary text-white px-4">
                    Salvar Cargos
                </button>
            </form>
        </div>
    </div>
</div>