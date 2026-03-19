<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - KJ WosKaraoke</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f0f1a;
            --bg-secondary: #1a1a2e;
            --bg-card: #16162a;
            --text-primary: #ffffff;
            --text-secondary: #a0a0b0;
            --text-muted: #6b6b80;
            --primary-500: #22c55e;
            --accent: #8b5cf6;
            --warning: #f59e0b;
            --error: #ef4444;
            --border: #2a2a40;
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .header-nav {
            display: flex;
            gap: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: var(--bg-card);
            color: var(--text-primary);
        }

        .nav-link.active {
            background: var(--accent);
            color: white;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-logout {
            padding: 0.5rem 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
        }

        .btn-logout:hover {
            background: var(--error);
        }

        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.5rem;
        }

        .page-header .subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-success {
            background: var(--primary-500);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .event-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .event-card.active {
            border-color: var(--primary-500);
        }

        .event-status {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .event-name {
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .event-code {
            background: var(--bg-secondary);
            padding: 0.75rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .event-code strong {
            font-size: 1.5rem;
            letter-spacing: 3px;
            color: var(--accent);
        }

        .event-qr-area {
            margin-bottom: 1rem;
        }

        .qr-placeholder {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px dashed var(--border);
        }

        .qr-placeholder:hover {
            border-color: var(--accent);
            background: var(--bg-card);
        }

        .event-links {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .event-links .btn {
            flex: 1;
            justify-content: center;
        }

        .event-actions {
            display: flex;
            gap: 0.5rem;
        }

        .event-actions .btn {
            flex: 1;
            justify-content: center;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            background: var(--bg-card);
            border-radius: var(--radius);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-backdrop.active {
            display: flex;
        }

        .modal {
            background: var(--bg-card);
            border-radius: var(--radius);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 1.5rem;
            text-align: center;
        }

        .modal-footer {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-align: left;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 8px;
            z-index: 1001;
        }

        /* Responsive - Tablet */
        @media (max-width: 768px) {
            .header {
                flex-wrap: wrap;
                padding: 0.75rem 1rem;
                gap: 0.5rem;
            }

            .header-brand {
                font-size: 1rem;
            }

            .header-nav {
                order: 3;
                width: 100%;
                justify-content: center;
                padding-top: 0.5rem;
                border-top: 1px solid var(--border);
                margin-top: 0.5rem;
            }

            .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }

            #user-name {
                display: none;
            }

            .main {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .event-card {
                padding: 1rem;
            }

            .event-links {
                flex-direction: column;
                gap: 0.5rem;
            }

            .event-links .btn {
                width: 100%;
                justify-content: center;
            }

            .event-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .event-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .modal {
                width: 95%;
                max-height: 90vh;
                overflow-y: auto;
            }
        }

        /* Responsive - Mobile */
        @media (max-width: 480px) {
            .header-brand {
                font-size: 0.9rem;
            }

            .nav-link {
                padding: 0.5rem;
                font-size: 0.75rem;
                flex-direction: column;
                gap: 0.25rem;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .event-code strong {
                font-size: 1.25rem;
            }

            .btn,
            button {
                min-height: 44px;
                font-size: 0.875rem;
            }

            .qr-placeholder {
                padding: 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
</head>

<body>
    <header class="header">
        <div class="header-brand">🎤 WosKaraoke Admin</div>
        <nav class="header-nav">
            <a href="index.php" class="nav-link">📋 Fila</a>
            <a href="events.php" class="nav-link active">🎵 Eventos</a>
            <a href="battles.php" class="nav-link">⚔️ Batalhas</a>
        </nav>
        <div class="header-right">
            <span id="user-name">KJ</span>
            <button class="btn-logout" onclick="logout()">Sair</button>
        </div>
    </header>

    <main class="main">
        <div class="page-header">
            <div>
                <h1>🎵 Meus Eventos</h1>
                <p class="subtitle">Gerenciar sessões de karaoke</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Novo Evento</button>
        </div>

        <div class="events-grid" id="events-list">
            <div class="empty-state">
                <div class="empty-icon">🎵</div>
                <h3>Nenhum evento</h3>
                <p>Crie seu primeiro evento de karaoke!</p>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Criar Evento</button>
            </div>
        </div>
    </main>

    <!-- Modal Create/Edit -->
    <div class="modal-backdrop" id="modal-form">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">Novo Evento</h2>
                <button class="modal-close" onclick="closeModal('modal-form')">&times;</button>
            </div>
            <form id="form-event">
                <div class="modal-body" style="text-align: left;">
                    <input type="hidden" id="form-id">
                    <div class="form-group">
                        <label class="form-label">Nome do Evento *</label>
                        <input type="text" class="form-input" id="form-name" placeholder="Karaokê Sexta" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Código de Acesso</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" class="form-input" id="form-code" placeholder="ABC1" maxlength="6"
                                style="text-transform: uppercase;">
                            <button type="button" class="btn btn-secondary" onclick="generateCode()">Gerar</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="form-status">
                            <option value="1">🟢 Aberto (recebendo pedidos)</option>
                            <option value="0">🔴 Fechado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-form')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal-backdrop" id="modal-qr">
        <div class="modal">
            <div class="modal-header">
                <h2 id="qr-modal-title">📱 QR Code</h2>
                <button class="modal-close" onclick="closeModal('modal-qr')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="qr-code-container" style="display: flex; justify-content: center; margin: 1rem 0;"></div>
                <p id="qr-event-url" style="color: var(--text-muted); font-size: 0.8rem; word-break: break-all;"></p>
                <p style="margin-top: 1rem; color: var(--text-secondary);">
                    Escaneie para entrar no evento!
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="downloadQRCode()">⬇️ Baixar PNG</button>
                <button class="btn btn-secondary" onclick="printQRCode()">🖨️ Imprimir</button>
            </div>
        </div>
    </div>

    <!-- QRCode.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script>
        const API_BASE = '../api/admin/';
        let events = [];
        let currentQREventCode = '';
        let currentQREventName = '';

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadEvents();
        });

        async function checkAuth() {
            try {
                const response = await fetch(API_BASE + 'auth.php', { credentials: 'include' });
                const data = await response.json();
                if (!data.logged) {
                    window.location.href = 'login.php';
                    return false;
                }
                document.getElementById('user-name').textContent = data.logged.name;
                return true;
            } catch (e) {
                window.location.href = 'login.php';
                return false;
            }
        }

        async function loadEvents() {
            try {
                const response = await fetch(API_BASE + 'event.php?action=list', { credentials: 'include' });
                const data = await response.json();
                events = data.data || [];
                renderEvents();
            } catch (e) {
                console.error(e);
            }
        }

        function renderEvents() {
            const container = document.getElementById('events-list');

            if (events.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🎵</div>
                        <h3>Nenhum evento</h3>
                        <p>Crie seu primeiro evento de karaoke!</p>
                        <button class="btn btn-primary" onclick="openCreateModal()">+ Criar Evento</button>
                    </div>
                `;
                return;
            }

            container.innerHTML = events.map(e => `
                <div class="event-card ${e.is_open ? 'active' : ''}">
                    <div class="event-status">${e.is_open ? '🟢 Aberto' : '🔴 Fechado'}</div>
                    <h3 class="event-name">${escapeHtml(e.event_name)}</h3>
                    <div class="event-code">
                        <span>Código:</span>
                        <strong>${e.event_code}</strong>
                    </div>
                    <div class="event-qr-area">
                        <div class="qr-placeholder" onclick="showQRCode(${e.id}, '${e.event_code}', '${escapeHtml(e.event_name)}')">
                            📱 Clique para gerar QR Code
                        </div>
                    </div>
                    <div class="event-links">
                        <a href="../?code=${e.event_code}" target="_blank" class="btn btn-secondary">🔗 Link Público</a>
                        <button class="btn btn-secondary" onclick="showQRCode(${e.id}, '${e.event_code}', '${escapeHtml(e.event_name)}')">📱 QR Code</button>
                    </div>
                    <div class="event-actions">
                        <button class="btn ${e.is_open ? 'btn-danger' : 'btn-success'}" onclick="toggleEvent(${e.id}, ${e.is_open})">
                            ${e.is_open ? '🔴 Fechar' : '🟢 Abrir'}
                        </button>
                        <button class="btn btn-secondary" onclick="selectEvent(${e.id})">📋 Ver Fila</button>
                    </div>
                </div>
            `).join('');
        }

        function openCreateModal() {
            document.getElementById('modal-title').textContent = 'Novo Evento';
            document.getElementById('form-event').reset();
            document.getElementById('form-id').value = '';
            generateCode();
            openModal('modal-form');
        }

        function generateCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let code = '';
            for (let i = 0; i < 4; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('form-code').value = code;
        }

        document.getElementById('form-event').addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('form-id').value;
            const payload = {
                event_name: document.getElementById('form-name').value,
                event_code: document.getElementById('form-code').value.toUpperCase(),
                is_open: document.getElementById('form-status').value === '1'
            };

            try {
                const endpoint = id ? `event.php?action=update&id=${id}` : 'event.php?action=create';
                const response = await fetch(API_BASE + endpoint, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (data.success) {
                    showToast('Evento salvo!', 'success');
                    closeModal('modal-form');
                    loadEvents();
                } else {
                    showToast(data.error || 'Erro ao salvar', 'error');
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        async function toggleEvent(id, currentStatus) {
            try {
                await fetch(API_BASE + `event.php?action=toggle&id=${id}`, {
                    method: 'POST',
                    credentials: 'include'
                });
                showToast(currentStatus ? 'Evento fechado' : 'Evento aberto!', 'success');
                loadEvents();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function selectEvent(id) {
            sessionStorage.setItem('currentEventId', id);
            window.location.href = 'index.php';
        }

        function showQRCode(eventId, code, name) {
            currentQREventCode = code;
            currentQREventName = name;

            const baseUrl = window.location.origin + '/WosKaraoke';
            const eventUrl = `${baseUrl}/?code=${code}`;

            document.getElementById('qr-modal-title').textContent = `📱 ${name}`;
            document.getElementById('qr-event-url').textContent = eventUrl;

            const container = document.getElementById('qr-code-container');
            container.innerHTML = '';

            const canvas = document.createElement('canvas');
            canvas.id = 'qr-canvas';
            container.appendChild(canvas);

            QRCode.toCanvas(canvas, eventUrl, {
                width: 250,
                margin: 2,
                color: { dark: '#000000', light: '#ffffff' }
            });

            openModal('modal-qr');
        }

        function downloadQRCode() {
            const canvas = document.getElementById('qr-canvas');
            if (!canvas) return;

            const link = document.createElement('a');
            link.download = `qrcode-${currentQREventCode}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();

            showToast('QR Code baixado!', 'success');
        }

        function printQRCode() {
            const canvas = document.getElementById('qr-canvas');
            if (!canvas) return;

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>QR Code - ${currentQREventName}</title>
                    <style>
                        body { 
                            display: flex; 
                            flex-direction: column;
                            align-items: center; 
                            justify-content: center; 
                            min-height: 100vh; 
                            font-family: Arial, sans-serif;
                            text-align: center;
                        }
                        h1 { margin-bottom: 1rem; }
                        .code { font-size: 2rem; font-weight: bold; letter-spacing: 4px; margin: 1rem 0; }
                        p { color: #666; }
                    </style>
                </head>
                <body>
                    <h1>🎤 ${currentQREventName}</h1>
                    <img src="${canvas.toDataURL('image/png')}" alt="QR Code">
                    <div class="code">${currentQREventCode}</div>
                    <p>Escaneie para entrar no karaokê!</p>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }


        async function logout() {
            try {
                await fetch(API_BASE + 'auth.php?action=logout', {
                    method: 'POST',
                    credentials: 'include'
                });
            } catch (e) { }
            window.location.href = 'login.php';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function showToast(message, type = 'success') {
            const existing = document.querySelector('.toast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.background = type === 'success' ? '#22c55e' : '#ef4444';
            toast.style.color = 'white';
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>

</html>
