<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar KJs - Estabelecimento WosKaraoke</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .page-subtitle {
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
            background: var(--primary-500);
            color: white;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
        }

        .kjs-grid {
            display: grid;
            gap: 1rem;
        }

        .kj-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .kj-info h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .kj-info p {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .kj-info .username {
            color: var(--accent);
        }

        .kj-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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
            max-width: 450px;
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
        }

        .modal-footer {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
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

            .kj-card {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
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
            <a href="kjs.php" class="nav-link active">👤 KJs</a>
            <a href="events.php" class="nav-link">🎵 Eventos</a>
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
                <h1 class="page-title">👤 Gerenciar KJs</h1>
                <p class="page-subtitle">Crie e gerencie seus DJs de karaokê</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Novo KJ</button>
        </div>

        <div class="kjs-grid" id="kjs-list">
            <div class="empty-state">
                <div class="empty-icon">👤</div>
                <h3>Nenhum KJ cadastrado</h3>
                <p>Crie seu primeiro KJ para começar a usar o sistema</p>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Criar KJ</button>
            </div>
        </div>
    </main>

    <!-- Modal Create/Edit -->
    <div class="modal-backdrop" id="modal-form">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">Novo KJ</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="form-kj">
                <div class="modal-body">
                    <input type="hidden" id="form-id">

                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-input" id="form-name" placeholder="Nome do KJ" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-input" id="form-username" placeholder="usuario_login" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" id="form-email" placeholder="email@exemplo.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Senha <span id="password-hint">(obrigatória)</span></label>
                        <input type="password" class="form-input" id="form-password" placeholder="••••••••">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="form-active">
                            <option value="1">🟢 Ativo</option>
                            <option value="0">🔴 Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '../api/establishment/';
        let kjs = [];
        let editingId = null;

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadKJs();
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
                const response = await fetch(API_BASE + 'kjs.php?action=list', { credentials: 'include' });
                const data = await response.json();
                kjs = data.data || [];
                renderKJs();
            } catch (e) {
                console.error(e);
            }
        }

        function renderKJs() {
            const container = document.getElementById('kjs-list');

            if (kjs.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">👤</div>
                        <h3>Nenhum KJ cadastrado</h3>
                        <p>Crie seu primeiro KJ para começar a usar o sistema</p>
                        <button class="btn btn-primary" onclick="openCreateModal()">+ Criar KJ</button>
                    </div>
                `;
                return;
            }

            container.innerHTML = kjs.map(kj => `
                <div class="kj-card">
                    <div class="kj-info">
                        <h3>${escapeHtml(kj.name)}</h3>
                        <p class="username">@${escapeHtml(kj.username)}</p>
                        <p>${kj.email || 'Sem email'} • ${kj.last_login ? 'Último acesso: ' + formatDate(kj.last_login) : 'Nunca acessou'}</p>
                    </div>
                    <div class="kj-actions">
                        <span class="badge ${kj.is_active ? 'badge-success' : 'badge-muted'}">
                            ${kj.is_active ? 'Ativo' : 'Inativo'}
                        </span>
                        <button class="btn btn-secondary btn-sm" onclick="editKJ(${kj.id})">✏️ Editar</button>
                        <button class="btn ${kj.is_active ? 'btn-danger' : 'btn-success'} btn-sm" onclick="toggleKJ(${kj.id})">
                            ${kj.is_active ? '🔴' : '🟢'}
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteKJ(${kj.id})">🗑️</button>
                    </div>
                </div>
            `).join('');
        }

        function openCreateModal() {
            editingId = null;
            document.getElementById('modal-title').textContent = 'Novo KJ';
            document.getElementById('form-kj').reset();
            document.getElementById('form-id').value = '';
            document.getElementById('form-username').disabled = false;
            document.getElementById('form-password').required = true;
            document.getElementById('password-hint').textContent = '(obrigatória)';
            openModal();
        }

        function editKJ(id) {
            const kj = kjs.find(k => k.id === id);
            if (!kj) return;

            editingId = id;
            document.getElementById('modal-title').textContent = 'Editar KJ';
            document.getElementById('form-id').value = id;
            document.getElementById('form-name').value = kj.name;
            document.getElementById('form-username').value = kj.username;
            document.getElementById('form-username').disabled = true;
            document.getElementById('form-email').value = kj.email || '';
            document.getElementById('form-password').value = '';
            document.getElementById('form-password').required = false;
            document.getElementById('password-hint').textContent = '(deixe em branco para manter)';
            document.getElementById('form-active').value = kj.is_active ? '1' : '0';
            openModal();
        }

        document.getElementById('form-kj').addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('form-id').value;
            const payload = {
                name: document.getElementById('form-name').value,
                username: document.getElementById('form-username').value,
                email: document.getElementById('form-email').value,
                password: document.getElementById('form-password').value,
                is_active: parseInt(document.getElementById('form-active').value)
            };

            try {
                const endpoint = id ? `kjs.php?action=update&id=${id}` : 'kjs.php?action=create';
                const response = await fetch(API_BASE + endpoint, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    showToast(id ? 'KJ atualizado!' : 'KJ criado!', 'success');
                    closeModal();
                    loadKJs();
                } else {
                    showToast(data.error || 'Erro ao salvar', 'error');
                }
            } catch (err) {
                showToast('Erro de conexão', 'error');
            }
        });

        async function toggleKJ(id) {
            try {
                await fetch(API_BASE + `kjs.php?action=toggle&id=${id}`, {
                    method: 'POST',
                    credentials: 'include'
                });
                loadKJs();
            } catch (e) {
                showToast('Erro', 'error');
            }
        }

        async function deleteKJ(id) {
            if (!confirm('Tem certeza que deseja excluir este KJ?')) return;

            try {
                await fetch(API_BASE + `kjs.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                showToast('KJ excluído', 'success');
                loadKJs();
            } catch (e) {
                showToast('Erro', 'error');
            }
        }

        async function logout() {
            try {
                await fetch(API_BASE + 'auth.php?action=logout', { method: 'POST', credentials: 'include' });
            } catch (e) { }
            window.location.href = 'login.php';
        }

        function openModal() { document.getElementById('modal-form').classList.add('active'); }
        function closeModal() { document.getElementById('modal-form').classList.remove('active'); }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleDateString('pt-BR');
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