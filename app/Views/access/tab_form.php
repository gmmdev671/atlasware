<?php
// app/Views/access/tab_form.php
$base = defined('BASE_PATH') ? BASE_PATH : '/atlasware/public';
$isEdit = isset($tab) && !empty($tab['id']);
$actionUrl = $isEdit 
    ? "{$base}/admin/tabs/{$tab['id']}/update" 
    : "{$base}/admin/tabs/store";
?>

<div class="access-page">
    <div class="mb-3">
        <a href="<?= $base ?>/admin/tabs" class="text-white-50 small text-decoration-none">← Voltar para Listagem</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold">
                <?= $isEdit ? 'Editar Aba' : 'Nova Aba' ?>
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
                    <!-- Nome da Aba -->
                    <div class="col-md-6 mb-3">
                        <label for="nome" class="form-label fw-bold text-dark">Nome da Aba</label>
                        <input type="text" 
                               name="nome" 
                               id="nome" 
                               class="form-control" 
                               placeholder="Ex: Financeiro, Obras, RH"
                               value="<?= htmlspecialchars($tab['nome'] ?? '') ?>" 
                               required>
                        <div class="form-text">Nome amigável que aparecerá nos menus e listagens.</div>
                    </div>

                    <!-- Key Name (Slug/Identificador) -->
                    <div class="col-md-6 mb-3">
                        <label for="key_name" class="form-label fw-bold text-dark">Key Name (Identificador)</label>
                        <input type="text" 
                               name="key_name" 
                               id="key_name" 
                               class="form-control" 
                               placeholder="Ex: financeiro, obras, rh"
                               value="<?= htmlspecialchars($tab['key_name'] ?? '') ?>">
                        <div class="form-text">Identificador único para lógica de código (opcional, mas recomendado).</div>
                    </div>

                    <!-- Descrição -->
                    <div class="col-12 mb-3">
                        <label for="descricao" class="form-label fw-bold text-dark">Descrição</label>
                        <textarea name="descricao" 
                                  id="descricao" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Breve descrição do que este módulo gerencia..."><?= htmlspecialchars($tab['descricao'] ?? '') ?></textarea>
                    </div>
                </div>

                <hr class="my-4 text-light">

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= $base ?>/admin/tabs" class="btn btn-light px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary text-white fw-bold px-4">
                        <?= $isEdit ? 'Salvar Alterações' : 'Criar Aba' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>