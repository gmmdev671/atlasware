<?php
function renderUserNode($users, $allUsers = []) {
    if (empty($users) || !is_array($users)) return;

    echo '<ul>';

    foreach ($users as $user) {
        $id = isset($user['id']) ? (int)$user['id'] : 0;
        $name = isset($user['nome']) ? $user['nome'] : ($user['name'] ?? 'Sem Nome');
        $isActive = (isset($user['status']) && $user['status'] == 0);
        $cardClass = $isActive ? '' : 'opacity-50 border-danger';
        $hasSub = !empty($user['subordinates']) && is_array($user['subordinates']);
        $firstName = htmlspecialchars(explode(' ', trim($name))[0] ?? $name, ENT_QUOTES, 'UTF-8');
        $fullNameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        // id do sub-container (para aria-controls)
        $subId = 'sub-container-' . $id;

        // li (adiciona has-sub se houver subordinados)
        echo '<li class="user-node ' . ($hasSub ? 'has-sub' : '') . '" data-user-id="' . $id . '">';

            // wrapper do card + trigger (sempre presente)
            echo '<div class="user-card-wrapper">';

                // Card principal
                echo '<div class="user-card ' . $cardClass . '" data-user-id="' . $id . '">';

                    // Header do card (nome + ações)
                    echo '<div class="card-header-user">';
                        echo '<span class="user-first-name ml-3">' . $firstName . '</span>';

                        echo '<div class="user-actions-wrapper">';
                            // Botão Ver (info)
                            echo '<button class="btn-info-inline" title="Ver detalhes" onclick="event.stopPropagation(); window.location.href=\'' . BASE_PATH . '/admin/users/' . $id . '\'">';
                                echo '<i class="bi bi-info-circle" aria-hidden="true"></i>';
                            echo '</button>';

                            // Botão detalhes (expande o card interno)
                            echo '<button class="btn-details-inline" title="Expandir detalhes" onclick="event.stopPropagation(); toggleCardExpand(' . $id . ');">';
                                echo '<i class="bi bi-list-ul" aria-hidden="true"></i>';
                            echo '</button>';

                            // Botão editar/permissões
                            echo '<button class="btn-edit-inline" title="Ver permissões" onclick="event.stopPropagation(); openPermissionsModal(' . $id . ', \'' . addslashes($name) . '\');">';
                                echo '<i class="bi bi-pencil" aria-hidden="true"></i>';
                            echo '</button>';
                        echo '</div>'; // .user-actions-wrapper

                    echo '</div>'; // .card-header-user

                    // Container de detalhes (inicialmente escondido)
                    echo '<div class="card-details-container" id="card-details-' . $id . '" style="display:none;">';

                        // Inner wrapper que vira duas colunas quando o card está expandido
                        echo '<div class="card-details-inner">';

                            // NAV vertical (left)
                            echo '<nav class="nav flex-column nav-tabs-vertical vertical-tabs" role="tablist" aria-orientation="vertical">';
                                echo '<a class="nav-link active" data-bs-toggle="tab" href="#tab-resumo-' . $id . '">Resumo</a>';
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-obras-' . $id . '">Contratos</a>';
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-cidades-' . $id . '">Obras</a>';
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-abas-' . $id . '">Acessos</a>';
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-permissoes-' . $id . '">Permissões</a>';
                            echo '</nav>';

                            // Conteúdo à direita
                            echo '<div class="tab-content tab-content-compact vertical-tab-content">';
                                echo '<div class="tab-pane fade show active" id="tab-resumo-' . $id . '">';
                                    echo '<div class="details-content" data-content="resumo" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                                echo '<div class="tab-pane fade" id="tab-obras-' . $id . '">';
                                    echo '<div class="details-content" data-content="obras" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                                echo '<div class="tab-pane fade" id="tab-cidades-' . $id . '">';
                                    echo '<div class="details-content" data-content="cidades" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                                echo '<div class="tab-pane fade" id="tab-abas-' . $id . '">';
                                    echo '<div class="details-content" data-content="abas" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                                echo '<div class="tab-pane fade" id="tab-permissoes-' . $id . '">';
                                    echo '<div class="details-content" data-content="permissoes" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                            echo '</div>'; // .tab-content

                        echo '</div>'; // .card-details-inner

                    echo '</div>'; // .card-details-container

                echo '</div>'; // .user-card

                // Expand-trigger — sempre renderizado (botão quando tem filhos; placeholder invisível caso contrário)
                if ($hasSub) {
                    echo '<button type="button" class="expand-trigger" aria-expanded="true" onclick="event.stopPropagation(); toggleSub(this);">';
                        echo '<i class="bi bi-chevron-right toggle-icon"></i>';
                    echo '</button>';
                } else {
                    // O placeholder DEVE ter a classe expand-trigger para herdar o tamanho de 30px
                    echo '<span class="expand-trigger empty-expand-trigger"></span>';
                }

            echo '</div>'; // .user-card-wrapper

            // Sub-container (coluna de filhos) - somente se houver subordinados
            if ($hasSub) {
                echo '<div id="' . $subId . '" class="sub-container">';
                    // renderiza recursivamente os subordinados (renderUserNode já gera a UL)
                    renderUserNode($user['subordinates'], $allUsers);
                echo '</div>';
            }

        echo '</li>';
    }

    echo '</ul>';
}
?>

