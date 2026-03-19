<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Estabelecimento Karaoke Show</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
    <style>
        :root {
            --bg-primary: #0f0f1a;
            --bg-secondary: #1a1a2e;
            --bg-card: #16162a;
            --text-primary: #ffffff;
            --text-secondary: #a0a0b0;
            --text-muted: #6b6b80;
            --primary-500: #f59e0b;
            --accent: #8b5cf6;
            --success: #22c55e;
            --error: #ef4444;
            --warning: #eab308;
            --border: #2a2a40;
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; }

        .header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border); }
        .header-brand { display: flex; align-items: center; gap: 0.75rem; font-size: 1.25rem; font-weight: 700; }
        .header-nav { display: flex; gap: 0.5rem; }
        .nav-link { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; color: var(--text-secondary); text-decoration: none; border-radius: 8px; font-size: 0.875rem; transition: all 0.2s; }
        .nav-link:hover { background: var(--bg-card); color: var(--text-primary); }
        .nav-link.active { background: var(--primary-500); color: white; }
        .btn-logout { padding: 0.5rem 1rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); cursor: pointer; }
        .btn-logout:hover { background: var(--error); }

        .main { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.5rem; }
        .page-subtitle { color: var(--text-muted); font-size: 0.875rem; }

        .btn { padding: 0.75rem 1.5rem; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background: var(--bg-secondary); border: 1px solid var(--border); }
        .btn-danger { background: var(--error); }

        .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
        .event-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; transition: transform 0.2s; }
        .event-card:hover { transform: translateY(-2px); }
        .event-card.open { border-color: var(--success); }
        .event-card.paused { border-color: var(--warning); }
        .event-card.closed { border-color: var(--error); }

        .event-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .event-name { font-size: 1.125rem; font-weight: 600; }
        .event-code { font-family: monospace; font-size: 1.5rem; font-weight: bold; color: var(--accent); letter-spacing: 0.1em; }

        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .status-open { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .status-paused { background: rgba(234, 179, 8, 0.2); color: #eab308; }
        .status-closed { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        .event-stats { display: flex; gap: 1.5rem; margin: 1rem 0; font-size: 0.875rem; color: var(--text-muted); }
        .event-stats span { display: flex; align-items: center; gap: 0.25rem; }
        .event-kj { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem; }

        .event-actions { display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
        .event-actions button { padding: 0.5rem 1rem; font-size: 0.875rem; }

        .empty-state { text-align: center; padding: 4rem 2rem; background: var(--bg-card); border-radius: var(--radius); }
        .empty-icon { font-size: 4rem; margin-bottom: 1rem; }
        .empty-state h3 { margin-bottom: 0.5rem; }
        .empty-state p { color: var(--text-muted); margin-bottom: 1.5rem; }

        .filter-bar { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-btn { padding: 0.5rem 1rem; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .filter-btn.active { background: var(--accent); color: white; border-color: var(--accent); }

        .modal-backdrop { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal-backdrop.active { display: flex; }
        .modal { background: var(--bg-secondary); border-radius: var(--radius); width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-title { font-size: 1.25rem; }
        .modal-close { background: none; border: none; color: var(--text-secondary); font-size: 1.5rem; cursor: pointer; }
        .modal form { padding: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-secondary); }
        .form-input, .form-select { width: 100%; padding: 0.75rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 1rem; }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--accent); }
        .modal-footer { display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border); margin-top: 1rem; }

        @media (max-width: 768px) {
            .header { flex-wrap: wrap; gap: 1rem; }
            .header-nav { order: 3; width: 100%; justify-content: center; flex-wrap: wrap; }
            .page-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-brand">🏢 <span id="establishment-name">Estabelecimento</span></div>
        <nav class="header-nav">
            <a href="index.php" class="nav-link">📊 Dashboard</a>
            <a href="kjs.php" class="nav-link">👤 KJs</a>
            <a href="events.php" class="nav-link active">🎵 Eventos</a>
            <a href="billing.php" class="nav-link">💰 Planos</a>
            <a href="settings.php" class="nav-link">⚙️ Config</a>
        </nav>
        <div class="header-right">
            <button class="btn-logout" onclick="logout()">Sair</button>
        </div>
    </header>

    <main class="main">
        <div class="page-header">
            <div>
                <h1 class="page-title">🎵 Eventos</h1>
                <p class="page-subtitle">Gerencie os eventos de karaoke do seu estabelecimento</p>
            </div>
            <button class="btn" onclick="openCreateModal()">+ Novo Evento</button>
        </div>

        <!-- Filtros -->
        <div class="filter-bar">
            <button class="filter-btn active" onclick="setFilter('all', this)">Todos</button>
            <button class="filter-btn" onclick="setFilter('open', this)">🟢 Abertos</button>
            <button class="filter-btn" onclick="setFilter('paused', this)">🟡 Pausados</button>
            <button class="filter-btn" onclick="setFilter('closed', this)">🔴 Fechados</button>
        </div>

        <div id="events-container" class="events-grid">
            <div class="empty-state">
                <div class="empty-icon">🎵</div>
                <h3>Carregando eventos...</h3>
            </div>
        </div>
    </main>

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
                <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 1rem;">Clientes podem escanear para entrar na fila</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        const API_BASE = '../api/establishment/';
        let events = [];
        let kjs = [];
        let currentFilter = 'all';

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            await loadKJs();
            loadEvents();
        });

        async function checkAuth() {
            try {
                const response = await fetch(API_BASE + 'auth.php', { credentials: 'include' });
                const data = await response.json();
                if (!data.logged) {
                    window.location.href = 'login.php';
                    return;
                }
                document.getElementById('establishment-name').textContent = data.logged.name;
            } catch (e) {
                window.location.href = 'login.php';
            }
        }

        async function loadKJs() {
            try {
                const response = await fetch(API_BASE + 'events.php?kjs=1', { credentials: 'include' });
                const data = await response.json();
                kjs = data.data || [];

                const select = document.getElementById('form-kj');
                select.innerHTML = '<option value="">-- Sem KJ atribuído --</option>' +
                    kjs.map(k => `<option value="${k.id}">${escapeHtml(k.name)}</option>`).join('');
            } catch (e) {
                console.error(e);
            }
        }

        async function loadEvents() {
            try {
                const response = await fetch(API_BASE + 'events.php', { credentials: 'include' });
                const data = await response.json();
                events = data.data || [];
                renderEvents();
            } catch (e) {
                console.error(e);
                showError('Erro ao carregar eventos');
            }
        }

        function setFilter(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderEvents();
        }

        function getStatusBadge(status) {
            const labels = {
                'open': '<span class="status-badge status-open">🟢 Aberto</span>',
                'paused': '<span class="status-badge status-paused">🟡 Pausado</span>',
                'closed': '<span class="status-badge status-closed">🔴 Fechado</span>'
            };
            return labels[status] || labels['closed'];
        }

        function renderEvents() {
            const container = document.getElementById('events-container');
            let filtered = events;

            if (currentFilter !== 'all') {
                filtered = events.filter(e => (e.status || 'closed') === currentFilter);
            }

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <div class="empty-icon">🎵</div>
                        <h3>Nenhum evento ${currentFilter !== 'all' ? 'com este status' : 'cadastrado'}</h3>
                        <p>Crie seu primeiro evento para começar</p>
                        <button class="btn" onclick="openCreateModal()">+ Novo Evento</button>
                    </div>
                `;
                return;
            }

            container.innerHTML = filtered.map(e => `
                <div class="event-card ${e.status || 'closed'}">
                    <div class="event-header">
                        <div>
                            <div class="event-name">${escapeHtml(e.event_name)}</div>
                            <div class="event-code">${e.event_code}</div>
                        </div>
                        ${getStatusBadge(e.status)}
                    </div>
                    <div class="event-kj">🎤 KJ: ${escapeHtml(e.kj_name || 'Não atribuído')}</div>
                    <div class="event-stats">
                        <span>🎵 ${e.total_songs || 0} músicas</span>
                        <span>👥 ${e.unique_singers || 0} cantores</span>
                    </div>
                    <div class="event-actions">
                        <button class="btn ${e.status === 'open' ? 'btn-danger' : ''}" onclick="toggleStatus(${e.id})">
                            ${e.status === 'open' ? '🔴 Fechar' : '🟢 Abrir'}
                        </button>
                        <button class="btn btn-secondary" onclick="showQRCode(${e.id})">📱 QR</button>
                        <button class="btn btn-secondary" onclick="editEvent(${e.id})">✏️</button>
                        <button class="btn btn-danger" onclick="deleteEvent(${e.id})">🗑️</button>
                    </div>
                </div>
            `).join('');
        }

        function openCreateModal() {
            document.getElementById('modal-title').textContent = 'Novo Evento';
            document.getElementById('form-event').reset();
            document.getElementById('form-id').value = '';
            openModal('modal-form');
        }

        async function editEvent(id) {
            try {
                const response = await fetch(`${API_BASE}events.php?id=${id}`, { credentials: 'include' });
                const data = await response.json();
                const e = data.data;

                document.getElementById('modal-title').textContent = 'Editar Evento';
                document.getElementById('form-id').value = e.id;
                document.getElementById('form-name').value = e.event_name;
                document.getElementById('form-code').value = e.event_code;
                document.getElementById('form-status').value = e.status || 'closed';
                document.getElementById('form-kj').value = e.admin_id || '';
                document.getElementById('form-starts').value = e.starts_at ? e.starts_at.slice(0, 16) : '';
                document.getElementById('form-ends').value = e.ends_at ? e.ends_at.slice(0, 16) : '';
                document.getElementById('form-max-songs').value = e.max_songs_per_person || 3;

                openModal('modal-form');
            } catch (e) {
                showError('Erro ao carregar evento');
            }
        }

        document.getElementById('form-event').addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('form-id').value;
            const payload = {
                event_name: document.getElementById('form-name').value,
                event_code: document.getElementById('form-code').value,
                status: document.getElementById('form-status').value,
                admin_id: document.getElementById('form-kj').value || null,
                starts_at: document.getElementById('form-starts').value || null,
                ends_at: document.getElementById('form-ends').value || null,
                max_songs_per_person: parseInt(document.getElementById('form-max-songs').value)
            };

            try {
                const url = id ? `${API_BASE}events.php?id=${id}` : `${API_BASE}events.php`;
                const method = id ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method,
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (result.success) {
                    toastSuccess(id ? 'Evento atualizado!' : 'Evento criado!');
                    closeModal('modal-form');
                    loadEvents();
                } else {
                    showError(result.error || 'Erro ao salvar evento');
                }
            } catch (err) {
                showError('Erro ao salvar evento');
            }
        });

        async function toggleStatus(id) {
            try {
                const response = await fetch(`${API_BASE}events.php?action=toggle&id=${id}`, {
                    method: 'POST',
                    credentials: 'include'
                });
                const result = await response.json();
                toastSuccess(result.message);
                loadEvents();
            } catch (err) {
                showError('Erro ao alternar status');
            }
        }

        async function deleteEvent(id) {
            const confirmed = await showDeleteConfirm('este evento');
            if (!confirmed) return;

            try {
                const response = await fetch(`${API_BASE}events.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                const result = await response.json();
                toastSuccess(result.message);
                loadEvents();
            } catch (err) {
                showError('Erro ao excluir evento');
            }
        }

        function showQRCode(id) {
            const event = events.find(e => e.id === id);
            if (!event) return;

            const qrContainer = document.getElementById('qr-code-container');
            qrContainer.innerHTML = '';

            const qr = qrcode(0, 'M');
            const url = `${window.location.origin}/WosKaraoke/?code=${event.event_code}`;
            qr.addData(url);
            qr.make();
            qrContainer.innerHTML = qr.createImgTag(6);

            document.getElementById('qr-event-name').textContent = event.event_name;
            document.getElementById('qr-event-code').textContent = event.event_code;

            openModal('modal-qr');
        }

        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        async function logout() {
            try { await fetch(API_BASE + 'auth.php?action=logout', { method: 'POST', credentials: 'include' }); } catch (e) {}
            window.location.href = 'login.php';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>