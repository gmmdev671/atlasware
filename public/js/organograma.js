/* organograma.js - versão atualizada (Pan + Toolbar drag sem conflitos)
   Colocar este arquivo em /public/js/organograma.js e incluir na view.
*/

/* =========================
   Helpers iniciais e toggles
   ========================= */

function toggleSub(el) {
    if (!el) return;
    var trigger = el.classList && el.classList.contains('expand-trigger') ? el : el.closest('.expand-trigger');
    if (!trigger) return;
    var li = trigger.closest('li');
    if (!li) return;
    var collapsed = li.classList.toggle('collapsed'); // true se agora está colapsado
    if (trigger.getAttribute) {
        if (trigger.getAttribute('aria-hidden') !== 'true') {
            trigger.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }
    var icon = trigger.querySelector('.toggle-icon');
    if (icon) {
        if (collapsed) icon.classList.add('rotated'); else icon.classList.remove('rotated');
    }
}

function expandAll() {
    document.querySelectorAll('.organograma-humano-page .tree li.has-sub').forEach(function(li){
        li.classList.remove('collapsed');
        var trigger = li.querySelector('.expand-trigger');
        if (trigger && trigger.getAttribute('aria-hidden') !== 'true') trigger.setAttribute('aria-expanded', 'true');
        var icon = li.querySelector('.expand-trigger .toggle-icon');
        if (icon) icon.classList.remove('rotated');
    });
}

function collapseAll() {
    document.querySelectorAll('.organograma-humano-page .tree li.has-sub').forEach(function(li){
        li.classList.add('collapsed');
        var trigger = li.querySelector('.expand-trigger');
        if (trigger && trigger.getAttribute('aria-hidden') !== 'true') trigger.setAttribute('aria-expanded', 'false');
        var icon = li.querySelector('.expand-trigger .toggle-icon');
        if (icon) icon.classList.add('rotated');
    });
}

// Delegação de clique para triggers (se necessário)
document.addEventListener('click', function(e){
    var t = e.target;
    var trg = t.classList && t.classList.contains('expand-trigger') ? t : t.closest && t.closest('.expand-trigger');
    if (!trg) return;
    if (trg.getAttribute && trg.getAttribute('aria-hidden') === 'true') return;
    e.stopPropagation();
    toggleSub(trg);
});

/* =========================
   Modal e edição de líder
   ========================= */

function openEditLeader(id, name) {
    var el = document.getElementById('editUserId');
    if (el) el.value = id;
    var nm = document.getElementById('editUserName');
    if (nm) nm.innerText = name;
    var modal = document.getElementById('modalLeader');
    if (modal) modal.style.display = 'flex';
}

function closeModal() {
    var modal = document.getElementById('modalLeader');
    if (modal) modal.style.display = 'none';
}

function saveLeader() {
    const userId = document.getElementById('editUserId').value;
    const leaderId = document.getElementById('newLeaderId').value;

    fetch(BASE_PATH + '/admin/users/update-leader', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${userId}&leader_id=${leaderId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Líder atualizado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(err => {
        alert('Erro ao salvar: ' + err);
    });
}

/* =========================
   Permissões modal (mantivemos as funções originais)
   ========================= */

function openPermissionsModal(userId, userName) {
    document.getElementById('permUserId').value = userId;
    document.getElementById('permUserName').innerText = userName;
    document.getElementById('modalPermissions').style.display = 'flex';

    const container = document.getElementById('permissionsList');
    container.innerHTML = '<div class="text-center w-100 p-4"><div class="spinner-border text-primary"></div></div>';

    fetch(`${BASE_PATH}/admin/users/get-permissions?id=${userId}`)
        .then(res => res.json())
        .then(data => {
            container.innerHTML = '';
            if (!data || !data.permissions) {
                container.innerHTML = '<p class="text-muted">Nenhum dado de permissões.</p>';
                return;
            }
            data.permissions.forEach(group => {
                const groupEl = document.createElement('div');
                groupEl.className = 'mb-4';
                groupEl.innerHTML = `<h6 class="fw-bold">${group.group}</h6>`;
                const list = document.createElement('div');
                list.className = 'permissions-grid';
                group.items.forEach(item => {
                    const itemEl = document.createElement('div');
                    itemEl.className = 'permission-item';
                    itemEl.innerHTML = `
                        <span class="badge bg-${item.active ? 'success' : 'secondary'}">${item.active ? 'Ativo' : 'Inativo'}</span>
                        <span style="margin-left:8px">${item.label}</span>
                    `;
                    list.appendChild(itemEl);
                });
                groupEl.appendChild(list);
                container.appendChild(groupEl);
            });
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<p class="text-danger">Erro ao carregar permissões.</p>';
        });
}

function closePermissionsModal() {
    document.getElementById('modalPermissions').style.display = 'none';
}

function savePermissions() {
    const userId = document.getElementById('permUserId').value;
    const checkboxes = document.querySelectorAll('input[name="perms[]"]:checked');
    const permissionIds = Array.from(checkboxes).map(cb => cb.value);

    fetch(`${BASE_PATH}/admin/users/update-permissions`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, permissions: permissionIds })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Permissões atualizadas com sucesso!');
            closePermissionsModal();
        } else {
            alert('Erro ao salvar permissões.');
        }
    });
}

