<?php
// app/Views/access/role_form.php
// Espera $role (array|null) e possivelmente $error
$editing = isset($role) && !empty($role['id']);

// CORREÇÃO: Usando BASE_PATH para tornar a URL dinâmica
$actionUrl = $editing 
    ? BASE_PATH . "/admin/roles/{$role['id']}/update" 
    : BASE_PATH . "/admin/roles/store";
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-6">
        <div class="card card-transparent shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3"><?= $editing ? 'Editar Role' : 'Criar Role' ?></h2>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= $actionUrl ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nome</label>
                        <input type="text" id="name" name="name" required class="form-control"
                               value="<?= htmlspecialchars($role['name'] ?? '') ?>" />
                    </div>

                    <div class="mb-3">
                        <label for="level" class="form-label">Nível</label>
                        <input type="number" id="level" name="level" min="1" max="99" class="form-control"
                               value="<?= htmlspecialchars($role['level'] ?? 99) ?>" />
                        <div class="form-text">Nível menor = nível superior (ex.: 1 = Master)</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <!-- CORREÇÃO: Link de voltar dinâmico -->
                        <a href="<?= BASE_PATH ?>/admin/roles" class="btn btn-secondary">Voltar</a>
                        <button type="submit" class="btn btn-primary"><?= $editing ? 'Atualizar' : 'Criar' ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>