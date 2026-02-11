<!-- app/Views/requests/form.php -->
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Nova Solicitação de Acesso</h4>
                </div>
                <div class="card-body">
                    <form action="<?= BASE_PATH ?>/requests/store" method="POST">
                        
                        <div class="mb-3">
                            <label for="type" class="form-label fw-bold">O que você deseja solicitar?</label>
                            <select name="type" id="type" class="form-select" required onchange="toggleFields()">
                                <option value="">Selecione uma opção...</option>
                                <option value="team_join">Entrar em um Time</option>
                                <option value="role_change">Mudar meu Cargo Global</option>
                            </select>
                        </div>

                        <!-- Campos para Time -->
                        <div id="fields_team" class="d-none border p-3 mb-3 bg-light rounded">
                            <div class="mb-3">
                                <label for="team_id" class="form-label fw-bold">Selecione o Time:</label>
                                <select name="team_id" id="team_id" class="form-select">
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="role_id_team" class="form-label fw-bold">Cargo pretendido no Time:</label>
                                <select name="role_id" id="role_id_team" class="form-select">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Campos para Cargo Global -->
                        <div id="fields_role" class="d-none border p-3 mb-3 bg-light rounded">
                            <div class="mb-3">
                                <label for="role_id_global" class="form-label fw-bold">Novo Cargo Global:</label>
                                <select name="role_id" id="role_id_global" class="form-select">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="justification" class="form-label fw-bold">Justificativa:</label>
                            <textarea name="justification" id="justification" class="form-control" rows="3" 
                                      placeholder="Explique por que você precisa deste acesso..." required></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_PATH ?>/requests" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Enviar Solicitação</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFields() {
    const type = document.getElementById('type').value;
    const fieldsTeam = document.getElementById('fields_team');
    const fieldsRole = document.getElementById('fields_role');

    // Reset
    fieldsTeam.classList.add('d-none');
    fieldsRole.classList.add('d-none');
    
    // Desabilita inputs ocultos para não enviar dados errados no POST
    fieldsTeam.querySelectorAll('select').forEach(s => s.disabled = true);
    fieldsRole.querySelectorAll('select').forEach(s => s.disabled = true);

    if (type === 'team_join') {
        fieldsTeam.classList.remove('d-none');
        fieldsTeam.querySelectorAll('select').forEach(s => s.disabled = false);
    } else if (type === 'role_change') {
        fieldsRole.classList.remove('d-none');
        fieldsRole.querySelectorAll('select').forEach(s => s.disabled = false);
    }
}
</script>