/* =========================
   Estado e carregamento de cards
   ========================= */

const expandedCards = new Set();

function toggleCardExpand(userId) {
    const card = document.querySelector(`.user-card[data-user-id="${userId}"]`);
    if (!card) return;
    const detailsContainer = card.querySelector('.card-details-container');

    if (card.classList.contains('expanded')) {
        card.classList.remove('expanded');
        expandedCards.delete(userId);
        if (detailsContainer) detailsContainer.style.display = 'none';
    } else {
        card.classList.add('expanded');
        expandedCards.add(userId);
        if (detailsContainer) detailsContainer.style.display = 'block';

        // Reset tabs
        const tabs = card.querySelectorAll('.nav-link');
        const panes = card.querySelectorAll('.tab-pane');
        tabs.forEach(t => t.classList.remove('active'));
        panes.forEach(p => p.classList.remove('show', 'active'));
        const defaultTab = card.querySelector(`a[href="#tab-abas-${userId}"]`);
        const defaultPane = card.querySelector(`#tab-abas-${userId}`);
        if (defaultTab) defaultTab.classList.add('active');
        if (defaultPane) defaultPane.classList.add('show', 'active');

        loadCardDetails(userId);
    }
}

function loadCardDetails(userId) {
    fetch(`${BASE_PATH}/admin/organograma/user-details?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) {
                console.error('Dados inválidos:', data);
                return;
            }
            fillTabObras(userId, data.obras || []);
            fillTabCidades(userId, data.cidades || []);
            fillTabAbas(userId, data.abas || []);
            fillTabPermissoes(userId, data.permissoes || {});
        })
        .catch(err => console.error('Erro ao carregar detalhes:', err));
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function fillTabResumo(userId, user) {
    const content = document.querySelector(`.details-content[data-content="resumo"][data-user-id="${userId}"]`);
    if (!content) return;
    content.innerHTML = `
        <div class="details-text-list">
            <div class="detail-item"><strong>Nome:</strong> ${escapeHtml(user.nome)}</div>
            <div class="detail-item"><strong>Login:</strong> ${escapeHtml(user.login)}</div>
            <div class="detail-item"><strong>Nível:</strong> ${escapeHtml(user.nivel_acesso || 'N/A')}</div>
            <div class="detail-item"><strong>Status:</strong> ${user.status == 0 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'}</div>
        </div>
    `;
}

function fillTabObras(userId, obras) {
    const content = document.querySelector(`.details-content[data-content="obras"][data-user-id="${userId}"]`);
    if (!content) return;

    // 1. Preparar dados e busca
    const renderList = (filterText = '') => {
        const filtered = obras.filter(o => {
            const name = (o.nome_obra || o.nome || '').toLowerCase();
            return name.includes(filterText.toLowerCase());
        });

        // Ordenar: associadas primeiro
        filtered.sort((a, b) => {
            if (!!a.associada === !!b.associada) return (a.nome_obra || a.nome || '').localeCompare(b.nome_obra || b.nome || '');
            return a.associada ? -1 : 1;
        });

        const cols = [[], [], []]; // agora 3 colunas
        filtered.forEach((o, i) => cols[i % 3].push(o));
        const maxRows = Math.max(...cols.map(c => c.length));

        let tableHtml = `<div class="obras-table-container mt-2"><table class="obras-table obras-table-3col">
            <thead><tr>${'<th></th><th>Obra</th>'.repeat(3)}</tr></thead>
            <tbody>`;

        for (let r = 0; r < maxRows; r++) {
            tableHtml += '<tr>';
            for (let c = 0; c < 3; c++) { // iterar 3 colunas
                const item = cols[c][r];
                if (item) {
                    tableHtml += `
                        <td style="text-align:center; width:30px"><input type="checkbox" class="obra-checkbox" data-id="${item.id}" ${item.associada ? 'checked' : ''}></td>
                        <td class="small">${escapeHtml(item.nome_obra || item.nome)}</td>`;
                } else { tableHtml += '<td></td><td></td>'; }
            }
            tableHtml += '</tr>';
        }
        tableHtml += '</tbody></table></div>';
        
        const listContainer = content.querySelector('.list-wrapper');
        if (listContainer) listContainer.innerHTML = tableHtml;

        // Reatachar eventos de checkbox
        content.querySelectorAll('.obra-checkbox').forEach(chk => {
            chk.addEventListener('change', () => {
                const id = parseInt(chk.dataset.id);
                const obra = obras.find(o => o.id == id);
                if (obra) obra.associada = chk.checked; // Atualiza estado local
                collectAndSendObras();
            });
        });
    };

    // 2. Montar estrutura inicial (Busca + Wrapper)
    content.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <input type="text" class="form-control form-control-sm w-50 search-obras" placeholder="Filtrar obras...">
            <div class="save-feedback"></div>
        </div>
        <div class="list-wrapper"></div>
    `;

    const searchInput = content.querySelector('.search-obras');
    searchInput.addEventListener('input', (e) => renderList(e.target.value));

    const collectAndSendObras = debounce(async () => {
        showSavingFeedback(content, true);
        const checkedIds = obras.filter(o => o.associada).map(o => o.id);
        const resp = await sendUpdateUserObras(userId, checkedIds);
        showSavingFeedback(content, false);
        if (!resp.success) alert('Erro ao salvar: ' + resp.message);
    }, 800);

    renderList();
}

function fillTabCidades(userId, cidades) {
    const content = document.querySelector(`.details-content[data-content="cidades"][data-user-id="${userId}"]`);
    if (!content) return;

    // renderList: monta 3 colunas a partir do array 'cidades'
    const renderList = (filterText = '') => {
        const filtered = (cidades || []).filter(c => {
            const name = (c.nome || '').toLowerCase();
            return name.includes(filterText.toLowerCase());
        });

        // Ordenar: associadas primeiro
        filtered.sort((a, b) => {
            if (!!a.associada === !!b.associada) return (a.nome || '').localeCompare(b.nome || '');
            return a.associada ? -1 : 1;
        });

        // Distribui em 3 colunas
        const cols = [[], [], []];
        filtered.forEach((item, i) => cols[i % 3].push(item));
        const maxRows = Math.max(...cols.map(c => c.length));

        // Monta a tabela 3-colunas (cada coluna ocupa 2 TDs: checkbox + texto)
        let tableHtml = `<div class="obras-table-container mt-2"><table class="obras-table obras-table-3col">
            <thead><tr>${'<th></th><th>Cidade</th>'.repeat(3)}</tr></thead>
            <tbody>`;

        for (let r = 0; r < maxRows; r++) {
            tableHtml += '<tr>';
            for (let c = 0; c < 3; c++) {
                const item = cols[c][r];
                if (item) {
                    const idAttr = item.id != null ? item.id : (item.value || '');
                    const checked = item.associada ? 'checked' : '';
                    const nome = escapeHtml(item.nome || item.nome_cidade || String(idAttr));

                    tableHtml += `
                        <td style="text-align:center; width:30px">
                            <input type="checkbox" class="cidade-checkbox" data-id="${idAttr}" ${checked}>
                        </td>
                        <td class="small">${nome}</td>`;
                } else {
                    tableHtml += '<td></td><td></td>';
                }
            }
            tableHtml += '</tr>';
        }

        tableHtml += '</tbody></table></div>';

        const listContainer = content.querySelector('.list-wrapper');
        if (listContainer) listContainer.innerHTML = tableHtml;

        // Reatacha eventos
        content.querySelectorAll('.cidade-checkbox').forEach(chk => {
            chk.addEventListener('change', () => {
                const id = chk.dataset.id;
                const cidade = cidades.find(c => String(c.id) === String(id) || String(c.value) === String(id));
                if (cidade) cidade.associada = chk.checked; // atualiza estado local
                collectAndSendCidades(); // debounce
            });
        });
    };

    // Estrutura inicial
    content.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <input type="text" class="form-control form-control-sm w-50 search-cidades" placeholder="Filtrar cidades...">
            <div class="save-feedback"></div>
        </div>
        <div class="list-wrapper"></div>
    `;

    const searchInput = content.querySelector('.search-cidades');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => renderList(e.target.value));
    }

    // debounce da coleta e envio (usa debounce global se existir)
    const collectAndSendCidades = typeof debounce === 'function' ? debounce(async () => {
        showSavingFeedback(content, true);
        try {
            const checkedIds = (cidades || []).filter(c => c.associada).map(c => c.id);
            const resp = await sendUpdateUserCidades(userId, checkedIds);
            showSavingFeedback(content, false);
            if (!resp.success) alert('Erro ao salvar: ' + (resp.message || 'erro desconhecido'));
        } catch (err) {
            showSavingFeedback(content, false);
            console.error('Erro ao enviar cidades:', err);
            alert('Erro ao salvar cidades');
        }
    }, 800) : (async () => {
        showSavingFeedback(content, true);
        const checkedIds = (cidades || []).filter(c => c.associada).map(c => c.id);
        const resp = await sendUpdateUserCidades(userId, checkedIds);
        showSavingFeedback(content, false);
        if (!resp.success) alert('Erro ao salvar: ' + (resp.message || 'erro desconhecido'));
    });

    // Render inicial
    renderList();
}

function fillTabAbas(userId, abas) {
    const content = document.querySelector(`.details-content[data-content="abas"][data-user-id="${userId}"]`);
    if (!content) return;

    if (!Array.isArray(abas) || abas.length === 0) {
        content.innerHTML = '<div class="text-muted">Nenhuma aba cadastrada</div>';
        return;
    }

    // Normaliza para garantir formato esperado
    const normalized = abas.map(a => ({
        id: String(a.id),
        nome: a.nome || a.nome_exibicao || ('Aba ' + a.id),
        active: !!a.active
    }));

    // 3 colunas (como obras/cidades)
    const cols = [[], [], []];
    normalized.forEach((item, i) => cols[i % 3].push(item));
    const maxRows = Math.max(...cols.map(c => c.length));

    let tableHtml = `<div class="obras-table-container mt-2"><table class="obras-table obras-table-3col">
        <thead><tr>${'<th style="width:30px"></th><th>Abas</th>'.repeat(3)}</tr></thead><tbody>`;

    for (let r = 0; r < maxRows; r++) {
        tableHtml += '<tr>';
        for (let c = 0; c < 3; c++) {
            const item = cols[c][r];
            if (item) {
                const checked = item.active ? 'checked' : '';
                tableHtml += `
                    <td style="text-align:center; width:30px">
                        <input type="checkbox" class="aba-checkbox" data-id="${escapeHtml(item.id)}" ${checked}>
                    </td>
                    <td class="small">
                        <span class="${item.active ? 'text-dark fw-semibold' : 'text-muted'}">
                            ${escapeHtml(item.nome)}
                        </span>
                    </td>`;
            } else {
                tableHtml += '<td></td><td></td>';
            }
        }
        tableHtml += '</tr>';
    }

    tableHtml += '</tbody></table></div>';

    // Wrapper com search + feedback (consistente com obras/cidades)
    content.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <input type="text" class="form-control form-control-sm w-50 search-abas" placeholder="Filtrar abas...">
            <div class="save-feedback"></div>
        </div>
        <div class="list-wrapper">${tableHtml}</div>
    `;

    const searchInput = content.querySelector('.search-abas');
    const listWrapper = content.querySelector('.list-wrapper');

    // Função para filtrar (re-render simples)
    const renderFiltered = (filterText = '') => {
        const filtered = normalized.filter(i => i.nome.toLowerCase().includes(filterText.toLowerCase()));
        // redistribui em 3 colunas
        const c = [[], [], []];
        filtered.forEach((it, idx) => c[idx % 3].push(it));
        const rows = Math.max(...c.map(cc => cc.length));
        let html = `<div class="obras-table-container mt-2"><table class="obras-table obras-table-3col">
            <thead><tr>${'<th style="width:30px"></th><th>Abas</th>'.repeat(3)}</tr></thead><tbody>`;
        for (let rr = 0; rr < rows; rr++) {
            html += '<tr>';
            for (let cc = 0; cc < 3; cc++) {
                const it = c[cc][rr];
                if (it) {
                    const checked = it.active ? 'checked' : '';
                    html += `
                        <td style="text-align:center; width:30px">
                            <input type="checkbox" class="aba-checkbox" data-id="${escapeHtml(it.id)}" ${checked}>
                        </td>
                        <td class="small">
                            <span class="${it.active ? 'text-dark fw-semibold' : 'text-muted'}">
                                ${escapeHtml(it.nome)}
                            </span>
                        </td>`;
                } else {
                    html += '<td></td><td></td>';
                }
            }
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        const wrapper = content.querySelector('.list-wrapper');
        if (wrapper) wrapper.innerHTML = html;
        attachCheckboxHandlers();
    };

    if (searchInput) {
        searchInput.addEventListener('input', (e) => renderFiltered(e.target.value));
    }

    // Atualiza estado local e envia para o servidor (debounced)
    const collectAndSendTabs = typeof debounce === 'function' ? debounce(async () => {
        showSavingFeedback(content, true);
        try {
            const current = Array.from(content.querySelectorAll('.aba-checkbox'))
                .filter(chk => chk.checked)
                .map(chk => String(chk.dataset.id));
            const resp = await sendUpdateUserTabs(userId, current);
            showSavingFeedback(content, false);
            if (!resp.success) alert('Erro ao salvar abas: ' + (resp.message || 'erro desconhecido'));
        } catch (err) {
            showSavingFeedback(content, false);
            console.error('Erro ao salvar abas:', err);
            alert('Erro ao salvar abas');
        }
    }, 800) : (async () => {
        showSavingFeedback(content, true);
        const current = Array.from(content.querySelectorAll('.aba-checkbox'))
            .filter(chk => chk.checked)
            .map(chk => String(chk.dataset.id));
        const resp = await sendUpdateUserTabs(userId, current);
        showSavingFeedback(content, false);
        if (!resp.success) alert('Erro ao salvar abas: ' + (resp.message || 'erro desconhecido'));
    });

    // Anexa handlers às checkboxes
    function attachCheckboxHandlers() {
        content.querySelectorAll('.aba-checkbox').forEach(chk => {
            // evite adicionar múltiplos listeners
            chk.removeEventListener('change', onCheckboxChange);
            chk.addEventListener('change', onCheckboxChange);
        });
    }

    function onCheckboxChange(e) {
        // Atualiza array normalized para manter estado local coerente
        const id = String(e.target.dataset.id);
        const checked = e.target.checked;
        const idx = normalized.findIndex(x => String(x.id) === id);
        if (idx !== -1) normalized[idx].active = checked;
        // dispara envio debounced
        collectAndSendTabs();
    }

    // Inicializar handlers
    attachCheckboxHandlers();

    // Guarda estado em dataset para possíveis usos futuros
    try {
        content.dataset.tabsState = JSON.stringify(normalized);
    } catch (err) { /* ignore */ }
}

function fillTabPermissoes(userId, permissoes) {
    const content = document.querySelector(`.details-content[data-content="permissoes"][data-user-id="${userId}"]`);
    if (!content) return;

    if (!permissoes || (!permissoes.ativas && !permissoes.inativas)) {
        content.innerHTML = '<div class="text-muted">Nenhuma permissão atribuída</div>';
        return;
    }

    let html = '';

    

    html += '<div class="perms-two-column">';

    // Coluna 1: permissões ativas (grid)
    html += '<div class="perms-active">';

    // Cabeçalho / seção de ativas
    html += '<div class="detail-section"><strong class="text-success">✓ Ativas:</strong></div>';

    if (permissoes.ativas && permissoes.ativas.length > 0) {
        html += '<div class="permissions-grid-card" id="perms-active-grid-' + userId + '">';
        permissoes.ativas.forEach(perm => {
            const pid = perm.campo || perm.id || perm.nome_exibicao;
            html += `<div class="permission-item-card perm-draggable" draggable="true" data-perm="${escapeHtml(pid)}" data-active="true">${escapeHtml(perm.nome_exibicao || pid)}</div>`;
        });
        html += '</div>';
    } else {
        html += '<div class="text-muted">Nenhuma permissão ativa</div>';
    }

    // if (permissoes.inativas && permissoes.inativas.length > 0) {
    //     html += `<div><button class="btn btn-sm btn-outline-secondary btn-activate-inline" onclick="openActivatePermissionsModal(${userId}, '${escapeHtml(permissoes.userName || 'Usuário')}')"><i class="bi bi-plus-circle"></i> Ativar permissões (${permissoes.inativas.length} inativas)</button></div>`;
    // }
    html += '</div>'; // .perms-active

    // Coluna 2: wrapper vertical contendo o título (FORA do container) e abaixo o painel de inativas
    html += `<div class="perms-inactive-column">`;

    // Painel inativas
    html += `<div class="perms-inactive" id="perms-inactive-panel-${userId}">`;
    
    // Título fora do container inativo, mesmo padrão do das ativas (antagonista)
    html += '<div class="detail-section"><strong class="text-danger">✗ Inativas:</strong></div>';
    
    if (permissoes.inativas && permissoes.inativas.length > 0) {
        html += `<div class="perms-inactive-list" id="perms-inactive-list-${userId}">`;
        permissoes.inativas.forEach(perm => {
            const pid = perm.campo || perm.id || perm.nome_exibicao;
            html += `<div class="perm-inactive-item perm-draggable" draggable="true" data-perm="${escapeHtml(pid)}" data-active="false">${escapeHtml(perm.nome_exibicao || pid)}</div>`;
        });
        html += `</div>`;
    } else {
        html += '<div class="text-muted small">Sem permissões inativas</div>';
    }
    html += `</div>`; // .perms-inactive

    html += `</div>`; // .perms-inactive-column

    html += '</div>'; // .perms-two-column

    content.innerHTML = html;

    // Armazenar arrays para uso posterior
    content.dataset.inactivePermissions = JSON.stringify(permissoes.inativas || []);
    content.dataset.activePermissions = JSON.stringify(permissoes.ativas || []);

    // Inicializar drag & drop
    attachPermDragHandlers(userId);
}

/* =========================
   Pan (arrastar o canvas) + Fullscreen
   ========================= */

// Variáveis globais para manter a posição mesmo após o AJAX recarregar a árvore
let currentTranslateX = 0;
let currentTranslateY = 0;

function initPanAndFullscreen() {
    const wrapper = document.querySelector('.tree-wrapper');
    // Buscamos a tree toda vez que o evento ocorre para garantir que pegamos a nova (pós-AJAX)
    if (!wrapper) return;

    let isDown = false;
    let startX = 0, startY = 0;
    let originX = 0, originY = 0;
    const speedMultiplier = 1.2; // Ajuste a sensibilidade aqui

    function isInteractionTarget(el) {
        if (!el) return false;
        return !!el.closest(
            'button, a, input, textarea, select, ' +
            '.user-actions-wrapper, .card-details-container, ' +
            '.custom-modal, .org-toolbar-fixed-bottom, .offcanvas'
        );
    }

    // Função interna para aplicar o transform na árvore atual
    function applyTransform() {
        const tree = wrapper.querySelector('.tree');
        if (tree) {
            tree.style.transform = `translate(${currentTranslateX}px, ${currentTranslateY}px)`;
        }
    }

    wrapper.addEventListener('pointerdown', (e) => {
        // Se clicar em botões ou elementos de interação, não arrasta
        if (isInteractionTarget(e.target)) return;
        // Apenas botão esquerdo do mouse
        if (e.pointerType === 'mouse' && e.button !== 0) return;

        isDown = true;
        wrapper.classList.add('panning');
        
        startX = e.clientX;
        startY = e.clientY;
        originX = currentTranslateX;
        originY = currentTranslateY;

        // Pointer Capture evita que o arraste "escape" se o mouse sair do wrapper
        wrapper.setPointerCapture(e.pointerId);
        e.preventDefault();
    });

    wrapper.addEventListener('pointermove', (e) => {
        if (!isDown) return;
        
        currentTranslateX = originX + (e.clientX - startX) * speedMultiplier;
        currentTranslateY = originY + (e.clientY - startY) * speedMultiplier;
        
        applyTransform();
    });

    wrapper.addEventListener('pointerup', (e) => {
        isDown = false;
        wrapper.classList.remove('panning');
        wrapper.releasePointerCapture(e.pointerId);
    });

    wrapper.addEventListener('pointercancel', (e) => {
        isDown = false;
        wrapper.classList.remove('panning');
        wrapper.releasePointerCapture(e.pointerId);
    });

    // Expõe a função globalmente para que o AJAX possa chamar após carregar
    window.reapplyTreeTransform = applyTransform;
    
    // Aplica a posição inicial
    applyTransform();
}

// Inicialização
document.addEventListener('DOMContentLoaded', initPanAndFullscreen);

/* =========================
   Debounce helper + search
   ========================= */

function debounce(fn, wait) {
    let t;
    return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}

let searchTimeout;
function handleSearch(query) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyOrgFilters(query.toLowerCase());
    }, 200);
}

