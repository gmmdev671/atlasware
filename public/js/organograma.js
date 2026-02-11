// organograma.js - funções de interação do organograma (com permissão drag & drop)

/* =========================
   Helpers iniciais e toggles
   ========================= */

// Toggle individual (recebe o elemento trigger — botão .expand-trigger)
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

// Expande todos os nós que possuem subordinados
function expandAll() {
    document.querySelectorAll('.organograma-humano-page .tree li.has-sub').forEach(function(li){
        li.classList.remove('collapsed');
        var trigger = li.querySelector('.expand-trigger');
        if (trigger && trigger.getAttribute('aria-hidden') !== 'true') trigger.setAttribute('aria-expanded', 'true');
        var icon = li.querySelector('.expand-trigger .toggle-icon');
        if (icon) icon.classList.remove('rotated');
    });
}

// Colapsa todos os nós que possuem subordinados
function collapseAll() {
    document.querySelectorAll('.organograma-humano-page .tree li.has-sub').forEach(function(li){
        li.classList.add('collapsed');
        var trigger = li.querySelector('.expand-trigger');
        if (trigger && trigger.getAttribute('aria-hidden') !== 'true') trigger.setAttribute('aria-expanded', 'false');
        var icon = li.querySelector('.expand-trigger .toggle-icon');
        if (icon) icon.classList.add('rotated');
    });
}

// Acessibilidade: ligar teclado (Enter / Space) aos triggers existentes
document.addEventListener('keydown', function(e){
    var target = e.target;
    if (!target || !target.classList) return;
    if (target.classList.contains('expand-trigger')) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleSub(target);
        }
    }
});

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
   Modal Permissões (global)
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

            if (data.scopes && data.scopes.length) {
                const scopeGroup = document.createElement('div');
                scopeGroup.className = 'mt-4 pt-3 border-top';
                scopeGroup.innerHTML = `<h6 class="fw-bold">Escopo de Acesso</h6>`;
                const scopeList = document.createElement('ul');
                scopeList.className = 'list-group';

                data.scopes.forEach(scope => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between';
                    li.innerHTML = `<strong>${scope.label}</strong> <span>${scope.value}</span>`;
                    scopeList.appendChild(li);
                });

                scopeGroup.appendChild(scopeList);
                container.appendChild(scopeGroup);
            }
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
        loadCardDetails(userId);
    }
}

