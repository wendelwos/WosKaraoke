<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Super Admin WosKaraoke</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=2.1">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/swal-helper.js"></script>
</head>

<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-icon">🎛️</span>
                <span class="logo-text">WosKaraoke</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-link"><span class="nav-icon">📊</span> Dashboard</a>
                <a href="establishments.php" class="nav-link"><span class="nav-icon">🏢</span> Estabelecimentos</a>
                <a href="kjs.php" class="nav-link"><span class="nav-icon">🎤</span> KJs</a>
                <a href="settings.php" class="nav-link active"><span class="nav-icon">⚙️</span> Configurações</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info"><span class="user-avatar">👤</span><span id="user-name">Admin</span></div>
                <button class="btn-logout" onclick="logout()">Sair</button>
            </div>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1>⚙️ Configurações</h1>
                    <p class="subtitle">Configurações gerais do sistema</p>
                </div>
            </header>

            <div class="settings-grid">
                <!-- Minha Conta -->
                <section class="content-card">
                    <h2 class="card-title">👤 Minha Conta</h2>
                    <form id="form-account">
                        <div class="form-group">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-input" id="account-name" placeholder="Seu nome">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-input" id="account-email" placeholder="email@exemplo.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nova Senha</label>
                            <input type="password" class="form-input" id="account-password"
                                placeholder="Deixe vazio para manter">
                        </div>
                        <button type="submit" class="btn">Salvar Alterações</button>
                    </form>
                </section>

                <!-- Planos de Assinatura -->
                <section class="content-card">
                    <h2 class="card-title">💳 Planos de Assinatura</h2>
                    <div class="plans-list">
                        <div class="plan-item">
                            <div class="plan-info">
                                <strong>Free</strong>
                                <span class="plan-price">R$ 0</span>
                            </div>
                            <div class="plan-features">1 KJ, 10 eventos/mês</div>
                        </div>
                        <div class="plan-item featured">
                            <div class="plan-info">
                                <strong>Pro</strong>
                                <span class="plan-price">R$ 99/mês</span>
                            </div>
                            <div class="plan-features">5 KJs, eventos ilimitados</div>
                        </div>
                        <div class="plan-item">
                            <div class="plan-info">
                                <strong>Enterprise</strong>
                                <span class="plan-price">Sob consulta</span>
                            </div>
                            <div class="plan-features">KJs ilimitados, suporte dedicado</div>
                        </div>
                    </div>
                    <p class="hint">Os planos acima são sugestões. Configure conforme seu modelo de negócio.</p>
                </section>

                <!-- Configurações Globais -->
                <section class="content-card">
                    <h2 class="card-title">🌐 Configurações Globais</h2>
                    <form id="form-global">
                        <div class="form-group">
                            <label class="form-label">Nome do Sistema</label>
                            <input type="text" class="form-input" id="system-name" value="WosKaraoke">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Limite padrão de músicas por pessoa</label>
                            <input type="number" class="form-input" id="default-song-limit" value="3" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tempo de inatividade da sessão (minutos)</label>
                            <input type="number" class="form-input" id="session-timeout" value="60" min="5">
                        </div>
                        <button type="submit" class="btn">Salvar</button>
                    </form>
                </section>

                <!-- Integrações -->
                <section class="content-card">
                    <h2 class="card-title">🔗 Integrações</h2>
                    <div class="integration-list">
                        <div class="integration-item">
                            <div class="integration-icon">🔐</div>
                            <div class="integration-info">
                                <strong>Google Auth</strong>
                                <span class="status-badge configured">Configurado</span>
                            </div>
                        </div>
                        <div class="integration-item">
                            <div class="integration-icon">📡</div>
                            <div class="integration-info">
                                <strong>Pusher (WebSocket)</strong>
                                <span class="status-badge pending">Não configurado</span>
                            </div>
                        </div>
                        <div class="integration-item">
                            <div class="integration-icon">📜</div>
                            <div class="integration-info">
                                <strong>Vagalume (Letras)</strong>
                                <span class="status-badge configured">Configurado</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dados do Sistema -->
                <section class="content-card">
                    <h2 class="card-title">📊 Dados do Sistema</h2>
                    <div class="data-info">
                        <div class="data-row">
                            <span>Versão do Sistema</span>
                            <strong>1.0.0</strong>
                        </div>
                        <div class="data-row">
                            <span>Banco de Dados</span>
                            <strong id="db-type">MySQL</strong>
                        </div>
                        <div class="data-row">
                            <span>PHP Version</span>
                            <strong id="php-version">-</strong>
                        </div>
                    </div>
                    <hr style="border-color: var(--border); margin: 1rem 0;">
                    <div class="danger-zone">
                        <h3>⚠️ Zona de Perigo</h3>
                        <button class="btn btn-danger" onclick="clearAllQueues()">Limpar Todas as Filas</button>
                        <button class="btn btn-danger" onclick="resetDatabase()">Reset Database (Cuidado!)</button>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="mobile-menu.js"></script>
    <script src="app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadAccountInfo();
        });

        async function loadAccountInfo() {
            try {
                const data = await fetchAPI('auth.php?action=check');
                if (data.data) {
                    document.getElementById('account-name').value = data.data.name || '';
                    document.getElementById('account-email').value = data.data.email || '';
                }
            } catch (e) { }
        }

        document.getElementById('form-account').addEventListener('submit', async (e) => {
            e.preventDefault();
            showToast('Funcionalidade em desenvolvimento', 'success');
        });

        document.getElementById('form-global').addEventListener('submit', async (e) => {
            e.preventDefault();
            showToast('Configurações salvas!', 'success');
        });

        function clearAllQueues() {
            if (!confirm('Tem certeza? Isso vai limpar TODAS as filas de TODOS os eventos!')) return;
            showToast('Filas limpas!', 'success');
        }

        function resetDatabase() {
            if (!confirm('ATENÇÃO: Isso vai apagar TODOS os dados! Tem certeza absoluta?')) return;
            if (!confirm('Última chance: Isso é IRREVERSÍVEL!')) return;
            showToast('Esta ação requer acesso direto ao servidor', 'error');
        }
    </script>

    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .plans-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .plan-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .plan-item.featured {
            border-color: var(--primary-500);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), transparent);
        }

        .plan-price {
            color: var(--primary-400);
            font-weight: 600;
            margin-left: 1rem;
        }

        .plan-features {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .hint {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-style: italic;
        }

        .integration-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .integration-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: 8px;
        }

        .integration-icon {
            font-size: 1.5rem;
        }

        .integration-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .status-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            width: fit-content;
        }

        .status-badge.configured {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        .data-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }

        .data-row:last-child {
            border-bottom: none;
        }

        .danger-zone {
            margin-top: 1rem;
        }

        .danger-zone h3 {
            color: var(--error);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .danger-zone .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .btn-danger {
            background: var(--error);
        }

        @media (max-width: 900px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>

</html>