<script>const BASE_PATH = '<?= BASE_PATH ?>';</script>
<script src="<?= BASE_PATH ?>/js/organograma.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/atlasware/public/css/access.css">
<link rel="stylesheet" href="/atlasware/public/css/organograma.css">

<div class="organograma-humano-page">
    <!-- Cabeçalho no estilo padrão do sistema -->
    <div class="org-header-standard d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">Hierarquia de Liderança</h4>
        <div class="d-flex align-items-center">
            <div class="btn-group me-3">
                <button class="btn btn-sm btn-outline-light" onclick="expandAll()">Expandir Tudo</button>
                <button class="btn btn-sm btn-outline-light" onclick="collapseAll()">Recolher Tudo</button>
            </div>
            <a href="<?= BASE_PATH ?>/admin/dashboard" class="text-white-50 small text-decoration-none">
                ← Voltar para Dashboard
            </a>
        </div>
    </div>

    <!-- Área da Árvore -->
    <div class="tree-wrapper">
        <div class="tree">
            <?php renderUserNode($masters, $allUsers); ?>
        </div>
    </div>
</div>

<div id="modalPermissions" class="custom-modal" style="display:none;">
    <div class="modal-overlay" onclick="closePermissionsModal()"></div>
    <div class="modal-content-custom modal-large">
        <div class="modal-header-custom">
            <h5>Permissões de <span id="permUserName"></span></h5>
            <button class="btn-close-modal" onclick="closePermissionsModal()">&times;</button>
        </div>
        <div class="modal-body-custom">
            <input type="hidden" id="permUserId">
            <p class="text-muted small mb-3">As permissões marcadas abaixo serão atribuídas diretamente ao usuário, independente de cargo ou time.</p>
            
            <div id="permissionsList" class="permissions-grid">
            </div>
        </div>
        <div class="modal-footer-custom">
            <button class="btn btn-primary" onclick="savePermissions()">Salvar Permissões</button>
            <button class="btn btn-secondary" onclick="closePermissionsModal()">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal de Ativação de Permissões -->
<div id="modalActivatePermissions" class="custom-modal" style="display:none;">
    <div class="modal-overlay" onclick="closeActivatePermissionsModal()"></div>
    <div class="modal-content-custom modal-large">
        <div class="modal-header-custom">
            <h5>Ativar Permissões - <span id="activatePermUserName"></span></h5>
            <button class="btn-close-modal" onclick="closeActivatePermissionsModal()">&times;</button>
        </div>
        <div class="modal-body-custom">
            <input type="hidden" id="activatePermUserId">
            <p class="text-muted small mb-3">Selecione as permissões que deseja ativar para este usuário:</p>
            
            <div id="inactivePermissionsList" class="permissions-grid">
                <!-- Preenchido dinamicamente -->
            </div>
        </div>
        <div class="modal-footer-custom">
            <button class="btn btn-primary" onclick="activateSelectedPermissions()">
                <i class="bi bi-check-circle"></i> Ativar Selecionadas
            </button>
            <button class="btn btn-secondary" onclick="closeActivatePermissionsModal()">Cancelar</button>
        </div>
    </div>
</div>