function loadCardDetails(userId) {
    fetch(`${BASE_PATH}/admin/organograma/user-details?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (!data || data.error) {
                console.error(data && data.error ? data.error : 'Dados inválidos');
                return;
            }
            fillTabResumo(userId, data.user);
            fillTabObras(userId, data.obras);
            fillTabCidades(userId, data.cidades);
            fillTabAbas(userId, data.abas);
            fillTabPermissoes(userId, data.permissoes);
        })
        .catch(err => console.error('Erro ao carregar detalhes:', err));
}

/* =========================
   Preenchimento de abas (Resumo/Obras/Acessos/Abas)
   ========================= */

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

// helper debounce (adicione se não existir)
function debounce(fn, wait) {
    let t;
    return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}

// envia atualização de obras para o servidor
async function sendUpdateUserObras(userId, obraIds) {
    try {
        const resp = await fetch(BASE_PATH + '/admin/obras/updateUserObras', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId: Number(userId), obraIds: obraIds })
        });
        const json = await resp.json();
        return json;
    } catch (err) {
        console.error('Erro sendUpdateUserObras', err);
        return { success: false, message: err.message || 'Erro de rede' };
    }
}

// envia atualização de cidades para o servidor
async function sendUpdateUserCidades(userId, cidadeIds) {
    try {
        const resp = await fetch(BASE_PATH + '/admin/cidades/updateUserCidades', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId: Number(userId), cidadeIds: cidadeIds })
        });
        const json = await resp.json();
        return json;
    } catch (err) {
        console.error('Erro sendUpdateUserCidades', err);
        return { success: false, message: err.message || 'Erro de rede' };
    }
}

// Helper para mostrar feedback de salvamento
function showSavingFeedback(container, isSaving) {
    let feedback = container.querySelector('.save-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'save-feedback';
        feedback.style = 'position: sticky; top: 0; right: 0; background: #fff3cd; padding: 2px 10px; border-radius: 4px; font-size: 11px; display: none; float: right; z-index: 10; border: 1px solid #ffeeba;';
        container.prepend(feedback);
    }
    if (isSaving) {
        feedback.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Salvando...';
        feedback.style.display = 'block';
    } else {
        feedback.innerHTML = '<span class="text-success">✓ Salvo</span>';
        setTimeout(() => { if (feedback) feedback.style.display = 'none'; }, 2000);
    }
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

// Substitua pela nova versão de fillTabAbas (organograma.js)

async function sendUpdateUserTabs(userId, activeTabIds) {
    // Endpoint sugerido: /admin/organograma/update-user-tabs
    // Ajuste conforme sua rota real.
    try {
        const resp = await fetch(`${BASE_PATH}/admin/organograma/update-user-tabs`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId: Number(userId), activeTabs: activeTabIds })
        });
        const text = await resp.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Resposta inválida do servidor (não JSON):', text);
            return { success: false, message: 'Resposta inválida do servidor' };
        }
    } catch (err) {
        console.error('Erro sendUpdateUserTabs', err);
        return { success: false, message: err.message || 'Erro de rede' };
    }
}

// debounce já existe no seu arquivo; se não existir, usar esta fallback:
// const debounce = window.debounce || function(fn, wait){ let t; return function(...a){ clearTimeout(t); t = setTimeout(()=>fn.apply(this,a), wait); }; };
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

/* =========================
   Permissões (duas colunas + drag & drop)
   ========================= */

/**
 * Exibe as permissões em 2 colunas:
 * - col 1: permissões ativas (grid)
 * - col 2: permissões inativas (painel lateral)
 *
 * Espera que 'permissoes' tenha: { ativas: [...], inativas: [...], userName (opcional) }
 */
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

/* --------------------------
   Drag & Drop helpers
   -------------------------- */

function attachPermDragHandlers(userId) {
    const activeGrid = document.getElementById(`perms-active-grid-${userId}`);
    const inactiveList = document.getElementById(`perms-inactive-list-${userId}`);
    const inactivePanel = document.getElementById(`perms-inactive-panel-${userId}`);

    const itemsSelector = `#perms-active-grid-${userId} .perm-draggable, #perms-inactive-list-${userId} .perm-draggable`;
    document.querySelectorAll(itemsSelector).forEach(item => {
        item.addEventListener('dragstart', onPermDragStart);
        item.addEventListener('dragend', onPermDragEnd);
    });

    if (activeGrid) {
        activeGrid.addEventListener('dragover', onPermDragOver);
        activeGrid.addEventListener('drop', function(e){ onPermDrop(e, userId, true); });
        activeGrid.addEventListener('dragleave', onPermDragLeave);
    }
    if (inactiveList) {
        inactiveList.addEventListener('dragover', onPermDragOver);
        inactiveList.addEventListener('drop', function(e){ onPermDrop(e, userId, false); });
        inactiveList.addEventListener('dragleave', onPermDragLeave);
    }
    if (inactivePanel) {
        inactivePanel.addEventListener('dragover', onPermDragOver);
        inactivePanel.addEventListener('drop', function(e){ onPermDrop(e, userId, false); });
        inactivePanel.addEventListener('dragleave', onPermDragLeave);
    }
}

function onPermDragStart(e) {
    const el = e.currentTarget;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', el.getAttribute('data-perm') || '');
    el.classList.add('dragging');
    e.dataTransfer.setData('from-active', el.getAttribute('data-active') === 'true' ? '1' : '0');
}

function onPermDragEnd(e) {
    const el = e.currentTarget;
    if (el) el.classList.remove('dragging');
    document.querySelectorAll('.drop-target').forEach(d => d.classList.remove('drop-target'));
}

function onPermDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const target = e.currentTarget;
    if (target) target.classList.add('drop-target');
}

function onPermDragLeave(e) {
    if (e.currentTarget) e.currentTarget.classList.remove('drop-target');
}

/* --------------------------
   onPermDrop (async) - substituição
   -------------------------- */

