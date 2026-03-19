<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Estabelecimento WosKaraoke</title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            margin-bottom: 2rem;
        }

        .section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.125rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
            background: var(--primary-500);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-600);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-muted);
        }

        .info-value {
            font-weight: 500;
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

        .toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 8px;
            z-index: 1001;
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
            <a href="index.php" class="nav-link">📊 Dashboard</a>
            <a href="kjs.php" class="nav-link">👤 KJs</a>
            <a href="events.php" class="nav-link">🎵 Eventos</a>
            <a href="billing.php" class="nav-link">💰 Planos</a>
            <a href="settings.php" class="nav-link active">⚙️ Config</a>
        </nav>
        <div class="header-right">
            <button class="btn-logout" onclick="logout()">Sair</button>
        </div>
    </header>

    <main class="main">
        <h1 class="page-title">⚙️ Configurações</h1>

        <!-- Account Info -->
        <div class="section">
            <h2 class="section-title">🏢 Informações do Estabelecimento</h2>
            <div class="info-item">
                <span class="info-label">Nome</span>
                <span class="info-value" id="info-name">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value" id="info-email">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Slug (URL)</span>
                <span class="info-value" id="info-slug">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Plano</span>
                <span class="info-value"><span class="badge badge-success" id="info-plan">-</span></span>
            </div>
        </div>

        <!-- Change Password -->
        <div class="section">
            <h2 class="section-title">🔐 Alterar Senha</h2>
            <form id="form-password">
                <div class="form-group">
                    <label class="form-label">Senha Atual</label>
                    <input type="password" class="form-input" id="current-password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" class="form-input" id="new-password" required minlength="4">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmar Nova Senha</label>
                    <input type="password" class="form-input" id="confirm-password" required>
                </div>
                <button type="submit" class="btn btn-primary">Alterar Senha</button>
            </form>
        </div>

        <!-- Support -->
        <div class="section">
            <h2 class="section-title">📞 Suporte</h2>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">
                Precisa de ajuda? Entre em contato com o administrador da plataforma.
            </p>
            <a href="mailto:suporte@woskaraoke.com" class="btn btn-primary">📧 Enviar Email</a>
        </div>
    </main>

    <script>
        const API_BASE = '../api/establishment/';
        let establishmentData = null;

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
        });

        async function checkAuth() {
            try {
                const response = await fetch(API_BASE + 'auth.php', { credentials: 'include' });
                const data = await response.json();
                if (!data.logged) {
                    window.location.href = 'login.php';
                    return;
                }
                establishmentData = data.logged;
                document.getElementById('establishment-name').textContent = data.logged.name;

                // Populate info
                document.getElementById('info-name').textContent = data.logged.name;
                document.getElementById('info-email').textContent = data.logged.email || 'Não definido';
                document.getElementById('info-slug').textContent = data.logged.slug || '-';
                document.getElementById('info-plan').textContent = (data.logged.subscription_plan || 'free').toUpperCase();
            } catch (e) {
                window.location.href = 'login.php';
            }
        }

        document.getElementById('form-password').addEventListener('submit', async (e) => {
            e.preventDefault();

            const newPass = document.getElementById('new-password').value;
            const confirmPass = document.getElementById('confirm-password').value;

            if (newPass !== confirmPass) {
                showToast('As senhas não conferem', 'error');
                return;
            }

            showToast('Funcionalidade em desenvolvimento', 'error');
        });

        async function logout() {
            try { await fetch(API_BASE + 'auth.php?action=logout', { method: 'POST', credentials: 'include' }); } catch (e) { }
            window.location.href = 'login.php';
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