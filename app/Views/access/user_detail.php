<?php
// app/Views/access/user_detail.php
$currentRoleIds = $user['role_ids'] ?? [];
?>
<div class="access-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-white mb-0">Detalhes do Usuário</h4>
        <a href="/atlasware/public/admin/users" class="text-white-50 small text-decoration-none">
            ← Voltar para lista de usuários
        </a>
    </div>

    <div class="row g-3">
        <!-- COLUNA ESQUERDA: Dados, Cargos Globais e LÍDER -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary fw-bold"><?= htmlspecialchars($user['name']) ?></h5>
                    <small class="text-muted">
                        ID: <?= $user['id'] ?>
                        <?php if (!empty($user['email'])): ?>
                            • <?= htmlspecialchars($user['email']) ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="card-body">
                    <!-- Cargos Globais -->
                    <h6 class="fw-semibold mb-2">Cargos Globais</h6>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <?php if (empty($currentRoleIds)): ?>
                            <span class="text-muted small">Sem cargos globais definidos.</span>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <?php if (in_array($role['id'], $currentRoleIds, true)): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= htmlspecialchars($role['name']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="/atlasware/public/admin/users/<?= $user['id'] ?>/roles" class="btn btn-outline-primary btn-sm w-100 mb-4">
                        Gerenciar cargos
                    </a>

                    <!-- GESTÃO DE LÍDER (Nova Seção) -->
                    <hr>
                    <h6 class="fw-semibold mb-2 mt-3">Hierarquia Humana</h6>
                    <form action="/atlasware/public/admin/users/<?= $user['id'] ?>/update-leader" method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Líder Direto</label>
                            <select name="id_lider" class="form-select form-select-sm">
                                <option value="">(Sem Líder / Topo da Cadeia)</option>
                                <?php foreach ($allUsers as $u): ?>
                                    <?php if ($u['id'] != $user['id']): ?>
                                        <option value="<?= $u['id'] ?>" <?= ($user['id_lider'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            Atualizar Líder
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- COLUNA DIREITA: Times e Permissões -->
        <div class="col-lg-8">
            <!-- Times -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary fw-bold">Times em que o usuário atua</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($userTeams)): ?>
                        <p class="text-muted small mb-0">Este usuário ainda não está associado a nenhum time.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Time</th>
                                        <th>Cargo no Time</th>
                                        <th>Permissões do Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userTeams as $ut): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ut['team_name']) ?></td>
                                            <td><?= htmlspecialchars($ut['role_name']) ?></td>
                                            <td>
                                                <?php 
                                                $teamPerms = $permissionsByTeam[$ut['team_id']]['permissions'] ?? [];
                                                foreach ($teamPerms as $pname): ?>
                                                    <span class="badge bg-light text-secondary border border-secondary-subtle">
                                                        <?= htmlspecialchars($pname) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Permissões Diretas (Sobrescritas via Organograma) -->
            <div class="card border-0 shadow-sm mb-3 border-start border-4 border-warning">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-warning fw-bold">Permissões Diretas (Override)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($directPermissions)): ?>
                        <p class="text-muted small mb-0">Nenhuma permissão forçada para este usuário.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($directPermissions as $pname): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                    <?= htmlspecialchars($pname) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-2">
                        Estas permissões foram atribuídas manualmente e ignoram as regras de cargo/time.
                    </small>
                </div>
            </div>

            <!-- Resumo Geral -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-success fw-bold">Resumo das Permissões Herdadas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($userPermissionsFlat)): ?>
                        <p class="text-muted small mb-0">Nenhuma permissão herdada via times.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($userPermissionsFlat as $pname): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <?= htmlspecialchars($pname) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>