async function onPermDrop(e, userId, toActive) {
    e.preventDefault();
    const dropTarget = e.currentTarget;
    if (dropTarget) dropTarget.classList.remove('drop-target');

    const permId = e.dataTransfer.getData('text/plain');
    const fromActive = e.dataTransfer.getData('from-active') === '1';
    if (!permId) return;

    if (toActive && fromActive) return;
    if (!toActive && !fromActive) return;

    // chama o endpoint apropriado e aguarda o JSON
    if (toActive) {
        const res = await activatePermissionBatch(userId, [permId]);
        if (res && res.success) {
            moveDomToActive(userId, permId);
        } else {
            alert('Erro ao ativar permissão: ' + (res && res.message ? res.message : 'erro desconhecido'));
        }
    } else {
        const res = await deactivatePermissionBatch(userId, [permId]);
        if (res && res.success) {
            moveDomToInactive(userId, permId);
        } else {
            alert('Erro ao desativar permissão: ' + (res && res.message ? res.message : 'erro desconhecido'));
        }
    }
}

/* --------------------------
   DOM helpers para mover itens
   -------------------------- */

/* --------------------------
   DOM helpers para mover itens (Adicionar ao final do arquivo)
   -------------------------- */

function moveDomToActive(userId, permId) {
    const content = document.querySelector(`.details-content[data-content="permissoes"][data-user-id="${userId}"]`);
    const item = document.querySelector(`#perms-inactive-list-${userId} .perm-draggable[data-perm="${cssEscape(permId)}"]`);
    const activeGrid = document.getElementById(`perms-active-grid-${userId}`);
    
    if (item && activeGrid) {
        const clone = item.cloneNode(true);
        clone.setAttribute('data-active', 'true');
        clone.classList.remove('perm-inactive-item');
        clone.classList.add('permission-item-card');
        
        // Reatacha os eventos de drag no novo elemento
        clone.addEventListener('dragstart', onPermDragStart);
        clone.addEventListener('dragend', onPermDragEnd);
        
        activeGrid.appendChild(clone);
        item.remove();

        // Atualiza os datasets para manter o modal de ativação sincronizado
        try {
            const inactiveArr = JSON.parse(content.dataset.inactivePermissions || '[]');
            const activeArr = JSON.parse(content.dataset.activePermissions || '[]');
            const idx = inactiveArr.findIndex(p => (p.campo || p.id || p.nome_exibicao || p) == permId);
            let moved = idx !== -1 ? inactiveArr.splice(idx, 1)[0] : { campo: permId, nome_exibicao: permId };
            activeArr.push(moved);
            content.dataset.inactivePermissions = JSON.stringify(inactiveArr);
            content.dataset.activePermissions = JSON.stringify(activeArr);
        } catch (err) { console.warn('Erro ao atualizar dataset active', err); }
    }
}

function moveDomToInactive(userId, permId) {
    const content = document.querySelector(`.details-content[data-content="permissoes"][data-user-id="${userId}"]`);
    const item = document.querySelector(`#perms-active-grid-${userId} .perm-draggable[data-perm="${cssEscape(permId)}"]`);
    const inactiveList = document.getElementById(`perms-inactive-list-${userId}`);
    
    if (item && inactiveList) {
        const clone = item.cloneNode(true);
        clone.setAttribute('data-active', 'false');
        clone.classList.remove('permission-item-card');
        clone.classList.add('perm-inactive-item');
        
        // Reatacha os eventos de drag no novo elemento
        clone.addEventListener('dragstart', onPermDragStart);
        clone.addEventListener('dragend', onPermDragEnd);
        
        inactiveList.appendChild(clone);
        item.remove();

        // Atualiza os datasets para manter o modal de ativação sincronizado
        try {
            const inactiveArr = JSON.parse(content.dataset.inactivePermissions || '[]');
            const activeArr = JSON.parse(content.dataset.activePermissions || '[]');
            const idx = activeArr.findIndex(p => (p.campo || p.id || p.nome_exibicao || p) == permId);
            let moved = idx !== -1 ? activeArr.splice(idx, 1)[0] : { campo: permId, nome_exibicao: permId };
            inactiveArr.push(moved);
            content.dataset.inactivePermissions = JSON.stringify(inactiveArr);
            content.dataset.activePermissions = JSON.stringify(activeArr);
        } catch (err) { console.warn('Erro ao atualizar dataset inactive', err); }
    }
}

/* --------------------------
   Chamadas servidor (batch) - substituições
   -------------------------- */

/**
 * Ativa (define = 0) as permissões informadas para o usuário.
 * Chama AccessDetailsController::activatePermissions
 * Retorna o JSON do servidor: { success: true, updated: X } ou { success: false, message: '...' }
 */
