<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KJ Dashboard - WosKaraoke</title>
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
            --primary-600: #16a34a;
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

        /* Header */
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

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-500);
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
            font-size: 0.875rem;
        }

        .btn-logout:hover {
            background: var(--error);
        }

        /* Main Content */
        .main {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Event Code Section */
        .event-section {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .event-code-card {
            background: var(--bg-card);
            border: 2px solid var(--accent);
            border-radius: var(--radius);
            padding: 1.5rem 2rem;
            text-align: center;
        }

        .event-code-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .event-code-digits {
            display: flex;
            gap: 0.5rem;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .event-code-digit {
            background: var(--bg-secondary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .btn-new-code {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
            font-size: 0.875rem;
        }

        .btn-new-code:hover {
            background: var(--accent);
        }

        /* Queue Status */
        .queue-status {
            flex: 1;
            min-width: 250px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .status-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background: var(--primary-500);
            border: none;
            border-radius: var(--radius);
            color: white;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 0.5rem;
        }

        .status-btn.closed {
            background: var(--error);
        }

        .status-hint {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        /* Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        /* Current Song */
        .current-song {
            background: var(--bg-card);
            border: 2px solid var(--primary-500);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .current-song-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-500);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }

        .current-song-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .song-code {
            background: var(--primary-500);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .song-info h3 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .song-info p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .song-singer {
            color: var(--primary-500);
            font-weight: 500;
        }

        .current-song-actions {
            display: flex;
            gap: 0.75rem;
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
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-500);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-600);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-play {
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--primary-500);
            color: white;
            border: none;
            cursor: pointer;
        }

        .btn-remove {
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: var(--text-muted);
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
        }

        .btn-remove:hover {
            color: var(--error);
        }

        /* Queue List */
        .queue-section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .queue-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .queue-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .queue-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
        }

        .drag-handle {
            color: var(--text-muted);
            cursor: grab;
            font-size: 1.25rem;
        }

        .reorder-btns {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .reorder-btn {
            padding: 2px 6px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 4px;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.75rem;
        }

        .reorder-btn:hover {
            background: var(--accent);
            color: white;
        }

        .queue-position {
            background: var(--accent);
            color: white;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-weight: 700;
        }

        .queue-song-info {
            flex: 1;
        }

        .queue-song-info h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .queue-song-info p {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .queue-meta {
            text-align: right;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .queue-meta .singer {
            color: var(--primary-500);
            font-weight: 500;
        }

        .queue-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 8px;
            z-index: 1000;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateX(-50%) translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
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

            .header-right {
                gap: 0.5rem;
            }

            #user-name {
                display: none;
            }

            .main {
                padding: 1rem;
            }

            .event-section {
                flex-direction: column;
                gap: 1rem;
            }

            .event-code-card {
                padding: 1rem;
            }

            .event-code-digits {
                font-size: 2.5rem;
            }

            .queue-toggle {
                width: 100%;
            }

            .stats-row {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-card .value {
                font-size: 1.5rem;
            }

            .now-playing {
                flex-direction: column;
                text-align: center;
            }

            .now-playing-info {
                width: 100%;
            }

            .now-playing-actions {
                width: 100%;
                justify-content: center;
            }

            .queue-item {
                flex-wrap: wrap;
                gap: 0.5rem;
                padding: 0.75rem;
            }

            .queue-actions {
                width: 100%;
                justify-content: flex-end;
                padding-top: 0.5rem;
                border-top: 1px solid var(--border);
                margin-top: 0.5rem;
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

            .event-code-digits {
                font-size: 2rem;
            }

            .event-code-digit {
                padding: 0.5rem;
                min-width: 2.5rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .stat-card {
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: left;
            }

            .stat-card .value {
                order: 2;
            }

            .stat-card .label {
                order: 1;
            }

            .btn,
            button {
                min-height: 44px;
                /* Touch-friendly target */
            }

            .queue-song-info h4 {
                font-size: 0.85rem;
            }

            .queue-song-info p {
                font-size: 0.75rem;
            }

            .btn-action {
                width: 40px;
                height: 40px;
            }
        }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-brand">
            🎤 WosKaraoke Admin
            <span class="status-dot" id="status-dot"></span>
        </div>
        <nav class="header-nav">
            <a href="index.php" class="nav-link active">📋 Fila</a>
            <a href="events.php" class="nav-link">🎵 Eventos</a>
            <a href="battles.php" class="nav-link">⚔️ Batalhas</a>
        </nav>
        <div class="header-right">
            <span id="user-name">KJ</span>
            <button class="btn-logout" onclick="logout()">Sair</button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <!-- Event Code & Status -->
        <section class="event-section">
            <div class="event-code-card">
                <div class="event-code-label">Código do Evento</div>
                <div class="event-code-digits" id="event-code">
                    <span class="event-code-digit">-</span>
                    <span class="event-code-digit">-</span>
                    <span class="event-code-digit">-</span>
                    <span class="event-code-digit">-</span>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                    <button class="btn-new-code" onclick="generateNewCode()">
                        🔁 Novo Código
                    </button>
                    <button class="btn-new-code" onclick="openTVMode()" style="background: var(--accent);">
                        📺 Modo TV
                    </button>
                </div>
            </div>

            <div class="queue-status">
                <button class="status-btn" id="status-btn" onclick="toggleQueueStatus()">
                    <span class="status-dot"></span>
                    <span id="status-text">FILA ABERTA</span>
                </button>
                <p class="status-hint" id="status-hint">Clientes podem adicionar músicas</p>
            </div>
        </section>

        <!-- Stats -->
        <section class="stats-row">
            <div class="stat-card">
                <div class="stat-value" id="stat-queue">0</div>
                <div class="stat-label">Na fila</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-people">0</div>
                <div class="stat-label">Pessoas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-today">0</div>
                <div class="stat-label">Cantadas hoje</div>
            </div>
        </section>

        <!-- Current Song -->
        <section class="current-song" id="current-song-section" style="display: none;">
            <div class="current-song-header">
                🎤 CANTANDO AGORA
            </div>
            <div class="current-song-content">
                <div class="song-code" id="current-code">-</div>
                <div class="song-info">
                    <h3 id="current-title">-</h3>
                    <p id="current-artist">-</p>
                    <p>👤 <span class="song-singer" id="current-singer">-</span></p>
                </div>
            </div>
            <div class="current-song-actions">
                <button class="btn btn-primary" onclick="nextSong()">
                    ▶ Próxima
                </button>
                <button class="btn btn-warning" onclick="skipSong()">
                    ⏭ Pular
                </button>
            </div>
        </section>

        <!-- Queue List -->
        <section class="queue-section">
            <div class="queue-header">
                <div class="queue-title">
                    📋 Próximos (<span id="queue-count">0</span>)
                </div>
                <button class="btn btn-danger" onclick="clearQueue()">Limpar</button>
            </div>
            <div class="queue-list" id="queue-list">
                <div class="empty-state">
                    <div class="empty-state-icon">🎵</div>
                    <p>Nenhuma música na fila</p>
                </div>
            </div>
        </section>
    </main>

    <script>
        const API_BASE = '../api/admin/';
        let eventData = null;
        let queueData = [];
        let refreshInterval = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            const isAuth = await checkAuth();
            if (!isAuth) return;

            await loadEventData();
            await loadQueue();

            // Auto-refresh every 2 seconds for real-time updates
            refreshInterval = setInterval(loadQueue, 2000);
        });

        // Check authentication
        async function checkAuth() {
            try {
                const response = await fetch(API_BASE + 'auth.php', { credentials: 'include' });
                const data = await response.json();

                if (!data.logged) {
                    window.location.href = 'login.php';
                    return false;
                }

                document.getElementById('user-name').textContent = data.logged.name;
                eventData = data.event;

                if (eventData) {
                    updateEventDisplay();
                }

                return true;
            } catch (e) {
                window.location.href = 'login.php';
                return false;
            }
        }

        // Load event data
        async function loadEventData() {
            try {
                const response = await fetch(API_BASE + 'event.php?action=current', { credentials: 'include' });
                const data = await response.json();

                if (data.data) {
                    eventData = data.data;
                    updateEventDisplay();
                }
            } catch (e) {
                console.error('Error loading event:', e);
            }
        }

        // Update event code display
        function updateEventDisplay() {
            if (!eventData) return;

            const code = eventData.event_code || '----';
            const codeContainer = document.getElementById('event-code');
            codeContainer.innerHTML = code.split('').map(d =>
                `<span class="event-code-digit">${d}</span>`
            ).join('');

            const isOpen = eventData.is_open;
            const statusBtn = document.getElementById('status-btn');
            const statusText = document.getElementById('status-text');
            const statusHint = document.getElementById('status-hint');
            const statusDot = document.getElementById('status-dot');

            if (isOpen) {
                statusBtn.classList.remove('closed');
                statusText.textContent = 'FILA ABERTA';
                statusHint.textContent = 'Clientes podem adicionar músicas';
                statusDot.style.background = '#22c55e';
            } else {
                statusBtn.classList.add('closed');
                statusText.textContent = 'FILA FECHADA';
                statusHint.textContent = 'Fila bloqueada para novos pedidos';
                statusDot.style.background = '#ef4444';
            }
        }

        // Load queue - uses event_id from eventData for proper filtering
        async function loadQueue() {
            try {
                // Get event_id from current event or default to 1
                const eventId = eventData?.id || sessionStorage.getItem('currentEventId') || 1;
                const response = await fetch(API_BASE + `queue.php?event_id=${eventId}`, { credentials: 'include' });
                const result = await response.json();

                // API returns data.data.current and data.data.waiting
                const current = result.data?.current || null;
                const waiting = result.data?.waiting || [];
                const stats = result.data?.stats || {};

                // Build queueData: current song first (if exists), then waiting list
                queueData = [];
                if (current) {
                    queueData.push(current);
                }
                queueData = queueData.concat(waiting);

                // Update stats - use correct field names from API
                document.getElementById('stat-queue').textContent = queueData.length;
                document.getElementById('stat-people').textContent = stats.unique_people || 0;
                document.getElementById('stat-today').textContent = stats.done_today || 0;
                document.getElementById('queue-count').textContent = waiting.length;

                renderQueue();
            } catch (e) {
                console.error('Error loading queue:', e);
            }
        }

        // Render queue
        function renderQueue() {
            const listEl = document.getElementById('queue-list');
            const currentSection = document.getElementById('current-song-section');

            if (queueData.length === 0) {
                currentSection.style.display = 'none';
                listEl.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🎵</div>
                        <p>Nenhuma música na fila</p>
                    </div>
                `;
                return;
            }

            // Current song (first in queue)
            const current = queueData[0];
            currentSection.style.display = 'block';
            document.getElementById('current-code').textContent = current.song_code || '-';
            document.getElementById('current-title').textContent = current.song_title || '-';
            document.getElementById('current-artist').textContent = current.song_artist || '-';
            document.getElementById('current-singer').textContent = current.singer_name || '-';

            // Remaining queue
            const remaining = queueData.slice(1);

            if (remaining.length === 0) {
                listEl.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">✨</div>
                        <p>Próximas músicas aparecerão aqui</p>
                    </div>
                `;
                return;
            }

            listEl.innerHTML = remaining.map((item, index) => `
                <div class="queue-item" data-id="${item.id}">
                    <span class="drag-handle">≡</span>
                    <div class="reorder-btns">
                        <button class="reorder-btn" onclick="moveUp(${item.id})">▲</button>
                        <button class="reorder-btn" onclick="moveDown(${item.id})">▼</button>
                    </div>
                    <div class="queue-position">${index + 1}</div>
                    <div class="queue-song-info">
                        <h4>${escapeHtml(item.song_title)}</h4>
                        <p>${escapeHtml(item.song_artist)}</p>
                    </div>
                    <div class="queue-meta">
                        <div class="singer">👤 ${escapeHtml(item.singer_name)}</div>
                        <div>${item.wait_time || '-'} | 🎵 ${item.songs_sung || 0} cantadas</div>
                    </div>
                    <div class="queue-actions">
                        <button class="btn-play" onclick="playNow(${item.id})" title="Tocar agora">▶</button>
                        <button class="btn-remove" onclick="removeFromQueue(${item.id})" title="Remover">×</button>
                    </div>
                </div>
            `).join('');
        }

        // Toggle queue status
        async function toggleQueueStatus() {
            if (!eventData) return;

            try {
                await fetch(API_BASE + 'event.php?action=toggle&id=' + eventData.id, {
                    method: 'POST',
                    credentials: 'include'
                });

                eventData.is_open = !eventData.is_open;
                updateEventDisplay();
                showToast(eventData.is_open ? 'Fila aberta!' : 'Fila fechada!');
            } catch (e) {
                showToast('Erro ao alterar status', 'error');
            }
        }

        // Generate new code
        async function generateNewCode() {
            if (!confirm('Gerar novo código vai desconectar clientes atuais. Continuar?')) return;

            try {
                const response = await fetch(API_BASE + 'event.php?action=new_code&id=' + eventData.id, {
                    method: 'POST',
                    credentials: 'include'
                });
                const data = await response.json();

                if (data.success) {
                    eventData.event_code = data.new_code;
                    updateEventDisplay();
                    showToast('Novo código gerado!');
                }
            } catch (e) {
                showToast('Erro ao gerar código', 'error');
            }
        }

        // Next song
        async function nextSong() {
            try {
                await fetch(API_BASE + 'queue.php?action=next', {
                    method: 'POST',
                    credentials: 'include'
                });
                showToast('Próxima música!');
                await loadQueue();
            } catch (e) {
                showToast('Erro ao avançar', 'error');
            }
        }

        // Skip song
        async function skipSong() {
            try {
                await fetch(API_BASE + 'queue.php?action=skip', {
                    method: 'POST',
                    credentials: 'include'
                });
                showToast('Música pulada!');
                await loadQueue();
            } catch (e) {
                showToast('Erro ao pular', 'error');
            }
        }

        // Play now (move to current)
        async function playNow(id) {
            try {
                await fetch(API_BASE + 'queue.php?action=play&id=' + id, {
                    method: 'POST',
                    credentials: 'include'
                });
                showToast('Tocando agora!');
                await loadQueue();
            } catch (e) {
                showToast('Erro ao tocar', 'error');
            }
        }

        // Remove from queue
        async function removeFromQueue(id) {
            if (!confirm('Remover esta música da fila?')) return;

            try {
                await fetch(API_BASE + 'queue.php?id=' + id, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                showToast('Removido da fila');
                await loadQueue();
            } catch (e) {
                showToast('Erro ao remover', 'error');
            }
        }

        // Clear queue
        async function clearQueue() {
            if (!confirm('Limpar TODA a fila? Esta ação não pode ser desfeita.')) return;

            try {
                await fetch(API_BASE + 'queue.php?action=clear', {
                    method: 'POST',
                    credentials: 'include'
                });
                showToast('Fila limpa!');
                await loadQueue();
            } catch (e) {
                showToast('Erro ao limpar', 'error');
            }
        }

        // Move up in queue
        async function moveUp(id) {
            try {
                await fetch(API_BASE + 'queue.php?action=reorder', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, direction: 'up' })
                });
                await loadQueue();
            } catch (e) {
                showToast('Erro ao reordenar', 'error');
            }
        }

        // Move down in queue
        async function moveDown(id) {
            try {
                await fetch(API_BASE + 'queue.php?action=reorder', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, direction: 'down' })
                });
                await loadQueue();
            } catch (e) {
                showToast('Erro ao reordenar', 'error');
            }
        }

        // Logout
        async function logout() {
            try {
                await fetch(API_BASE + 'auth.php?action=logout', {
                    method: 'POST',
                    credentials: 'include'
                });
            } catch (e) { }

            window.location.href = 'login.php';
        }

        // Helpers
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

        // Open TV Mode in new window
        function openTVMode() {
            if (!eventData || !eventData.event_code) {
                showToast('Evento não carregado', 'error');
                return;
            }
            const tvUrl = `../tv/?code=${eventData.event_code}`;
            window.open(tvUrl, '_blank', 'width=1280,height=720');
        }
    </script>
</body>

</html>