/**
 * Busca a árvore no servidor baseada no status selecionado
 * Chamada pelo onchange do #adv-filter-status
 */
// Controller reutilizável para cancelar fetchs anteriores
let currentTreeFetchController = null;

function fetchTreeByStatus(status) {
    // Cancela requisição anterior, se existir
    if (currentTreeFetchController) {
        try { currentTreeFetchController.abort(); } catch(e) {}
    }
    currentTreeFetchController = new AbortController();
    const { signal } = currentTreeFetchController;

    const treeContainer = document.querySelector('.tree');
    if (treeContainer) treeContainer.style.opacity = '0.5';

    const includeOrphans = document.getElementById('adv-filter-orphans')?.checked ? 1 : 0;
    const url = `${BASE_PATH}/admin/organograma/fetch-tree?status=${encodeURIComponent(status)}&includeOrphans=${includeOrphans}`;

    fetch(url, { signal })
        .then(response => {
            if (!response.ok) throw new Error('Erro ao buscar dados do servidor');
            return response.text();
        })
        .then(html => {
            // Verifica se o container ainda está no DOM (evita erros se a página mudou)
            if (!treeContainer || !document.body.contains(treeContainer)) return;

            // Insere o HTML novo
            treeContainer.innerHTML = html;
            treeContainer.style.opacity = '1';

            // Se você usa pan/zoom ou plugins, reinicialize aqui (exemplo comentado)
            // if (window.panzoomInstance) { window.panzoomInstance.dispose(); initPanzoom(); }

            // Reaplica os filtros visuais (search/abas/perms). applyOrgFilters já está preparada.
            try {
                applyOrgFilters();
            } catch (ex) {
                console.error('Erro ao aplicar filtros após fetch:', ex);
            }
        })
        .catch(error => {
            // Se foi abort, não mostrar mensagem de erro
            if (error.name === 'AbortError') {
                // console.log('Fetch da árvore abortado');
                return;
            }
            console.error('Erro:', error);
            alert('Erro ao carregar árvore. Tente novamente.');
            if (treeContainer) treeContainer.style.opacity = '1';
        });
}

