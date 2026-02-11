<?php
// app/Views/access/team_members.php

?>
<div class="access-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-white mb-0">
            Membros do Time: <?= htmlspecialchars($team['name']) ?>
        </h4>
        <a href="/atlasware/public/admin/teams" class="text-white-50 small text-decoration-none">
            ← Voltar para lista de times
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold">Adicionar / Atualizar Membro</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/atlasware/public/admin/teams/<?= $team['id'] ?>/members/add" class="row g-3">
                <div class="col-md-5">
                    <label for="user_id" class="form-label fw-semibold">Usuário</label>
                    <select name="user_id" id="user_id" class="form-select" required>
                        <option value="">Selecione um usuário...</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['name']) ?> (ID: <?= $u['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="role_id" class="form-label fw-semibold">Cargo no Time</label>
                    <select name="role_id" id="role_id" class="form-select" required>
                        <option value="">Selecione um cargo...</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>">
                                <?= htmlspecialchars($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary text-white w-100">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold">Membros atuais do time</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Usuário</th>
                            <th>E-mail</th>
                            <th>Cargo no Time</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Nenhum membro neste time.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['user_name']) ?> (ID: <?= $m['user_id'] ?>)</td>
                                    <td><?= htmlspecialchars($m['user_email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($m['role_name']) ?></td>
                                    <td class="text-end">
                                        <form method="POST"
                                              action="/atlasware/public/admin/teams/<?= $team['id'] ?>/members/<?= $m['user_id'] ?>/remove"
                                              onsubmit="return confirm('Remover este usuário do time?');"
                                              class="d-inline">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                Remover
                                            </button>
                                        </form>
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