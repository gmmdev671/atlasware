<?php
// app/Views/access/_tree_partial.php
// Helper para normalizar: aceita string ou array e retorna "1,2,3" ou ""
if (!function_exists('normalizeDataAttr')) {
    function normalizeDataAttr($value) {
        if ($value === null || $value === '') return '';
        if (is_array($value)) {
            $clean = array_filter(array_map('trim', $value), function($v){ return $v !== '' && $v !== null; });
            return implode(',', $clean);
        }
        $s = trim((string)$value);
        $s = preg_replace('/\s*,\s*/', ',', $s);
        $parts = array_filter(array_map('trim', explode(',', $s)), function($v){ return $v !== ''; });
        return implode(',', $parts);
    }
}
// Este arquivo só tem uma missão: renderizar os nós.
function renderUserNode($users, $allUsers = [], $visited = []) {
    if (empty($users) || !is_array($users)) return;

    echo '<ul>';

    foreach ($users as $user) {
        $id = isset($user['id']) ? (int)$user['id'] : 0;
        if (in_array($id, $visited)) continue;
        $visited[] = $id;
        $name = isset($user['nome']) ? $user['nome'] : ($user['name'] ?? 'Sem Nome');
        $isActive = (isset($user['status']) && $user['status'] == 0);
        $cardClass = $isActive ? '' : 'opacity-50 border-danger';
        $hasSub = !empty($user['subordinates']) && is_array($user['subordinates']);
        $firstName = htmlspecialchars(explode(' ', trim($name))[0] ?? $name, ENT_QUOTES, 'UTF-8');
        $fullNameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $abasAttrEsc = htmlspecialchars(normalizeDataAttr($user['abas'] ?? ''), ENT_QUOTES, 'UTF-8');
        $permsAttrEsc = htmlspecialchars(normalizeDataAttr($user['permissoes'] ?? ''), ENT_QUOTES, 'UTF-8');
        $obrasAttrEsc = htmlspecialchars(normalizeDataAttr($user['obras'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cidadesAttrEsc = htmlspecialchars(normalizeDataAttr($user['cidades'] ?? ''), ENT_QUOTES, 'UTF-8');

        // id do sub-container (para aria-controls)
        $subId = 'sub-container-' . $id;

        // li (adiciona has-sub se houver subordinados)
        echo '<li class="user-node ' . ($hasSub ? 'has-sub' : '') . '" data-user-id="' . $id . '">';

            // wrapper do card + trigger (sempre presente)
            echo '<div class="user-card-wrapper">';

                // Card principal
                echo '<div class="user-card ' . $cardClass . '" 
                    data-user-id="' . $id . '" 
                    data-status="' . ($isActive ? 'active' : 'inactive') . '"
                    data-lider="' . ($user['id_lider'] ?? '') . '"
                    data-abas="' . $abasAttrEsc . '"
                    data-permissoes="' . $permsAttrEsc . '"
                    data-obras="' . $obrasAttrEsc . '"
                    data-cidades="' . $cidadesAttrEsc . '">';

                    // Header do card (nome + ações)
                    echo '<div class="card-header-user" style="display:flex; justify-content:space-between; align-items:flex-start; width:100%;">';

                        // LEFT: área com login + nome completo + status (compacto)
                        echo '<div class="user-compact-info" style="flex: 1; min-width: 0; padding-right: 10px; display:flex; flex-direction:column;">';

                            // login em destaque (mantive user-first-name para compatibilidade)
                            $login = isset($user['login']) && $user['login'] !== '' ? $user['login'] : $firstName;
                            $loginEsc = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');
                            echo '<span class="user-first-name" style="font-weight:700; font-size:1rem; color:#111827;">' . $loginEsc . '</span>';

                            // nome completo menor
                            echo '<span class="user-full-name" style="color:#6b7280; font-size:0.85rem; font-weight:600; display:block; white-space:normal; word-break:break-word; margin-top:2px;">' . $fullNameEsc . '</span>';

                            // status (linha final do card fechado)
                            $statusText = $isActive ? 'Ativo' : 'Inativo';
                            $statusClass = $isActive ? 'status-active' : 'status-inactive';
                            echo '<span class="user-status-badge ' . $statusClass . '" style="margin-top:4px; display:inline-block;">';
                                echo '<span class="badge ' . ($isActive ? 'bg-success' : 'bg-danger') . '" style="font-weight:700;">' . $statusText . '</span>';
                            echo '</span>';

                        echo '</div>'; // .user-compact-info

                        // RIGHT: ações (mantive sua estrutura)
                        echo '<div class="user-actions-wrapper" style="flex-shrink: 0; display: flex; gap: 4px;">';
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
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-abas-' . $id . '">Acessos</a>';
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-obras-' . $id . '">Contratos</a>';
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-cidades-' . $id . '">Obras</a>';
                                echo '<a class="nav-link" data-bs-toggle="tab" href="#tab-permissoes-' . $id . '">Permissões</a>';
                            echo '</nav>';

                            // Conteúdo à direita
                            echo '<div class="tab-content tab-content-compact vertical-tab-content">';
                                echo '<div class="tab-pane fade" id="tab-abas-' . $id . '">';
                                    echo '<div class="details-content" data-content="abas" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                                echo '<div class="tab-pane fade" id="tab-obras-' . $id . '">';
                                    echo '<div class="details-content" data-content="obras" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                                echo '<div class="tab-pane fade" id="tab-cidades-' . $id . '">';
                                    echo '<div class="details-content" data-content="cidades" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                                echo '<div class="tab-pane fade" id="tab-permissoes-' . $id . '">';
                                    echo '<div class="details-content" data-content="permissoes" data-user-id="' . $id . '">Carregando...</div>';
                                echo '</div>';
                            echo '</div>';
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
                    renderUserNode($user['subordinates'], $allUsers, $visited);
                echo '</div>';
            }

        echo '</li>';
    }

    echo '</ul>';
}

if (isset($roots)) {
    renderUserNode($roots, $roots);
}
?>