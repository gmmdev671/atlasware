<?php
// app/Views/access/organograma_view.php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', rtrim(str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']), '/'));
}
$base = BASE_PATH;

/**
 * Função auxiliar para renderizar os níveis da árvore
 */
function renderNode($nodes) {
    if (empty($nodes)) return;
    
    // IMPORTANTE: Permite que a função use a variável $base definida lá fora
    global $base;
    $base = BASE_PATH;

    echo '<ul>';
    foreach ($nodes as $node) {
        $teamId = (int)($node['id'] ?? 0);
        echo '<li>';
        echo '<div class="org-card" data-team-id="' . $teamId . '">';
            
            // Cabeçalho do Card com Nome e Ações
            echo '<div class="org-card-header d-flex justify-content-between align-items-start">';
                echo '<strong class="team-title">' . htmlspecialchars($node['name']) . '</strong>';
                
                echo '<div class="card-actions">';
                    // Botão Editar (Usa a variável $base global)
                    echo '<a href="' . $base . '/admin/teams/' . $teamId . '/edit" class="btn-action" title="Editar Time">✏️</a>';
                    
                    // Botão Membros (Usa a variável $base global)
                    echo '<a href="' . $base . '/admin/teams/' . $teamId . '/members" class="btn-action" title="Gerenciar Membros">👥</a>';
                    
                    // Botão Toggle (JS)
                    echo '<button type="button" class="btn-action btn-toggle-members" data-team-id="' . $teamId . '" aria-expanded="true" title="Alternar membros">🔽</button>';
                echo '</div>';
            echo '</div>';
        
            // Lista membros do time
            if (!empty($node['members'])) {
                echo '<div class="org-members" id="members-' . $teamId . '">';
                foreach ($node['members'] as $m) {
                    $level = (int)($m['min_role_level'] ?? 999);
                    $roleName = $m['role_names'] ?? 'Membro';

                    if ($level <= 10) {
                        $badgeClass = 'bg-dark text-white';
                    } elseif ($level <= 30) {
                        $badgeClass = 'bg-danger text-white';
                    } elseif ($level <= 55) {
                        $badgeClass = 'bg-primary text-white';
                    } else {
                        $badgeClass = 'bg-secondary text-white';
                    }
                    
                    echo '<div class="member-item">';
                        echo '<span class="member-name">' . htmlspecialchars($m['name']) . '</span>';
                        echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($roleName) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
            }
        echo '</div>';

        // Renderiza sub-times (Recursão)
        if (!empty($node['subs'])) {
            renderNode($node['subs']);
        }
        echo '</li>';
    }
    echo '</ul>';
}
?>

<script>const BASE_PATH = '<?= BASE_PATH ?>';</script>
<script src="<?= BASE_PATH ?>/js/teams_hierarchy.js"></script>
<link rel="stylesheet" href="<?= BASE_PATH ?>/css/teams_hierarchy.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="text-white mb-0 fw-bold">📊 Estrutura Organizacional</h4>
    <a href="<?php echo $base; ?>/admin/access" class="text-white-50 small text-decoration-none">
        ← Voltar para Visão de Acesso
    </a>
</div>

<div class="organograma-container">
    

    <div class="tree">
        <?php if (!empty($tree)): ?>
            <?php renderNode($tree); ?>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                <strong>Nenhuma estrutura organizacional encontrada.</strong>
                <p class="mb-0 mt-2">Crie times e associe usuários para visualizar o organograma.</p>
            </div>
        <?php endif; ?>
    </div>
</div>