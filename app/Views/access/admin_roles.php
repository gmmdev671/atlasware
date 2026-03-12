<?php
// app/Views/access/admin_roles.php
$base = defined('BASE_PATH') ? BASE_PATH : '/atlasware/public';
?>
<div class="access-page">
    
    <div class="mb-3">
        <a href="/atlasware/public/dashboard" class="text-white-50 small text-decoration-none">← Voltar para Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary fw-bold">Gestão de Roles (Cargos)</h5>
            <!-- Ajuste: text-white para garantir a label branca -->
            <a href="/atlasware/public/admin/roles/create" class="btn btn-primary btn-sm text-white fw-bold px-3">
                Novo Cargo
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <!-- ID removido conforme solicitado -->
                            <th class="ps-4">Nome do Cargo</th>
                            <th class="text-center">Nível Hierárquico</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">Nenhum cargo cadastrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($role['name']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3">
                                            Nível <?= $role['level'] ?? 'N/A' ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <!-- Editar (GET) -->
                                            <a href="<?php echo $base; ?>/admin/roles/<?php echo (int)$role['id']; ?>/edit"
                                            class="btn btn-outline-secondary action-btn">Editar</a>

                                            <!-- Permissões (GET) -->
                                            <a href="<?php echo $base; ?>/admin/roles/<?php echo (int)$role['id']; ?>/permissions"
                                            class="btn btn-outline-secondary action-btn">Permissões</a>

                                            <!-- Excluir (POST) -->
                                            <form action="<?php echo $base; ?>/admin/roles/<?php echo (int)$role['id']; ?>/delete"
                                                method="POST"
                                                class="m-0"
                                                onsubmit="return confirm('Tem certeza que deseja excluir este cargo?')">
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