/* =========================
   Filtros Visuais Unificados
   ========================= */


function applyOrgFilters() {
    try {
        const searchTerm = (document.getElementById('org-search-input')?.value || '').toLowerCase().trim();
        const rawLeader = document.getElementById('adv-filter-leader')?.value;
        const selectedLeaderId = (rawLeader === undefined || rawLeader === null || rawLeader === '' || rawLeader === '0') ? '' : String(rawLeader);

        // Substitua as linhas de selectedAbas e selectedPerms por estas:
        const selectedAbas = Array.from(document.querySelectorAll('.adv-filter-aba:checked'))
            .map(cb => parseInt(cb.value, 10))
            .filter(n => !isNaN(n));

        const selectedPerms = Array.from(document.querySelectorAll('.adv-filter-permissao:checked'))
            .map(cb => String(cb.value).trim().toLowerCase())
            .filter(s => s !== '');

        const allItems = document.querySelectorAll('.tree li');
        const allCards = document.querySelectorAll('.user-card');

        console.log('applyOrgFilters start', {
            searchTerm,
            selectedLeaderId,
            selectedAbas,
            selectedPerms,
            totalCards: allCards.length
        });

        // Sem filtros ativos: mostra tudo e sai
        if (!searchTerm && !selectedLeaderId && selectedAbas.length === 0 && selectedPerms.length === 0) {
            allItems.forEach(li => li.style.display = '');
            return;
        }

        // Esconde tudo inicialmente
        allItems.forEach(li => li.style.display = 'none');

        // Percorre todos os cards (se nenhum card existir, nada acontece)
        allCards.forEach(card => {
            if (!card) return;

            const cardName  = (card.querySelector('.user-full-name')?.textContent || '').toLowerCase().trim();
            const cardLogin = (card.querySelector('.user-first-name')?.textContent || '').toLowerCase().trim();
            const cardUserId  = (card.getAttribute('data-user-id') || '').toString();
            const cardLiderId = (card.getAttribute('data-lider') || '').toString();

            // Substitua as linhas de cardAbas e cardPerms por estas:
            const cardAbas = (card.getAttribute('data-abas') || '')
                .split(',')
                .map(s => parseInt(s.trim(), 10))
                .filter(n => !isNaN(n));

            const cardPerms = (card.getAttribute('data-permissoes') || '')
                .split(',')
                .map(s => s.trim().toLowerCase())
                .filter(s => s !== '');

            let isMatch = true;

            // 1) Filtro de texto (nome ou login)
            if (searchTerm) {
                if (!(cardName.includes(searchTerm) || cardLogin.includes(searchTerm))) {
                    isMatch = false;
                }
            }

            // 2) Filtro de líder (mantendo a sua lógica original: mostra o líder e seus subordinados imediatos)
            if (isMatch && selectedLeaderId) {
                const isTheLider = (cardUserId === selectedLeaderId);
                const isSubordinate = (cardLiderId === selectedLeaderId);
                if (!isTheLider && !isSubordinate) isMatch = false;
            }

            // 3) Filtro de Abas
            if (isMatch && selectedAbas.length > 0) {
                // Verifica se o card tem PELO MENOS UM dos IDs selecionados
                const hasAba = selectedAbas.some(id => cardAbas.includes(id));
                if (!hasAba) isMatch = false;
            }

            // 4) Filtro de Permissões
            if (isMatch && selectedPerms.length > 0) {
                const hasPerm = selectedPerms.some(p => cardPerms.includes(p));
                if (!hasPerm) isMatch = false;
            }

            // Se passou por todos os filtros ativos, mostra o caminho até a raiz e (se for o líder selecionado) todos os subordinados
            if (isMatch) {
                showPathToRoot(card);

                if (selectedLeaderId && cardUserId === selectedLeaderId) {
                    // Mostra todas as linhas abaixo do líder selecionado
                    showAllSubordinates(card);
                }
            }
        });
    } catch (err) {
        // Falha segura — não quebra a página; log pra debugar
        console.error('applyOrgFilters error:', err);
    }
}

