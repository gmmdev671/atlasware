<?php
// app/Views/access/team_permissions.php
$base = defined('BASE_PATH') ? BASE_PATH : '/atlasware/public';
$defaultAllowedTill = $defaultAllowedTill ?? null;

// Agrupa permissões por aba
$grouped = [];
if (!empty($allPermissions)) {
    foreach ($allPermissions as $p) {
        $tab = $p['tab_name'] ?? 'Global / Outros';
        if (!isset($grouped[$tab])) $grouped[$tab] = [];
        $grouped[$tab][] = $p;
    }
}
?>
<div class="access-page">

    <div class="mb-3">
        <a href="<?= $base ?>/dashboard" class="text-white-50 small text-decoration-none">← Voltar para Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary fw-bold">Permissões do Time: <?= htmlspecialchars($team['name']) ?></h5>
            <a href="<?= $base ?>/admin/teams" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>

        <div class="card-body">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if (isset($this->auth) && $this->auth->isMasterOrCoordinatorCurrent()): ?>
                <div class="alert alert-info mb-3">
                    <strong>Acesso elevado (Master/Coordenador):</strong>
                    Você vê e pode alterar todas as permissões. As configurações de "até qual nível" são aplicadas para delegações feitas por usuários com nível inferior.
                </div>
            <?php endif; ?>

            <form action="<?= $base ?>/admin/teams/<?= (int)$team['id'] ?>/permissions/save" method="POST">
                <div class="mb-3 d-flex gap-3 align-items-center">
                    <label for="global_allowed_till" class="form-label fw-semibold mb-0 me-2">Valor padrão para "Até qual nível":</label>
                    <select id="global_allowed_till" class="form-select w-auto" aria-label="Padrão allowed till">
                        <option value="">Sem limite (até qualquer nível)</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<​?= (int)$role['id'] ?>" <?= ((string)$defaultAllowedTill === (string)$role['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="button" class="btn btn-sm btn-outline-secondary" id="applyAllBtn">Aplicar a todas</button>

                    <small class="text-muted ms-3">Define o valor padrão que será sugerido nos selects individuais. Você pode sobrescrever por permissão.</small>
                </div>

                <hr>

                <?php if (empty($grouped)): ?>
                    <p class="text-muted small mb-0">Nenhuma permissão cadastrada.</p>
                <?php else: ?>
                    <?php $tabIndex = 0; ?>
                    <?php foreach ($grouped as $tabName => $perms): ?>
                        <?php $tabIndex++; ?>
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="fw-bold">
                                    <i class="bi bi-folder2-open me-2"></i> <?= htmlspecialchars($tabName) ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted">Ações:</small>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllInTab(<?= $tabIndex ?>, true)">Marcar tudo</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllInTab(<?= $tabIndex ?>, false)">Desmarcar tudo</button>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="applySelectToTab(<?= $tabIndex ?>)">Aplicar padrão da seção</button>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="row gx-2 gy-2">
                                    <?php foreach ($perms as $perm): ?>
                                        <?php
                                            $pid = (int)($perm['permission_id'] ?? $perm['id'] ?? 0);
                                            $pname = $perm['permission_name'] ?? $perm['name'] ?? '';
                                            $assigned = !empty($perm['assigned']) && (int)$perm['assigned'] === 1;
                                            $allowedForThis = array_key_exists('allowed_till_role_id', $perm) ? $perm['allowed_till_role_id'] : null;
                                            // If no per-permission allowed set, fall back to defaultAllowedTill
                                            $initialAllowed = $allowedForThis !== null ? $allowedForThis : $defaultAllowedTill;
                                        ?>
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <div class="d-flex align-items-start gap-3 border rounded p-2">
                                                <div class="form-check flex-grow-1">
                                                    <input class="form-check-input perm-checkbox" 
                                                           type="checkbox" 
                                                           name="permissions[]" 
                                                           value="<​?= $pid ?>" 
                                                           id="perm_<?= $pid ?>"
                                                           data-tab-index="<​?= $tabIndex ?>"
                                                           <?= $assigned ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold" for="perm_<?= $pid ?>">
                                                        <?= htmlspecialchars($pname) ?>
                                                    </label>

                                                    <div class="small text-muted mt-1">
                                                        <label class="form-label small mb-0">Delegável até:</label>
                                                        <select name="allowed[<?= $pid ?>]" class="form-select form-select-sm mt-1 perm-allowed-select" data-tab-index="<​?= $tabIndex ?>">
                                                            <option value="" <?= ($initialAllowed === null || $initialAllowed === '') ? 'selected' : '' ?>>Sem limite (até qualquer nível)</option>
                                                            <?php foreach ($roles as $role): ?>
                                                                <option value="<​?= (int)$role['id'] ?>" <?= ((string)$initialAllowed === (string)$role['id']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($role['name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary text-white px-4">Salvar Permissões</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Aplica o valor do select global para todos os selects individuais
    document.getElementById('applyAllBtn').addEventListener('click', function () {
        const val = document.getElementById('global_allowed_till').value;
        document.querySelectorAll('.perm-allowed-select').forEach(function (sel) {
            sel.value = val;
        });
    });

    // Aplica o valor do select global apenas para uma determinada aba (seção)
    function applySelectToTab(tabIndex) {
        const val = document.getElementById('global_allowed_till').value;
        document.querySelectorAll('.perm-allowed-select[data-tab-index="' + tabIndex + '"]').forEach(function (sel) {
            sel.value = val;
        });
    }

    // Marcar/desmarcar todos os checkboxes de uma aba
    function selectAllInTab(tabIndex, check) {
        document.querySelectorAll('.perm-checkbox[data-tab-index="' + tabIndex + '"]').forEach(function (cb) {
            cb.checked = !!check;
        });
    }
</script>