async function activatePermissionBatch(userId, permissionsArray) {
    try {
        const res = await fetch(`${BASE_PATH}/admin/access-details/activatePermissions`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId: userId, permissions: permissionsArray })
        });
        // tenta parsear JSON (pode lançar se resposta não for JSON)
        const json = await res.json();
        return json;
    } catch (err) {
        console.error('Erro activatePermissionBatch', err);
        return { success: false, message: err.message || 'Erro de rede' };
    }
}

/**
 * Desativa (define = 1) as permissões informadas para o usuário.
 * Chama AccessDetailsController::deactivatePermissions
 * Retorna o JSON do servidor: { success: true, updated: X } ou { success: false, message: '...' }
 */
async function deactivatePermissionBatch(userId, permissions) {
    try {
        const response = await fetch(`${BASE_PATH}/admin/access-details/deactivatePermissions`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userId, permissions }),
        });

        // parse uniforme (tratamento robusto)
        const text = await response.text();
        try {
            const json = JSON.parse(text);
            return json;
        } catch (e) {
            console.error('Resposta inválida do servidor (não JSON):', text);
            return { success: false, message: 'Resposta inválida do servidor' };
        }
    } catch (error) {
        console.error('Erro deactivatePermissionBatch', error);
        return { success: false, message: error.message || 'Erro de rede' };
    }
}

/* --------------------------
   Modal de ativação (mantive sua implementação)
   -------------------------- */

function openActivatePermissionsModal(userId, userName) {
    const content = document.querySelector(`.details-content[data-content="permissoes"][data-user-id="${userId}"]`);
    if (!content) return;

    const inactivePerms = JSON.parse(content.dataset.inactivePermissions || '[]');
    if (inactivePerms.length === 0) return;

    document.getElementById('activatePermUserId').value = userId;
    document.getElementById('activatePermUserName').textContent = userName;

    const list = document.getElementById('inactivePermissionsList');
    list.innerHTML = '';

    inactivePerms.forEach(perm => {
        const item = document.createElement('div');
        item.className = 'permission-item';
        item.innerHTML = `
            <input type="checkbox" 
                   id="perm-${perm.campo}" 
                   value="${perm.campo}"
                   class="perm-checkbox">
            <label for="perm-${perm.campo}">${escapeHtml(perm.nome_exibicao || perm.campo)}</label>
        `;
        list.appendChild(item);
    });

    document.getElementById('modalActivatePermissions').style.display = 'flex';
}

function closeActivatePermissionsModal() {
    document.getElementById('modalActivatePermissions').style.display = 'none';
}

/* --------------------------
   Atualiza activateSelectedPermissions para usar o JSON retornado
   -------------------------- */

async function activateSelectedPermissions() {
    const userId = document.getElementById('activatePermUserId').value;
    const checkboxes = document.querySelectorAll('.perm-checkbox:checked');

    if (checkboxes.length === 0) {
        alert('Selecione ao menos uma permissão para ativar');
        return;
    }

    const permissions = Array.from(checkboxes).map(cb => cb.value);

    try {
        const res = await activatePermissionBatch(userId, permissions);
        if (res && res.success) {
            alert('Permissões ativadas com sucesso!');
            closeActivatePermissionsModal();
            loadCardDetails(userId); // recarrega para garantir consistência visual + dados
        } else {
            alert('Erro ao ativar permissões: ' + (res && res.message ? res.message : 'erro desconhecido'));
        }
    } catch (error) {
        console.error(error);
        alert('Erro ao ativar permissões');
    }
}

/* --------------------------
   Utilitários
   -------------------------- */

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

/* Escapa caracteres para uso em selectors (simples) */
function cssEscape(str) {
    return String(str).replace(/([ #.;?+*~>:[\](){}\\'\"`])/g, "\\$1");
}

/* Toggle inline do nome (primeiro <-> completo) */
function toggleName(btn) {
    var wrapper = btn.closest('.user-name-toggle');
    if (!wrapper) return;
    var isVisible = wrapper.classList.contains('full-visible');
    if (isVisible) {
        wrapper.classList.remove('full-visible');
    } else {
        wrapper.classList.add('full-visible');
    }
}

/* Opcional: permitir clique no first-name para alternar (delegação) */
document.addEventListener('click', function(e){
    var el = e.target;
    var first = el.closest && el.closest('.user-first-name');
    if (first) {
        // evita disparar expandir card (por propagation)
        e.stopPropagation();
        var wrapper = first.closest('.user-name-toggle');
        if (wrapper) wrapper.classList.toggle('full-visible');
    }
});