// Helper: mostra o nó do card e todos os ancestrais <li> até a raiz da .tree
function showPathToRoot(card) {
    const li = card.closest('li');
    if (!li) return;

    let current = li;
    while (current) {
        current.style.display = ''; // mostra o <li> atual
        // também garanta que o <ul> pai esteja visível (caso o CSS use display:none em uls)
        const parentUL = current.parentElement;
        if (parentUL && parentUL.tagName && parentUL.tagName.toLowerCase() === 'ul') {
            parentUL.style.display = '';
        }
        // sobe para o <li> pai (se existir)
        current = parentUL ? parentUL.closest('li') : null;
    }
}

// Helper: mostra o nó do card e todos os <li> descendentes (subárvore)
function showAllSubordinates(card) {
    const li = card.closest('li');
    if (!li) return;
    // mostra o próprio li
    li.style.display = '';
    // mostra todos os li descendentes
    li.querySelectorAll('li').forEach(subLi => subLi.style.display = '');
}

function filterHierarchy(query) {
    const allNodes = document.querySelectorAll('.user-node');
    
    // Para cada nó visível, garante que os pais também fiquem visíveis
    allNodes.forEach(node => {
        if (!node.classList.contains('filtered-out')) {
            let cur = node;
            while (cur && cur.classList && cur.classList.contains('user-node')) {
                cur.classList.remove('filtered-out');
                
                // Expande o pai para mostrar o filho que deu match
                const parentLi = cur.parentElement.closest('li.user-node');
                if (parentLi) {
                    parentLi.classList.remove('collapsed');
                    const trigger = parentLi.querySelector('.expand-trigger');
                    if (trigger) trigger.setAttribute('aria-expanded', 'true');
                }
                cur = parentLi;
            }
        }
    });
}

