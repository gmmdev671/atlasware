<?php
// app/Views/access/team_form.php
$isEdit = !empty($team);
$base = defined('BASE_PATH') ? BASE_PATH : '/atlasware/public';
$formAction = $isEdit
    ? $base . "/admin/teams/{$team['id']}/update"
    : $base . "/admin/teams/store";
?>
<div class="access-page">
    <div class="card border-0 shadow-sm" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-primary fw-bold">
                <?= $isEdit ? 'Editar Time' : 'Novo Time' ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="<?= $formAction ?>" method="POST">
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Nome do Time *</label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= htmlspecialchars($team['name'] ?? '') ?>" 
                           required>
                </div>

                <div class="mb-4">
                    <label for="parent_team_id" class="form-label fw-semibold">Time Pai (opcional)</label>
                    <select class="form-select" id="parent_team_id" name="parent_team_id">
                        <option value="">— Nenhum (time raiz) —</option>
                        <?php foreach ($allTeams as $t): ?>
                            <?php 
                            if ($isEdit && $t['id'] == $team['id']) continue; // não deixa ser pai de si mesmo
                            $selected = ($isEdit && $team['parent_team_id'] == $t['id']) ? 'selected' : '';
                            ?>
                            <option value="<?= $t['id'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        Se este time faz parte de outro (ex: RH dentro de Administrativo), selecione o time pai.
                    </small>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary text-white px-4">
                        <?= $isEdit ? 'Atualizar' : 'Criar' ?>
                    </button>
                    <button type="button" 
                            class="btn btn-outline-secondary px-4"
                            onclick="window.location.href='/atlasware/public/admin/teams'">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="mt-3 text-center">
        <a href="/atlasware/public/dashboard" class="text-white-50 small text-decoration-none">← Voltar para Dashboard</a>
    </div>
</div>