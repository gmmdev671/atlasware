<?php
// app/Views/access/users_roles_index.php
?>
<div class="access-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-white mb-0">Gestão de Cargos de Usuários</h4>
        <a href="/atlasware/public/dashboard" class="text-white-50 small text-decoration-none">
            ← Voltar para Dashboard
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold">Usuários</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Nenhum usuário cadastrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                    <td class="text-end">
                                        <a href="/atlasware/public/admin/users/<?= $user['id'] ?>"
                                        class="btn btn-outline-secondary btn-sm">
                                            Detalhes
                                        </a>
                                        <a href="/atlasware/public/admin/users/<?= $user['id'] ?>/roles"
                                        class="btn btn-outline-primary btn-sm">
                                            Cargos / Nível
                                        </a>
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