function clearOrgSearch() {
    const input = document.getElementById('org-search-input');
    if (input) input.value = '';
    const sel = document.getElementById('filter-status');
    if (sel) sel.value = 'all';
    applyOrgFilters('');
}

/* =========================
   Inicialização Robusta
   ========================= */

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initPanAndFullscreen();
} else {
    document.addEventListener('DOMContentLoaded', initPanAndFullscreen);
}

function toggleFilterPanel() {
    const el = document.getElementById('offcanvasFilters');
    if (!el) return;

    // Tenta pegar a instância existente
    let instance = bootstrap.Offcanvas.getInstance(el);
    
    // Se não existir, cria uma nova
    if (!instance) {
        instance = new bootstrap.Offcanvas(el);
    }

    // Verifica se está visível no momento para decidir se esconde ou mostra
    if (el.classList.contains('show')) {
        instance.hide();
    } else {
        instance.show();
    }
}

// Função para limpar todos os filtros avançados
function resetAdvFilters() {
    const form = document.getElementById('form-filters-adv');
    if (form) form.reset();
    
    const searchInput = document.getElementById('org-search-input');
    if (searchInput) searchInput.value = '';

    // 1. Limpa os filtros visuais do DOM imediatamente
    applyOrgFilters();

    // 2. O reset do status volta para 'active' e recarrega a árvore original do servidor
    fetchTreeByStatus('active');
}

