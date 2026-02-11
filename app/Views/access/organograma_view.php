<?php
// Função auxiliar para renderizar os níveis da árvore
function renderNode($nodes) {
    if (empty($nodes)) return;
    echo '<ul>';
    foreach ($nodes as $node) {
        echo '<li>';
        echo '<div class="org-card">';
        echo '<strong>' . htmlspecialchars($node['name']) . '</strong>';
        
        // Lista membros do time
        if (!empty($node['members'])) {
            echo '<div class="org-members">';
            foreach ($node['members'] as $m) {
                // Define cor do badge baseado no level (menor = mais poder)
                if ($m['level'] <= 10) {
                    $badgeClass = 'bg-dark text-white'; // Master
                } elseif ($m['level'] <= 30) {
                    $badgeClass = 'bg-danger text-white'; // Coordenador
                } elseif ($m['level'] <= 55) {
                    $badgeClass = 'bg-primary text-white'; // Gerente
                } else {
                    $badgeClass = 'bg-secondary text-white'; // Funcionário
                }
                
                echo '<div class="member-item">';
                echo '<span>' . htmlspecialchars($m['name']) . '</span>';
                echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($m['role_name']) . '</span>';
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

<!-- Link para o CSS específico do Organograma -->
<link rel="stylesheet" href="/atlasware/public/css/access.css">

<div class="organograma-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0 fw-bold">📊 Estrutura Organizacional</h4>
        <a href="/atlasware/public/admin/access" class="text-white-50 small text-decoration-none">
            ← Voltar para Visão de Acesso
        </a>
    </div>

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