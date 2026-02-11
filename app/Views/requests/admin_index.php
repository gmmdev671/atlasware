<!-- app/Views/requests/admin_index.php -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-shield-check"></i> Gerenciar Solicitações</h2>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Solicitações Pendentes</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Usuário</th>
                            <th>Tipo</th>
                            <th>Detalhes</th>
                            <th>Justificativa</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingRequests)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-check2-all display-4"></i><br>
                                    Nenhuma solicitação pendente no momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingRequests as $req): ?>
                                <tr>
                                    <td class="align-middle small">
                                        <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?>
                                    </td>
                                    <td class="align-middle fw-bold">
                                        <?= htmlspecialchars($req['user_name']) ?>
                                    </td>
                                    <td class="align-middle">
                                        <?php if ($req['type'] === 'team_join'): ?>
                                            <span class="badge bg-info text-dark">Entrar em Time</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Mudar Cargo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle small">
                                        <?php if ($req['type'] === 'team_join'): ?>
                                            Time ID: <?= $req['payload']['team_id'] ?> <br>
                                            Cargo ID: <?= $req['payload']['role_id'] ?>
                                        <?php else: ?>
                                            Novo Cargo ID: <?= $req['payload']['role_id'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($req['justification']) ?>">
                                            <?= htmlspecialchars($req['justification']) ?>
                                        </span>
                                    </td>
                                    <td class="align-middle text-center">
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalApprove<?= $req['id'] ?>">
                                            Analisar
                                        </button>

                                        <!-- Modal de Decisão -->
                                        <div class="modal fade" id="modalApprove<?= $req['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content text-start">
                                                    <form action="<?= BASE_PATH ?>/admin/requests/<?= $req['id'] ?>/process" method="POST">
                                                        <div class="modal-header bg-light">
                                                            <h5 class="modal-title">Analisar Solicitação #<?= $req['id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>Usuário:</strong> <?= htmlspecialchars($req['user_name']) ?></p>
                                                            <p><strong>Justificativa:</strong><br>
                                                            <span class="text-muted italic">"<?= nl2br(htmlspecialchars($req['justification'])) ?>"</span></p>
                                                            
                                                            <hr>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Sua Resposta/Comentário:</label>
                                                                <textarea name="review_comment" class="form-control" rows="3" placeholder="Opcional..."></textarea>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Decisão:</label>
                                                                <select name="action" class="form-select" required>
                                                                    <option value="approved">Aprovar e Aplicar Acesso</option>
                                                                    <option value="rejected">Rejeitar Solicitação</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light">
                                                            <button type="button" class="btn btn-secondary" data-bs-target="#modalApprove<?= $req['id'] ?>" data-bs-toggle="modal">Fechar</button>
                                                            <button type="submit" class="btn btn-primary">Confirmar Decisão</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
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