function recenterTree() {
    const tree = document.querySelector('.tree');
    if (!tree) return;

    // Resetamos as variáveis globais de controle do Pan (se você as tiver no escopo global)
    // Caso contrário, apenas resetamos o estilo:
    tree.style.transition = "transform 0.4s ease-out"; // Suaviza o movimento
    tree.style.transform = `translate(0px, 0px) scale(1)`;
    
    // Remove a transição após o movimento para não atrasar o Pan manual depois
    setTimeout(() => {
        tree.style.transition = "none";
    }, 400);

    // Se você usa variáveis startX/startY globais para o Pan, resete-as aqui também
    translateX = 0;
    translateY = 0;
}

// Reusa o mesmo controller global para cancelar chamadas anteriores
// (declared previously: let currentTreeFetchController = null;)
async function fetchTreeFromLeader(leaderId) {
    // Cancela requisição anterior, se existir
    if (currentTreeFetchController) {
        try { currentTreeFetchController.abort(); } catch(e) {}
    }
    currentTreeFetchController = new AbortController();
    const { signal } = currentTreeFetchController;

    const tree = document.querySelector('.tree');
    if (!tree) return;

    // Mostra feedback visual
    tree.style.opacity = '0.5';

    const status = document.getElementById('adv-filter-status')?.value || 'active';

    // Se limpar o líder, volta para a árvore por status (reaplica o mesmo fluxo de cancelamento)
    if (!leaderId) {
        try {
            // Cancela o controller atual antes de delegar (evita dup)
            try { currentTreeFetchController.abort(); } catch(e) {}
            currentTreeFetchController = null;
            fetchTreeByStatus(status);
        } finally {
            // Garante que o estado visual será restaurado pela fetchTreeByStatus
        }
        return;
    }

    const includeOrphans = document.getElementById('adv-filter-orphans')?.checked ? 1 : 0;
    const url = `${BASE_PATH}/admin/organograma/fetch-leader-tree?leaderId=${encodeURIComponent(leaderId)}&status=${encodeURIComponent(status)}&includeOrphans=${includeOrphans}`;

    try {
        const resp = await fetch(url, { method: 'GET', signal });

        if (!resp.ok) {
            throw new Error(`Erro ao buscar árvore do líder (${resp.status})`);
        }

        const html = await resp.text();

        // Verifica se o container ainda está no DOM
        if (!document.body.contains(tree)) return;

        tree.innerHTML = html;
        tree.style.opacity = '1';

        // Reinicialize plugins se necessário (pan/zoom, tooltips, etc.)
        // ex: if (window.panzoomInstance) { window.panzoomInstance.dispose(); initPanzoom(); }

        // Reaplica filtros visuais (search/abas/perms)
        try {
            applyOrgFilters();
        } catch (ex) {
            console.error('Erro ao aplicar filtros após fetch do líder:', ex);
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            // fetch abortado — silencioso
            return;
        }
        console.error('fetchTreeFromLeader erro:', error);
        alert('Erro ao carregar árvore do líder. Tente novamente.');
        tree.style.opacity = '1';
    } finally {
        // limpa controller atual (se ainda for o mesmo)
        if (currentTreeFetchController && currentTreeFetchController.signal === signal) {
            currentTreeFetchController = null;
        }
    }
}

