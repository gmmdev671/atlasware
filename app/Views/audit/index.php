<?php
// app/Views/audit/index.php
?>
<link rel="stylesheet" href="<?= $basePath ?>/css/audit.css">

<div class="d-flex justify-content-between align-items-center mb-4 audit-header">
    <div>
        <h1 class="h3 mb-0">Auditoria do Sistema</h1>
        <p class="mb-0">Histórico de ações e alterações de permissões.</p>
    </div>
    <button onclick="window.location.reload()" class="btn btn-refresh btn-sm px-3">
        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
    </button>
</div>

<div class="card audit-card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Data/Hora</th>
                        <th>Executor</th>
                        <th>Ação</th>
                        <th>Tabela</th>
                        <th>ID Afetado</th>
                        <th class="text-end pe-4">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="bi bi-shield-slash fs-1 d-block mb-3 opacity-25"></i>
                                    <span class="fs-5">Nenhum registro de auditoria encontrado.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4">
                                    <small class="d-block fw-bold"><?= date('d/m/Y', strtotime($log['created_at'])) ?></small>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <span class="text-dark"><?= htmlspecialchars($log['user_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted italic">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'bg-secondary';
                                    if (strpos($log['action'], 'ATIVAR') !== false) $badgeClass = 'bg-success';
                                    if (strpos($log['action'], 'DESATIVAR') !== false) $badgeClass = 'bg-danger';
                                    if (strpos($log['action'], 'LIDER') !== false) $badgeClass = 'bg-primary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?> font-monospace" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td><code class="small text-primary"><?= htmlspecialchars($log['table_name'] ?? '-') ?></code></td>
                                <td><?= $log['record_id'] ?? '-' ?></td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalLog<?= $log['id'] ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Modal de Detalhes -->
                            <div class="modal fade" id="modalLog<?= $log['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detalhes do Log #<?= $log['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Ação:</strong> <span class="badge <?= $badgeClass ?>"><?= $log['action'] ?></span><br>
                                                    <strong>Executor:</strong> <?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?><br>
                                                    <strong>Data:</strong> <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Tabela:</strong> <code><?= $log['table_name'] ?></code><br>
                                                    <strong>ID Registro:</strong> <?= $log['record_id'] ?><br>
                                                    <strong>IP:</strong> <?= $log['ip_address'] ?>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="fw-bold text-danger">Antes:</h6>
                                                    <pre class="json-preview bg-light p-3 rounded border small"><?php 
                                                        $old = json_decode($log['old_values'], true);
                                                        echo $old ? json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Nenhum dado';
                                                    ?></pre>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="fw-bold text-success">Depois:</h6>
                                                    <pre class="json-preview bg-light p-3 rounded border small"><?php 
                                                        $new = json_decode($log['new_values'], true);
                                                        echo $new ? json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Nenhum dado';
                                                    ?></pre>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Fim do Modal -->

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>