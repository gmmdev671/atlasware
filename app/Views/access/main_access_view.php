<?php
// app/Views/access/main_access_view.php

// Garantir que $columns exista, mesmo que vazio
$columns = $columns ?? [
    'master'      => [],
    'coordenador' => [],
    'gerente'     => [],
    'funcionario' => [],
    'sem_role'    => [],
];
?>
<div class="access-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-white mb-0">Visão Geral de Acesso</h4>
        <a href="/atlasware/public/dashboard" class="text-white-50 small text-decoration-none">
            ← Voltar para Dashboard
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body" style="background-color: #f5f7fb;">
            <div class="row g-3">
                <!-- Coluna Master -->
                <div class="col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-dark text-white py-2 text-center">
                            <strong>Master</strong>
                        </div>
                        <div class="card-body p-2" style="min-height: 200px; background-color: #111827;">
                            <?php if (empty($columns['master'])): ?>
                                <p class="text-white-50 small text-center mt-3">Nenhum usuário Master.</p>
                            <?php else: ?>
                                <?php foreach ($columns['master'] as $user): ?>
                                    <a href="/atlasware/public/admin/users/<?= $user['id'] ?>"
                                       class="text-decoration-none d-block mb-2">
                                        <div class="card bg-white border-0 shadow-sm hover-elevate"
                                             style="transition: box-shadow 0.15s ease, transform 0.15s ease;">
                                            <div class="card-body py-2 px-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="fw-semibold text-dark">
                                                        <?= htmlspecialchars($user['name']) ?>
                                                    </div>
                                                    <span class="text-muted" style="font-size: 0.7rem;">
                                                        ID: <?= $user['id'] ?>
                                                    </span>
                                                </div>

                                                <?php if (!empty($user['teams'])): ?>
                                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                                        <?php foreach ($user['teams'] as $teamInfo): ?>
                                                            <span class="badge bg-light text-primary border border-primary-subtle"
                                                                  style="font-size: 0.65rem; font-weight: 500;">
                                                                <?= htmlspecialchars($teamInfo) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Coluna Coordenação -->
                <div class="col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white py-2 text-center">
                            <strong>Coordenação</strong>
                        </div>
                        <div class="card-body p-2" style="min-height: 200px; background-color: #eff6ff;">
                            <?php if (empty($columns['coordenador'])): ?>
                                <p class="text-muted small text-center mt-3">Nenhum coordenador cadastrado.</p>
                            <?php else: ?>
                                <?php foreach ($columns['coordenador'] as $user): ?>
                                    <a href="/atlasware/public/admin/users/<?= $user['id'] ?>"
                                       class="text-decoration-none d-block mb-2">
                                        <div class="card bg-white border-0 shadow-sm hover-elevate"
                                             style="transition: box-shadow 0.15s ease, transform 0.15s ease;">
                                            <div class="card-body py-2 px-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="fw-semibold text-dark">
                                                        <?= htmlspecialchars($user['name']) ?>
                                                    </div>
                                                    <span class="text-muted" style="font-size: 0.7rem;">
                                                        ID: <?= $user['id'] ?>
                                                    </span>
                                                </div>

                                                <?php if (!empty($user['teams'])): ?>
                                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                                        <?php foreach ($user['teams'] as $teamInfo): ?>
                                                            <span class="badge bg-light text-primary border border-primary-subtle"
                                                                  style="font-size: 0.65rem; font-weight: 500;">
                                                                <?= htmlspecialchars($teamInfo) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Coluna Gerentes -->
                <div class="col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark py-2 text-center">
                            <strong>Gerentes</strong>
                        </div>
                        <div class="card-body p-2" style="min-height: 200px; background-color: #fffbeb;">
                            <?php if (empty($columns['gerente'])): ?>
                                <p class="text-muted small text-center mt-3">Nenhum gerente cadastrado.</p>
                            <?php else: ?>
                                <?php foreach ($columns['gerente'] as $user): ?>
                                    <a href="/atlasware/public/admin/users/<?= $user['id'] ?>"
                                       class="text-decoration-none d-block mb-2">
                                        <div class="card bg-white border-0 shadow-sm hover-elevate"
                                             style="transition: box-shadow 0.15s ease, transform 0.15s ease;">
                                            <div class="card-body py-2 px-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="fw-semibold text-dark">
                                                        <?= htmlspecialchars($user['name']) ?>
                                                    </div>
                                                    <span class="text-muted" style="font-size: 0.7rem;">
                                                        ID: <?= $user['id'] ?>
                                                    </span>
                                                </div>

                                                <?php if (!empty($user['teams'])): ?>
                                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                                        <?php foreach ($user['teams'] as $teamInfo): ?>
                                                            <span class="badge bg-light text-primary border border-primary-subtle"
                                                                  style="font-size: 0.65rem; font-weight: 500;">
                                                                <?= htmlspecialchars($teamInfo) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Coluna Funcionários -->
                <div class="col-lg-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-success text-white py-2 text-center">
                            <strong>Funcionários</strong>
                        </div>
                        <div class="card-body p-2" style="min-height: 200px; background-color: #ecfdf5;">
                            <?php if (empty($columns['funcionario'])): ?>
                                <p class="text-muted small text-center mt-3">Nenhum funcionário cadastrado.</p>
                            <?php else: ?>
                                <?php foreach ($columns['funcionario'] as $user): ?>
                                    <a href="/atlasware/public/admin/users/<?= $user['id'] ?>"
                                       class="text-decoration-none d-block mb-2">
                                        <div class="card bg-white border-0 shadow-sm hover-elevate"
                                             style="transition: box-shadow 0.15s ease, transform 0.15s ease;">
                                            <div class="card-body py-2 px-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="fw-semibold text-dark">
                                                        <?= htmlspecialchars($user['name']) ?>
                                                    </div>
                                                    <span class="text-muted" style="font-size: 0.7rem;">
                                                        ID: <?= $user['id'] ?>
                                                    </span>
                                                </div>

                                                <?php if (!empty($user['teams'])): ?>
                                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                                        <?php foreach ($user['teams'] as $teamInfo): ?>
                                                            <span class="badge bg-light text-primary border border-primary-subtle"
                                                                  style="font-size: 0.65rem; font-weight: 500;">
                                                                <?= htmlspecialchars($teamInfo) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($columns['sem_role'])): ?>
                <div class="mt-4">
                    <h6 class="text-muted fw-semibold">Usuários sem role mapeada</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($columns['sem_role'] as $user): ?>
                            <a href="/atlasware/public/admin/users/<?= $user['id'] ?>"
                               class="text-decoration-none">
                                <div class="card bg-white mb-2 border-0 shadow-sm hover-elevate"
                                     style="transition: box-shadow 0.15s ease, transform 0.15s ease;">
                                    <div class="card-body py-2 px-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="fw-semibold text-dark">
                                                <?= htmlspecialchars($user['name']) ?>
                                            </div>
                                            <span class="text-muted" style="font-size: 0.7rem;">
                                                ID: <?= $user['id'] ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($user['teams'])): ?>
                                            <div class="mt-2 d-flex flex-wrap gap-1">
                                                <?php foreach ($user['teams'] as $teamInfo): ?>
                                                    <span class="badge bg-light text-primary border border-primary-subtle"
                                                          style="font-size: 0.65rem; font-weight: 500;">
                                                        <?= htmlspecialchars($teamInfo) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>