<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Super Admin WosKaraoke</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=2.1">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
</head>

<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-icon">🎛️</span>
                <span class="logo-text">WosKaraoke</span>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="nav-link active">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="establishments.php" class="nav-link">
                    <span class="nav-icon">🏢</span>
                    Estabelecimentos
                </a>
                <a href="events.php" class="nav-link">
                    <span class="nav-icon">🎵</span>
                    Eventos
                </a>
                <a href="kjs.php" class="nav-link">
                    <span class="nav-icon">🎤</span>
                    KJs
                </a>
                <a href="settings.php" class="nav-link">
                    <span class="nav-icon">⚙️</span>
                    Configurações
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-avatar">👤</span>
                    <span id="user-name">Admin</span>
                </div>
                <button class="btn-logout" onclick="logout()">Sair</button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Dashboard</h1>
                <p class="subtitle">Visão geral do sistema</p>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid" id="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-info">
                        <div class="stat-value" id="stat-establishments">-</div>
                        <div class="stat-label">Estabelecimentos</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎤</div>
                    <div class="stat-info">
                        <div class="stat-value" id="stat-kjs">-</div>
                        <div class="stat-label">KJs Ativos</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎵</div>
                    <div class="stat-info">
                        <div class="stat-value" id="stat-events">-</div>
                        <div class="stat-label">Eventos Ativos</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <div class="stat-value" id="stat-users">-</div>
                        <div class="stat-label">Usuários</div>
                    </div>
                </div>
                <div class="stat-card accent">
                    <div class="stat-icon">🎶</div>
                    <div class="stat-info">
                        <div class="stat-value" id="stat-songs-today">-</div>
                        <div class="stat-label">Músicas Hoje</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-info">
                        <div class="stat-value" id="stat-songs-total">-</div>
                        <div class="stat-label">Músicas Total</div>
                    </div>
                </div>
            </div>

            <!-- Recent Items -->
            <div class="content-grid">
                <section class="content-card">
                    <h2 class="card-title">🏢 Últimos Estabelecimentos</h2>
                    <div id="recent-establishments" class="list">
                        <div class="loading">Carregando...</div>
                    </div>
                    <a href="establishments.php" class="card-link">Ver todos →</a>
                </section>

                <section class="content-card">
                    <h2 class="card-title">🎤 Últimos KJs</h2>
                    <div id="recent-kjs" class="list">
                        <div class="loading">Carregando...</div>
                    </div>
                    <a href="kjs.php" class="card-link">Ver todos →</a>
                </section>
            </div>
        </main>
    </div>

    <script src="mobile-menu.js"></script>
    <script src="app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadStats();
        });

        async function loadStats() {
            try {
                const data = await fetchAPI('stats.php');
                const stats = data.data;

                // Update stat cards
                document.getElementById('stat-establishments').textContent = stats.totals.establishments;
                document.getElementById('stat-kjs').textContent = stats.totals.kjs_active;
                document.getElementById('stat-events').textContent = stats.totals.events_active;
                document.getElementById('stat-users').textContent = stats.totals.users;
                document.getElementById('stat-songs-today').textContent = stats.totals.songs_today;
                document.getElementById('stat-songs-total').textContent = stats.totals.songs_total;

                // Recent establishments
                const estContainer = document.getElementById('recent-establishments');
                if (stats.recent_establishments.length > 0) {
                    estContainer.innerHTML = stats.recent_establishments.map(e => `
                        <div class="list-item">
                            <div class="item-info">
                                <div class="item-name">${escapeHtml(e.name)}</div>
                                <div class="item-meta">${e.slug}</div>
                            </div>
                            <span class="badge ${e.is_active ? 'badge-success' : 'badge-muted'}">${e.is_active ? 'Ativo' : 'Inativo'}</span>
                        </div>
                    `).join('');
                } else {
                    estContainer.innerHTML = '<p class="empty">Nenhum estabelecimento</p>';
                }

                // Recent KJs
                const kjContainer = document.getElementById('recent-kjs');
                if (stats.recent_kjs.length > 0) {
                    kjContainer.innerHTML = stats.recent_kjs.map(k => `
                        <div class="list-item">
                            <div class="item-info">
                                <div class="item-name">${escapeHtml(k.name)}</div>
                                <div class="item-meta">${escapeHtml(k.establishment_name || 'Sem vínculo')}</div>
                            </div>
                            <span class="badge ${k.is_active ? 'badge-success' : 'badge-muted'}">${k.is_active ? 'Ativo' : 'Inativo'}</span>
                        </div>
                    `).join('');
                } else {
                    kjContainer.innerHTML = '<p class="empty">Nenhum KJ</p>';
                }

            } catch (e) {
                console.error('Erro ao carregar stats:', e);
            }
        }
    </script>
</body>

</html>
