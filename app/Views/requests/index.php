<!-- app/Views/requests/index.php -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-journal-text"></i> Minhas Solicitações</h2>
        <a href="<?= BASE_PATH ?>/requests/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nova Solicitação
        </a>
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
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Detalhes</th>
                            <th>Status</th>
                            <th>Revisado por</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Você ainda não possui solicitações de acesso.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td class="align-middle">
                                        <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?>
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
                                        <?php 
                                        $statusClass = [
                                            'pending' => 'bg-warning text-dark',
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'canceled' => 'bg-secondary'
                                        ];
                                        $statusLabel = [
                                            'pending' => 'Pendente',
                                            'approved' => 'Aprovado',
                                            'rejected' => 'Rejeitado',
                                            'canceled' => 'Cancelado'
                                        ];
                                        ?>
                                        <span class="badge <?= $statusClass[$req['status']] ?>">
                                            <?= $statusLabel[$req['status']] ?>
                                        </span>
                                    </td>
                                    <td class="align-middle small">
                                        <?php if ($req['reviewed_at']): ?>
                                            Por: <?= htmlspecialchars($req['reviewed_by'] ?? 'Sistema', ENT_QUOTES, 'UTF-8') ?><br>
                                            Em: <?= date('d/m/Y', strtotime($req['reviewed_at'])) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="modal" data-bs-target="#modalReq<?= $req['id'] ?>">
                                            <i class="bi bi-info-circle"></i> Detalhes
                                        </button>

                                        <!-- Modal de Detalhes -->
                                        <div class="modal fade" id="modalReq<?= $req['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Detalhes da Solicitação #<?= $req['id'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <h6><strong>Sua Justificativa:</strong></h6>
                                                        <p class="bg-light p-2 rounded"><?= nl2br(htmlspecialchars($req['justification'])) ?></p>
                                                        
                                                        <?php if ($req['review_comment']): ?>
                                                            <hr>
                                                            <h6><strong>Resposta do Aprovador:</strong></h6>
                                                            <p class="bg-light p-2 rounded border-start border-4 border-primary">
                                                                <?= htmlspecialchars($request['review_comment'] ?? 'Sem justificativa', ENT_QUOTES, 'UTF-8') ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
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