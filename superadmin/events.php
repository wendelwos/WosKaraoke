<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Super Admin Karaoke Show</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=2.1">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
    <style>
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .status-open { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .status-paused { background: rgba(234, 179, 8, 0.2); color: #eab308; }
        .status-closed { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .event-code { font-family: monospace; font-size: 1.1em; font-weight: bold; color: #8b5cf6; }
        .filter-bar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.5rem 1rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); border-radius: 8px; cursor: pointer; }
        .filter-btn.active { background: var(--primary-500); color: white; border-color: var(--primary-500); }
        .stats-row { display: flex; gap: 0.5rem; font-size: 0.875rem; color: var(--text-muted); }
        .stats-row span { display: flex; align-items: center; gap: 0.25rem; }
    </style>
</head>

<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-icon">🎛️</span>
                <span class="logo-text">Karaoke Show</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-link"><span class="nav-icon">📊</span> Dashboard</a>
                <a href="establishments.php" class="nav-link"><span class="nav-icon">🏢</span> Estabelecimentos</a>
                <a href="events.php" class="nav-link active"><span class="nav-icon">🎵</span> Eventos</a>
                <a href="kjs.php" class="nav-link"><span class="nav-icon">🎤</span> KJs</a>
                <a href="settings.php" class="nav-link"><span class="nav-icon">⚙️</span> Configurações</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info"><span class="user-avatar">👤</span><span id="user-name">Admin</span></div>
                <button class="btn-logout" onclick="logout()">Sair</button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🎵 Eventos</h1>
                    <p class="subtitle">Gerenciar eventos de karaoke de todos os estabelecimentos</p>
                </div>
                <button class="btn" onclick="openCreateModal()">+ Novo Evento</button>
            </header>

            <!-- Filtros -->
            <div class="filter-bar">
                <button class="filter-btn active" onclick="setFilter('all')">Todos</button>
                <button class="filter-btn" onclick="setFilter('open')">🟢 Abertos</button>
                <button class="filter-btn" onclick="setFilter('paused')">🟡 Pausados</button>
                <button class="filter-btn" onclick="setFilter('closed')">🔴 Fechados</button>
                <select id="filter-establishment" class="form-select" style="min-width: 200px;" onchange="loadEvents()">
                    <option value="">Todos os Estabelecimentos</option>
                </select>
            </div>

            <div class="content-card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Código</th>
                                <th class="hide-mobile">Estabelecimento</th>
                                <th class="hide-mobile">KJ</th>
                                <th>Status</th>
                                <th class="hide-mobile">Estatísticas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <tr>
                                <td colspan="7" class="loading">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Create/Edit -->
    <div class="modal-backdrop" id="modal-form">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-title">Novo Evento</h2>
                <button class="modal-close" onclick="closeModal('modal-form')">&times;</button>
            </div>
            <form id="form-event">
                <input type="hidden" id="form-id">
                <div class="form-group">
                    <label class="form-label">Nome do Evento *</label>
                    <input type="text" class="form-input" id="form-name" required placeholder="Ex: Karaokê Sexta-Feira">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Código de Acesso</label>
                        <input type="text" class="form-input" id="form-code" maxlength="6" placeholder="Auto-gerado" style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="form-status">
                            <option value="closed">🔴 Fechado</option>
                            <option value="open">🟢 Aberto</option>
                            <option value="paused">🟡 Pausado</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Estabelecimento *</label>
                    <select class="form-select" id="form-establishment" required onchange="loadKJsForEstablishment()">
                        <option value="">-- Selecione --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">KJ Responsável</label>
                    <select class="form-select" id="form-kj">
                        <option value="">-- Sem KJ atribuído --</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Início</label>
                        <input type="datetime-local" class="form-input" id="form-starts">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Término</label>
                        <input type="datetime-local" class="form-input" id="form-ends">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Máx. músicas por pessoa</label>
                    <input type="number" class="form-input" id="form-max-songs" value="3" min="1" max="10">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-form')">Cancelar</button>
                    <button type="submit" class="btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal QR Code -->
    <div class="modal-backdrop" id="modal-qr">
        <div class="modal" style="max-width: 400px; text-align: center;">
            <div class="modal-header">
                <h2 class="modal-title">QR Code do Evento</h2>
                <button class="modal-close" onclick="closeModal('modal-qr')">&times;</button>
            </div>
            <div style="padding: 2rem;">
                <div id="qr-code-container" style="margin-bottom: 1rem;"></div>
                <p id="qr-event-name" style="font-weight: bold; margin-bottom: 0.5rem;"></p>
                <p id="qr-event-code" class="event-code" style="font-size: 2rem;"></p>
            </div>
        </div>
    </div>

    <script src="mobile-menu.js"></script>
    <script src="app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        let events = [];
        let establishments = [];
        let allKJs = [];
        let currentFilter = 'all';

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            await loadEstablishments();
            loadEvents();
        });

        async function loadEstablishments() {
            try {
                const data = await fetchAPI('establishments.php');
                establishments = data.data;
                
                // Preencher selects
                const filterSelect = document.getElementById('filter-establishment');
                const formSelect = document.getElementById('form-establishment');
                
                const options = establishments.map(e => `<option value="${e.id}">${escapeHtml(e.name)}</option>`).join('');
                filterSelect.innerHTML = '<option value="">Todos os Estabelecimentos</option>' + options;
                formSelect.innerHTML = '<option value="">-- Selecione --</option>' + options;
            } catch (e) {
                console.error(e);
            }
        }

        async function loadEvents() {
            try {
                let url = 'events.php';
                const params = [];
                
                const estId = document.getElementById('filter-establishment').value;
                if (estId) params.push(`establishment_id=${estId}`);
                if (currentFilter !== 'all') params.push(`status=${currentFilter}`);
                
                if (params.length) url += '?' + params.join('&');
                
                const data = await fetchAPI(url);
                events = data.data;
                renderTable();
            } catch (e) {
                showError(e.message);
            }
        }

        function setFilter(filter) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            loadEvents();
        }

        function getStatusBadge(status) {
            const labels = {
                'open': '<span class="status-badge status-open">🟢 Aberto</span>',
                'paused': '<span class="status-badge status-paused">🟡 Pausado</span>',
                'closed': '<span class="status-badge status-closed">🔴 Fechado</span>'
            };
            return labels[status] || status;
        }

        function renderTable() {
            const tbody = document.getElementById('table-body');

            if (events.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty">Nenhum evento encontrado</td></tr>';
                return;
            }

            tbody.innerHTML = events.map(e => `
                <tr>
                    <td><strong>${escapeHtml(e.event_name)}</strong></td>
                    <td><span class="event-code">${e.event_code}</span></td>
                    <td class="hide-mobile">${escapeHtml(e.establishment_name || '-')}</td>
                    <td class="hide-mobile">${escapeHtml(e.kj_name || 'Não atribuído')}</td>
                    <td>${getStatusBadge(e.status || 'closed')}</td>
                    <td class="hide-mobile">
                        <div class="stats-row">
                            <span>🎵 ${e.total_songs || 0}</span>
                            <span>👥 ${e.unique_singers || 0}</span>
                        </div>
                    </td>
                    <td style="white-space: nowrap;">
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="toggleStatus(${e.id})" title="Alternar Status">
                            ${e.status === 'open' ? '🔴' : '🟢'}
                        </button>
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="showQRCode(${e.id})" title="QR Code">📱</button>
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="editEvent(${e.id})" title="Editar">✏️</button>
                        <button class="btn btn-danger" style="padding: 0.5rem;" onclick="deleteEvent(${e.id})" title="Excluir">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }

        async function loadKJsForEstablishment() {
            const estId = document.getElementById('form-establishment').value;
            const kjSelect = document.getElementById('form-kj');
            
            if (!estId) {
                kjSelect.innerHTML = '<option value="">-- Sem KJ atribuído --</option>';
                return;
            }
            
            try {
                const data = await fetchAPI('kjs.php');
                const kjs = data.data.filter(k => k.establishment_id == estId);
                kjSelect.innerHTML = '<option value="">-- Sem KJ atribuído --</option>' + 
                    kjs.map(k => `<option value="${k.id}">${escapeHtml(k.name)}</option>`).join('');
            } catch (e) {
                console.error(e);
            }
        }

        function openCreateModal() {
            document.getElementById('modal-title').textContent = 'Novo Evento';
            document.getElementById('form-event').reset();
            document.getElementById('form-id').value = '';
            document.getElementById('form-kj').innerHTML = '<option value="">-- Sem KJ atribuído --</option>';
            openModal('modal-form');
        }

        async function editEvent(id) {
            try {
                const data = await fetchAPI(`events.php?id=${id}`);
                const e = data.data;

                document.getElementById('modal-title').textContent = 'Editar Evento';
                document.getElementById('form-id').value = e.id;
                document.getElementById('form-name').value = e.event_name;
                document.getElementById('form-code').value = e.event_code;
                document.getElementById('form-status').value = e.status || 'closed';
                document.getElementById('form-establishment').value = e.establishment_id;
                document.getElementById('form-starts').value = e.starts_at ? e.starts_at.slice(0, 16) : '';
                document.getElementById('form-ends').value = e.ends_at ? e.ends_at.slice(0, 16) : '';
                document.getElementById('form-max-songs').value = e.max_songs_per_person || 3;

                await loadKJsForEstablishment();
                document.getElementById('form-kj').value = e.admin_id || '';

                openModal('modal-form');
            } catch (e) {
                showError(e.message);
            }
        }

        document.getElementById('form-event').addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('form-id').value;
            const payload = {
                event_name: document.getElementById('form-name').value,
                event_code: document.getElementById('form-code').value,
                status: document.getElementById('form-status').value,
                establishment_id: parseInt(document.getElementById('form-establishment').value),
                admin_id: document.getElementById('form-kj').value || null,
                starts_at: document.getElementById('form-starts').value || null,
                ends_at: document.getElementById('form-ends').value || null,
                max_songs_per_person: parseInt(document.getElementById('form-max-songs').value)
            };

            try {
                if (id) {
                    await fetchAPI(`events.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) });
                    toastSuccess('Evento atualizado!');
                } else {
                    await fetchAPI('events.php', { method: 'POST', body: JSON.stringify(payload) });
                    toastSuccess('Evento criado!');
                }
                closeModal('modal-form');
                loadEvents();
            } catch (err) {
                showError(err.message);
            }
        });

        async function toggleStatus(id) {
            try {
                const result = await fetchAPI(`events.php?action=toggle&id=${id}`, { method: 'POST' });
                toastSuccess(result.message);
                loadEvents();
            } catch (err) {
                showError(err.message);
            }
        }

        async function deleteEvent(id) {
            const confirmed = await showDeleteConfirm('este evento');
            if (!confirmed) return;

            try {
                await fetchAPI(`events.php?id=${id}`, { method: 'DELETE' });
                toastSuccess('Evento excluído!');
                loadEvents();
            } catch (err) {
                showError(err.message);
            }
        }

        function showQRCode(id) {
            const event = events.find(e => e.id === id);
            if (!event) return;

            const qrContainer = document.getElementById('qr-code-container');
            qrContainer.innerHTML = '';

            // Gerar QR Code
            const qr = qrcode(0, 'M');
            const url = `${window.location.origin}/WosKaraoke/?code=${event.event_code}`;
            qr.addData(url);
            qr.make();
            qrContainer.innerHTML = qr.createImgTag(6);

            document.getElementById('qr-event-name').textContent = event.event_name;
            document.getElementById('qr-event-code').textContent = event.event_code;

            openModal('modal-qr');
        }
    </script>
</body>

</html>
