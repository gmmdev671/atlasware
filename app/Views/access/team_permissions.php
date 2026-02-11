<?php
// app/Views/access/team_permissions.php
?>
<div class="access-page">

    <div class="mb-3">
        <a href="/atlasware/public/dashboard" class="text-white-50 small text-decoration-none">
            ← Voltar para Dashboard
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary fw-bold">
                Permissões do Time: <?= htmlspecialchars($team['name']) ?>
            </h5>
            <a href="/atlasware/public/admin/teams" class="btn btn-outline-secondary btn-sm">
                Voltar
            </a>
        </div>

        <div class="card-body">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form action="/atlasware/public/admin/teams/<?= $team['id'] ?>/permissions/save" method="POST">
                <div class="mb-3">
                    <label for="allowed_till_role_id" class="form-label fw-semibold">
                        Até qual nível hierárquico esta permissão pode ser delegada?
                    </label>
                    <select name="allowed_till_role_id" id="allowed_till_role_id" class="form-select">
                        <option value="">Sem limite (até qualquer nível)</option>
                        <?php foreach ($roles as $role): ?>
                            <?php
                            $selected = ($allowedTillRoleId == $role['id']) ? 'selected' : '';
                            ?>
                            <option value="<?= $role['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        Isso define o nível máximo para o qual essas permissões podem descer (ex: até Gerente, até Funcionário, etc.).
                    </small>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Permissões disponíveis</label>
                    <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($allPermissions)): ?>
                            <p class="text-muted small mb-0">Nenhuma permissão cadastrada.</p>
                        <?php else: ?>
                            <?php foreach ($allPermissions as $perm): ?>
                                <?php
                                $checked = in_array($perm['id'], $teamPermissionIds) ? 'checked' : '';
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="permissions[]" 
                                           value="<?= $perm['id'] ?>" 
                                           id="perm_<?= $perm['id'] ?>"
                                           <?= $checked ?>>
                                    <label class="form-check-label" for="perm_<?= $perm['id'] ?>">
                                        <?= htmlspecialchars($perm['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary text-white px-4">
                    Salvar Permissões
                </button>
            </form>
        </div>
    </div>
</div>