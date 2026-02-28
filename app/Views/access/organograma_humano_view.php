<?php require_once __DIR__ . '/_tree_partial.php'; ?>

<script>const BASE_PATH = '<?= BASE_PATH ?>';</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                <button id="btn-fullscreen" class="btn btn-sm btn-outline-light" title="Tela cheia" onclick="window.toggleFullscreenManual ? window.toggleFullscreenManual() : null">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
            </div>
            <a href="<?= BASE_PATH ?>/admin/dashboard" class="text-white-50 small text-decoration-none">
                ← Voltar para Dashboard
            </a>
        </div>
    </div>
    

    <!-- Área da Árvore -->
    <div class="tree-wrapper">
        
        <!-- Toolbar Fixa na Base -->
        <div class="org-toolbar-fixed-bottom" role="region" aria-label="Barra de pesquisa e filtros">
            <div class="org-search-box">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="org-search-input" placeholder="Pesquisar por nome ou login..." onkeyup="handleSearch(this.value)">
                <button class="btn-clear-search" onclick="clearOrgSearch()">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>

            <div class="org-toolbar-separator"></div>

            <!-- Grupo de Controles da Árvore -->
            <div class="org-controls-group">
                <button class="btn-org-control" onclick="expandAll()" title="Expandir Tudo">
                    <i class="bi bi-plus-square"></i>
                </button>
                <button class="btn-org-control" onclick="collapseAll()" title="Recolher Tudo">
                    <i class="bi bi-dash-square"></i>
                </button>
                <button class="btn-org-control" onclick="recenterTree()" title="Centralizar Árvore">
                    <i class="bi bi-crosshair"></i>
                </button>
                <button class="btn-org-control" onclick="toggleFullscreenManual()" title="Tela Cheia">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
            </div>


            <div class="org-filters-group">
                <button id="btn-filter-adv" class="btn-filter-adv" onclick="toggleFilterPanel()" title="Filtros Avançados">
                    <i class="bi bi-sliders"></i>
                </button>
            </div>

            <div id="search-results-count" class="search-badge" style="display:none;">
                <span class="badge bg-primary"><span id="results-num">0</span></span>
            </div>
        </div>
        
        <div class="tree">
            <?php renderUserNode($masters, $allUsers); ?>
        </div>
    </div>

    <!-- Gaveta Lateral de Filtros Avançados -->
    <div class="offcanvas offcanvas-end org-filters-drawer" tabindex="-1" id="offcanvasFilters" aria-labelledby="offcanvasFiltersLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold" id="offcanvasFiltersLabel">
                <i class="bi bi-sliders2-vertical me-2"></i>Filtros Avançados
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form id="form-filters-adv">
                <!-- Filtro por Obra -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Contrato / Obra</label>
                    <select id="adv-filter-obra" class="form-select select2-filter" onchange="applyOrgFilters()">
                        <option value="all">Todas as Obras</option>
                        <!-- Preencher via PHP ou JS -->
                    </select>
                </div>

                <!-- Filtro por Cidade -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Cidade</label>
                    <select id="adv-filter-cidade" class="form-select select2-filter" onchange="applyOrgFilters()">
                        <option value="all">Todas as Cidades</option>
                        <!-- Preencher via PHP ou JS -->
                    </select>
                </div>

                <!-- Filtro por Cargo/Nível -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Nível Hierárquico</label>
                    <div class="list-group list-group-flush border rounded">
                        <label class="list-group-item">
                            <input class="form-check-input me-2" type="checkbox" value="master" checked onchange="applyOrgFilters()"> Master
                        </label>
                        <label class="list-group-item">
                            <input class="form-check-input me-2" type="checkbox" value="supervisor" checked onchange="applyOrgFilters()"> Supervisor
                        </label>
                        <label class="list-group-item">
                            <input class="form-check-input me-2" type="checkbox" value="gerente" checked onchange="applyOrgFilters()"> Gerente
                        </label>
                    </div>
                </div>

                <!-- Filtro por Status -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Status</label>
                    <select id="adv-filter-status" class="form-select" onchange="fetchTreeByStatus(this.value)">
                        <option value="all">Todos os Status</option>
                        <option value="active" selected>Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>

                <!-- Filtro de Exibição de Órfãos -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Estrutura</label>
                    <div class="form-check form-switch border p-2 rounded bg-light">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="adv-filter-orphans" onchange="fetchTreeByStatus(document.getElementById('adv-filter-status').value)">
                        <label class="form-check-label small fw-bold" for="adv-filter-orphans">Exibir usuários sem hierarquia</label>
                    </div>
                    <div class="form-text text-muted" style="font-size: 0.75rem;">
                        Ative para ver usuários que não possuem líder nem subordinados.
                    </div>
                </div>

                <!-- Filtro por Líder -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Líder</label>
                    <select id="adv-filter-leader" class="form-select" onchange="fetchTreeFromLeader(this.value)">
                        <option value="">Todos os Líderes</option>
                        <?php foreach (($leaders ?? []) as $l): ?>
                            <option value="<?= (int)$l['id'] ?>">
                                <?= htmlspecialchars(mb_strtoupper($l['nome'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro por Abas (Acessos) -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Abas / Acessos</label>
                    <div class="list-group list-group-flush border rounded" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach (($allTabs ?? []) as $tab): ?>
                            <label class="list-group-item py-1">
                                <input class="form-check-input me-2 adv-filter-aba" 
                                    type="checkbox" 
                                    value="<​?= (int)$tab['id'] ?>" 
                                    onchange="applyOrgFilters()">
                                <?= htmlspecialchars(mb_strtoupper($tab['nome'])) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filtro por Permissões -->
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Permissões</label>
                    <div class="list-group list-group-flush border rounded" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach (($allPermissions ?? []) as $key => $label): ?>
                            <label class="list-group-item py-1">
                                <input class="form-check-input me-2 adv-filter-permissao" 
                                    type="checkbox" 
                                    value="<​?= htmlspecialchars($key) ?>" 
                                    onchange="applyOrgFilters()">
                                <?= htmlspecialchars(mb_strtoupper($label)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="offcanvas-footer p-3 border-top bg-light">
            <button type="button" class="btn btn-outline-secondary w-100 mb-2" onclick="resetAdvFilters()">Limpar Filtros</button>
            <button type="button" class="btn btn-primary w-100" data-bs-dismiss="offcanvas">Aplicar e Fechar</button>
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
<script>
// Teste direto na View para isolar o problema do arquivo externo
function toggleFullscreenManual() {
    const wrapper = document.querySelector('.tree-wrapper');
    if (!wrapper) return alert('Container não encontrado');
    
    wrapper.classList.toggle('fullscreen-mode');
    const isFull = wrapper.classList.contains('fullscreen-mode');
    document.body.style.overflow = isFull ? 'hidden' : '';
    
    console.log('Fullscreen mode:', isFull);
}
</script>