<?php
// app/Views/access/permission_form.php
$base = defined('BASE_PATH') ? BASE_PATH : '/atlasware/public';
$isEdit = isset($permission) && !empty($permission['id']);
$actionUrl = $isEdit 
    ? "{$base}/admin/permissions/{$permission['id']}/update" 
    : "{$base}/admin/permissions/store";
?>

<div class="access-page">
    <div class="mb-3">
        <a href="<?= $base ?>/admin/permissions" class="text-white-50 small text-decoration-none">← Voltar para Listagem</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold">
                <?= $isEdit ? 'Editar Permissão' : 'Nova Permissão' ?>
            </h5>
        </div>
        <div class="card-body p-4">
            
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger mb-4">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="<?= $actionUrl ?>" method="POST">
                <div class="row">
                    <!-- Nome da Permissão -->
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label fw-bold text-dark">Nome da Permissão</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               class="form-control" 
                               placeholder="Ex: editar_usuarios, visualizar_financeiro"
                               value="<?= htmlspecialchars($permission['name'] ?? '') ?>" 
                               required>
                        <div class="form-text">Use nomes padronizados (snake_case) para facilitar a verificação no código.</div>
                    </div>

                    <!-- Vínculo com Aba -->
                    <div class="col-md-6 mb-3">
                        <label for="tab_id" class="form-label fw-bold text-dark">Aba Relacionada</label>
                        <select name="tab_id" id="tab_id" class="form-select">
                            <option value="">-- Sem Aba (Permissão Global) --</option>
                            <?php foreach ($tabs as $tab): ?>
                                <option value="<?= $tab['id'] ?>" 
                                    <?= (isset($permission['tab_id']) && $permission['tab_id'] == $tab['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tab['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Agrupar permissões por aba ajuda na organização da tela de gestão.</div>
                    </div>
                </div>

                <hr class="my-4 text-light">

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= $base ?>/admin/permissions" class="btn btn-light px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary text-white fw-bold px-4">
                        <?= $isEdit ? 'Salvar Alterações' : 'Criar Permissão' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>