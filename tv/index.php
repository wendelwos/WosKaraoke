<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎤 WosKaraoke TV</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a14;
            --bg-secondary: #12122a;
            --bg-card: #1a1a3a;
            --text-primary: #ffffff;
            --text-secondary: #a0a0c0;
            --primary: #8b5cf6;
            --success: #22c55e;
            --warning: #f59e0b;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
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
            overflow: hidden;
        }

        .tv-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            grid-template-rows: auto 1fr auto;
            height: 100vh;
            padding: 2rem;
            gap: 2rem;
        }

        /* Header */
        .header {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 2rem;
            font-weight: 900;
        }

        .event-name {
            font-size: 1.5rem;
            color: var(--primary);
            font-weight: 600;
        }

        .status-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--success);
            border-radius: 2rem;
            font-weight: 600;
        }

        .status-badge.closed {
            background: var(--warning);
        }

        .status-dot {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        /* Current Song - Main Area */
        .current-song {
            background: var(--bg-card);
            border: 3px solid var(--primary);
            border-radius: 2rem;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .current-song::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .current-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--success);
            font-size: 1.25rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 1.5rem;
            z-index: 1;
        }

        .current-code {
            background: var(--gradient);
            padding: 1rem 2.5rem;
            border-radius: 1rem;
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            z-index: 1;
        }

        .current-title {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            z-index: 1;
            line-height: 1.1;
        }

        .current-artist {
            font-size: 2rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            z-index: 1;
        }

        .current-singer {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.75rem;
            color: var(--success);
            font-weight: 600;
            z-index: 1;
        }

        .singer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
        }

        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 5rem;
        }

        .empty-text {
            font-size: 2rem;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Queue */
        .queue-section {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 1.5rem;
            flex: 1;
            overflow: hidden;
        }

        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .queue-title {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .queue-count {
            background: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .queue-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-height: calc(100% - 60px);
            overflow-y: auto;
        }

        .queue-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .queue-position {
            background: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .queue-info {
            flex: 1;
            min-width: 0;
        }

        .queue-song {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .queue-singer {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* QR Code Section */
        .qr-section {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 1.5rem;
            text-align: center;
        }

        .qr-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
        }

        .qr-code {
            background: white;
            padding: 1rem;
            border-radius: 1rem;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .qr-code img {
            width: 150px;
            height: 150px;
        }

        .event-code {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            letter-spacing: 5px;
        }

        .qr-hint {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        /* Footer */
        .footer {
            grid-column: 1 / -1;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Loading */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            gap: 1rem;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--bg-card);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Error */
        .error-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            gap: 1rem;
            text-align: center;
        }

        .error-icon {
            font-size: 5rem;
        }

        .error-text {
            font-size: 1.5rem;
            color: var(--text-secondary);
        }

        .btn-retry {
            padding: 1rem 2rem;
            background: var(--primary);
            border: none;
            border-radius: 1rem;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
        }
    </style>
</head>

<body>
    <div id="app">
        <div class="loading">
            <div class="spinner"></div>
            <p>Carregando evento...</p>
        </div>
    </div>

    <script>
        const API_BASE = '../api/';
        let eventData = null;
        let queueData = null;
        let refreshInterval = null;

        // Get event code from URL
        const urlParams = new URLSearchParams(window.location.search);
        const eventCode = urlParams.get('code');
        const eventId = urlParams.get('event');

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            if (!eventCode && !eventId) {
                showError('Código do evento não informado', 'Use ?code=XXXX ou ?event=ID na URL');
                return;
            }

            await loadEventData();

            if (eventData) {
                await loadQueue();
                renderTV();

                // Auto-refresh every 3 seconds
                refreshInterval = setInterval(async () => {
                    await loadQueue();
                    updateDisplay();
                }, 3000);
            }
        });

        // Load event data
        async function loadEventData() {
            try {
                if (eventCode) {
                    // Verify by code
                    const response = await fetch(`${API_BASE}admin/event.php?action=verify`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code: eventCode })
                    });
                    const result = await response.json();

                    if (result.success) {
                        // API returns event_id and event_name at top level
                        eventData = {
                            id: result.event_id || result.data?.id,
                            event_name: result.event_name || result.data?.event_name || 'Karaokê',
                            is_open: result.is_open !== undefined ? result.is_open : true,
                            event_code: eventCode
                        };
                    } else {
                        showError('Evento não encontrado', 'Verifique o código e tente novamente');
                        return;
                    }
                } else if (eventId) {
                    // Get by ID
                    const response = await fetch(`${API_BASE}admin/event.php?id=${eventId}`);
                    const result = await response.json();

                    if (result.success) {
                        eventData = result.data || {
                            id: result.id || eventId,
                            event_name: result.event_name || 'Karaokê',
                            is_open: result.is_open !== undefined ? result.is_open : true,
                            event_code: result.event_code || '----'
                        };
                        // Ensure id is set
                        if (!eventData.id) eventData.id = eventId;
                    } else {
                        showError('Evento não encontrado', 'Verifique o ID e tente novamente');
                        return;
                    }
                }
            } catch (e) {
                showError('Erro ao carregar evento', e.message);
            }
        }

        // Load queue
        async function loadQueue() {
            if (!eventData) return;

            try {
                const response = await fetch(`${API_BASE}admin/queue.php?event_id=${eventData.id}`);
                const result = await response.json();

                if (result.success) {
                    queueData = result.data;
                }
            } catch (e) {
                console.error('Queue error:', e);
            }
        }

        // Render TV interface
        function renderTV() {
            const app = document.getElementById('app');
            const isOpen = eventData.is_open;
            const code = eventData.event_code || eventData.code || '----';

            app.innerHTML = `
                <div class="tv-container">
                    <header class="header">
                        <div class="logo">
                            🎤 WosKaraoke
                            <span class="event-name">${escapeHtml(eventData.event_name || 'Karaokê')}</span>
                        </div>
                        <div class="status-badge ${isOpen ? '' : 'closed'}">
                            <span class="status-dot"></span>
                            ${isOpen ? 'FILA ABERTA' : 'FILA FECHADA'}
                        </div>
                    </header>

                    <section class="current-song" id="current-song">
                        ${renderCurrentSong()}
                    </section>

                    <aside class="sidebar">
                        <section class="queue-section">
                            <div class="queue-header">
                                <span class="queue-title">📋 Próximos</span>
                                <span class="queue-count" id="queue-count">0</span>
                            </div>
                            <div class="queue-list" id="queue-list">
                                ${renderQueueList()}
                            </div>
                        </section>

                        <section class="qr-section">
                            <div class="qr-label">Escaneie para participar</div>
                            <div class="qr-code">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(window.location.origin + '/WosKaraoke/?code=' + code)}" alt="QR Code">
                            </div>
                            <div class="event-code">${code}</div>
                            <div class="qr-hint">Digite este código no app</div>
                        </section>
                    </aside>

                    <footer class="footer">
                        Powered by WosKaraoke • Atualização automática
                    </footer>
                </div>
            `;
        }

        // Render current song
        function renderCurrentSong() {
            if (!queueData || !queueData.current) {
                return `
                    <div class="empty-state">
                        <div class="empty-icon">🎤</div>
                        <div class="empty-text">Aguardando próximo cantor...</div>
                    </div>
                `;
            }

            const song = queueData.current;
            const avatarColor = getAvatarColor(song.profile_name);
            const initials = getInitials(song.profile_name);

            return `
                <div class="current-label">
                    <span>🎵</span> Cantando Agora
                </div>
                <div class="current-code">${escapeHtml(song.song_code)}</div>
                <div class="current-title">${escapeHtml(song.song_title)}</div>
                <div class="current-artist">${escapeHtml(song.song_artist)}</div>
                <div class="current-singer">
                    <div class="singer-avatar" style="background: ${avatarColor}">${initials}</div>
                    ${escapeHtml(song.profile_name)}
                    ${song.table_number ? `<span style="opacity: 0.7">• Mesa ${song.table_number}</span>` : ''}
                </div>
            `;
        }

        // Render queue list
        function renderQueueList() {
            if (!queueData || !queueData.waiting || queueData.waiting.length === 0) {
                return '<div style="text-align: center; color: var(--text-secondary); padding: 2rem;">Fila vazia</div>';
            }

            return queueData.waiting.slice(0, 5).map((item, index) => `
                <div class="queue-item">
                    <div class="queue-position">${index + 1}</div>
                    <div class="queue-info">
                        <div class="queue-song">${escapeHtml(item.song_title)}</div>
                        <div class="queue-singer">👤 ${escapeHtml(item.profile_name)}</div>
                    </div>
                </div>
            `).join('');
        }

        // Update display (called on refresh)
        function updateDisplay() {
            const currentSong = document.getElementById('current-song');
            const queueList = document.getElementById('queue-list');
            const queueCount = document.getElementById('queue-count');

            if (currentSong) {
                currentSong.innerHTML = renderCurrentSong();
            }

            if (queueList) {
                queueList.innerHTML = renderQueueList();
            }

            if (queueCount && queueData) {
                queueCount.textContent = queueData.stats?.waiting_count || 0;
            }
        }

        // Show error
        function showError(title, message) {
            const app = document.getElementById('app');
            app.innerHTML = `
                <div class="error-state">
                    <div class="error-icon">❌</div>
                    <h1>${escapeHtml(title)}</h1>
                    <p class="error-text">${escapeHtml(message)}</p>
                    <button class="btn-retry" onclick="location.reload()">Tentar novamente</button>
                </div>
            `;
        }

        // Helpers
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getInitials(name) {
            if (!name) return '?';
            return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        }

        function getAvatarColor(name) {
            const colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#eab308', '#22c55e', '#14b8a6'];
            let hash = 0;
            for (let i = 0; i < (name || '').length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            return colors[Math.abs(hash) % colors.length];
        }
    </script>
</body>

</html>