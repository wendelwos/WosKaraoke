<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Estabelecimento WosKaraoke</title>
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
            --primary-500: #f59e0b;
            --primary-600: #d97706;
            --accent: #8b5cf6;
            --success: #22c55e;
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
            background: var(--primary-500);
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

        .welcome {
            margin-bottom: 2rem;
        }

        .welcome h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .welcome p {
            color: var(--text-muted);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-500);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .section-link {
            color: var(--primary-500);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item-info h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .list-item-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
        }

        .badge-muted {
            background: rgba(107, 107, 128, 0.2);
            color: var(--text-muted);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .empty-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .header {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .header-nav {
                order: 3;
                width: 100%;
                justify-content: center;
            }

            .sections {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
</head>

<body>
    <header class="header">
        <div class="header-brand">🏢 <span id="establishment-name">Estabelecimento</span></div>
        <nav class="header-nav">
            <a href="index.php" class="nav-link active">📊 Dashboard</a>
            <a href="kjs.php" class="nav-link">👤 KJs</a>
            <a href="events.php" class="nav-link">🎵 Eventos</a>
            <a href="billing.php" class="nav-link">💰 Planos</a>
            <a href="settings.php" class="nav-link">⚙️ Config</a>
        </nav>
        <div class="header-right">
            <button class="btn-logout" onclick="logout()">Sair</button>
        </div>
    </header>

    <main class="main">
        <div class="welcome">
            <h1>Bem-vindo! 👋</h1>
            <p>Gerencie seus KJs e acompanhe o desempenho do seu karaokê</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="stat-kjs">0</div>
                <div class="stat-label">KJs Cadastrados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-active-kjs">0</div>
                <div class="stat-label">KJs Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-events">0</div>
                <div class="stat-label">Eventos Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-songs">0</div>
                <div class="stat-label">Músicas Hoje</div>
            </div>
        </div>

        <div class="sections">
            <div class="section">
                <div class="section-header">
                    <h3 class="section-title">👤 Seus KJs</h3>
                    <a href="kjs.php" class="section-link">Ver todos →</a>
                </div>
                <div id="kjs-list">
                    <div class="empty-state">
                        <div class="empty-icon">👤</div>
                        <p>Nenhum KJ cadastrado</p>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h3 class="section-title">🎵 Eventos Recentes</h3>
                    <a href="events.php" class="section-link">Ver todos →</a>
                </div>
                <div id="events-list">
                    <div class="empty-state">
                        <div class="empty-icon">🎵</div>
                        <p>Nenhum evento ainda</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const API_BASE = '../api/establishment/';

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadStats();
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

        async function loadStats() {
            try {
                const response = await fetch(API_BASE + 'stats.php', { credentials: 'include' });
                const data = await response.json();

                if (!data.success) return;

                // Stats
                document.getElementById('stat-kjs').textContent = data.stats.total_kjs;
                document.getElementById('stat-active-kjs').textContent = data.stats.active_kjs;
                document.getElementById('stat-events').textContent = data.stats.active_events;
                document.getElementById('stat-songs').textContent = data.stats.songs_today;

                // KJs list
                if (data.recent_kjs && data.recent_kjs.length > 0) {
                    document.getElementById('kjs-list').innerHTML = data.recent_kjs.map(kj => `
                        <div class="list-item">
                            <div class="list-item-info">
                                <h4>${escapeHtml(kj.name)}</h4>
                                <p>@${escapeHtml(kj.username)}</p>
                            </div>
                            <span class="badge ${kj.is_active ? 'badge-success' : 'badge-muted'}">
                                ${kj.is_active ? 'Ativo' : 'Inativo'}
                            </span>
                        </div>
                    `).join('');
                }

                // Events list
                if (data.recent_events && data.recent_events.length > 0) {
                    document.getElementById('events-list').innerHTML = data.recent_events.map(event => `
                        <div class="list-item">
                            <div class="list-item-info">
                                <h4>${escapeHtml(event.event_name)}</h4>
                                <p>Código: ${event.event_code} • KJ: ${escapeHtml(event.kj_name || '-')}</p>
                            </div>
                            <span class="badge ${event.is_open ? 'badge-success' : 'badge-muted'}">
                                ${event.is_open ? 'Aberto' : 'Fechado'}
                            </span>
                        </div>
                    `).join('');
                }

            } catch (e) {
                console.error(e);
            }
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
    </script>
</body>


</html>