// No final do seu organograma.js, adicione/substitua:
if (typeof jQuery === 'undefined') {
    console.error('jQuery não carregado. Certifique-se de incluir jQuery antes de organograma.js');
} else {
    jQuery(function($){
        $(document).ready(function() {
            // 1. Quando mudar o Líder no Offcanvas -> Busca nova árvore
            $('#adv-filter-leader').on('change', function() {
                const leaderId = $(this).val();
                if (leaderId) {
                    fetchTreeFromLeader(leaderId);
                } else {
                    // Se limpar o líder, volta para a árvore padrão (status ativo)
                    fetchTreeByStatus('active');
                }
            });

            // 2. Quando mudar o Status no Offcanvas -> Busca nova árvore
            $('#adv-filter-status').on('change', function() {
                const status = $(this).val();
                fetchTreeByStatus(status);
            });

            // 3. Quando mudar o Switch de Órfãos -> Busca nova árvore
            $('#adv-filter-orphans').on('change', function() {
                const status = $('#adv-filter-status').val() || 'active';
                fetchTreeByStatus(status);
            });

            // 4. A pesquisa por texto continua sendo apenas visual (DOM)
            $('#org-search-input').on('keyup', function() {
                handleSearch($(this).val());
            });
        });
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('adv-filter-aba') || e.target.classList.contains('adv-filter-permissao')) {
            applyOrgFilters();
        }
    });
}