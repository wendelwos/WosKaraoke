<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batalhas - KJ WosKaraoke</title>
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

        /* Battle Card */
        .battles-grid {
            display: grid;
            gap: 1.5rem;
        }

        .battle-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        .battle-card.active {
            border-color: var(--warning);
        }

        .battle-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .battle-status.voting {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        .battle-status.finished {
            background: rgba(34, 197, 94, 0.2);
            color: var(--primary-500);
        }

        .battle-status.pending {
            background: rgba(107, 107, 128, 0.2);
            color: var(--text-muted);
        }

        .battle-contestants {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .contestant {
            text-align: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
        }

        .contestant.winner {
            background: rgba(34, 197, 94, 0.1);
            border: 2px solid var(--primary-500);
        }

        .contestant h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .contestant .song {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .contestant .votes {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            margin-top: 0.5rem;
        }

        .vs {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--warning);
        }

        .battle-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
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

        .toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 8px;
            z-index: 1000;
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

            .battle-contestants {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .vs {
                font-size: 1.25rem;
                padding: 0.5rem 0;
            }

            .contestant {
                padding: 0.75rem;
            }

            .contestant .votes {
                font-size: 1.5rem;
            }

            .battle-actions {
                flex-wrap: wrap;
            }

            .battle-actions .btn {
                flex: 1;
                min-width: 120px;
                justify-content: center;
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

            .page-header h1 {
                font-size: 1.25rem;
            }

            .battle-card {
                padding: 1rem;
            }

            .btn,
            button {
                min-height: 44px;
                font-size: 0.875rem;
            }

            .contestant h3 {
                font-size: 0.9rem;
            }

            .contestant .song {
                font-size: 0.8rem;
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
            <a href="events.php" class="nav-link">🎵 Eventos</a>
            <a href="battles.php" class="nav-link active">⚔️ Batalhas</a>
        </nav>
        <div class="header-right">
            <span id="user-name">KJ</span>
            <button class="btn-logout" onclick="logout()">Sair</button>
        </div>
    </header>

    <main class="main">
        <div class="page-header">
            <div>
                <h1>⚔️ Batalhas de Karaokê</h1>
                <p class="subtitle">Crie duelos entre cantores</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Nova Batalha</button>
        </div>

        <div class="battles-grid" id="battles-list">
            <div class="empty-state">
                <div class="empty-icon">⚔️</div>
                <h3>Nenhuma batalha</h3>
                <p>Crie uma batalha para os cantores competirem!</p>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Criar Batalha</button>
            </div>
        </div>
    </main>

    <!-- Create Battle Modal -->
    <div class="modal-backdrop" id="modal-create">
        <div class="modal">
            <div class="modal-header">
                <h2>Nova Batalha</h2>
                <button class="modal-close" onclick="closeModal('modal-create')">&times;</button>
            </div>
            <form id="form-battle">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Cantor 1 *</label>
                        <select class="form-select" id="singer1" required>
                            <option value="">Selecione da fila...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cantor 2 *</label>
                        <select class="form-select" id="singer2" required>
                            <option value="">Selecione da fila...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('modal-create')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Iniciar Batalha</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '../api/admin/';
        let battles = [];
        let queueData = [];

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            await loadQueue();
            await loadBattles();
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

        async function loadQueue() {
            try {
                const response = await fetch(API_BASE + 'queue.php', { credentials: 'include' });
                const data = await response.json();
                queueData = data.queue || [];
                populateSingerSelects();
            } catch (e) {
                console.error('Error loading queue:', e);
            }
        }

        function populateSingerSelects() {
            const select1 = document.getElementById('singer1');
            const select2 = document.getElementById('singer2');

            const options = queueData.map(item =>
                `<option value="${item.id}">${item.singer_name} - ${item.song_title}</option>`
            ).join('');

            select1.innerHTML = '<option value="">Selecione da fila...</option>' + options;
            select2.innerHTML = '<option value="">Selecione da fila...</option>' + options;
        }

        async function loadBattles() {
            try {
                const response = await fetch('../api/battle.php?action=list', { credentials: 'include' });
                const data = await response.json();
                battles = data.data || [];
                renderBattles();
            } catch (e) {
                console.error('Error loading battles:', e);
            }
        }

        function renderBattles() {
            const container = document.getElementById('battles-list');

            if (battles.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">⚔️</div>
                        <h3>Nenhuma batalha</h3>
                        <p>Crie uma batalha para os cantores competirem!</p>
                        <button class="btn btn-primary" onclick="openCreateModal()">+ Criar Batalha</button>
                    </div>
                `;
                return;
            }

            container.innerHTML = battles.map(b => {
                const statusClass = b.status === 'voting' ? 'voting' : (b.status === 'finished' ? 'finished' : 'pending');
                const statusText = b.status === 'voting' ? '🗳️ VOTAÇÃO ABERTA' : (b.status === 'finished' ? '✅ FINALIZADA' : '⏳ PENDENTE');

                const winner1 = b.status === 'finished' && b.votes_1 > b.votes_2;
                const winner2 = b.status === 'finished' && b.votes_2 > b.votes_1;

                return `
                    <div class="battle-card ${b.status === 'voting' ? 'active' : ''}">
                        <span class="battle-status ${statusClass}">${statusText}</span>
                        <div class="battle-contestants">
                            <div class="contestant ${winner1 ? 'winner' : ''}">
                                <h3>${escapeHtml(b.singer1_name || 'Cantor 1')}</h3>
                                <p class="song">${escapeHtml(b.song1_title || '-')}</p>
                                <div class="votes">${b.votes_1 || 0}</div>
                            </div>
                            <div class="vs">VS</div>
                            <div class="contestant ${winner2 ? 'winner' : ''}">
                                <h3>${escapeHtml(b.singer2_name || 'Cantor 2')}</h3>
                                <p class="song">${escapeHtml(b.song2_title || '-')}</p>
                                <div class="votes">${b.votes_2 || 0}</div>
                            </div>
                        </div>
                        <div class="battle-actions">
                            ${b.status === 'pending' ? `
                                <button class="btn btn-success" onclick="startVoting(${b.id})">🗳️ Abrir Votação</button>
                            ` : ''}
                            ${b.status === 'voting' ? `
                                <button class="btn btn-primary" onclick="endBattle(${b.id})">🏆 Encerrar</button>
                            ` : ''}
                            <button class="btn btn-danger" onclick="deleteBattle(${b.id})">🗑️ Excluir</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function openCreateModal() {
            openModal('modal-create');
        }

        document.getElementById('form-battle').addEventListener('submit', async (e) => {
            e.preventDefault();

            const singer1 = document.getElementById('singer1').value;
            const singer2 = document.getElementById('singer2').value;

            if (singer1 === singer2) {
                showToast('Selecione cantores diferentes!', 'error');
                return;
            }

            try {
                const response = await fetch('../api/battle.php?action=create', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        queue_item_1: singer1,
                        queue_item_2: singer2
                    })
                });

                const data = await response.json();
                if (data.success) {
                    showToast('Batalha criada!', 'success');
                    closeModal('modal-create');
                    loadBattles();
                } else {
                    showToast(data.error || 'Erro ao criar', 'error');
                }
            } catch (err) {
                showToast('Erro ao criar batalha', 'error');
            }
        });

        async function startVoting(id) {
            try {
                await fetch(`../api/battle.php?action=start&id=${id}`, {
                    method: 'POST',
                    credentials: 'include'
                });
                showToast('Votação aberta!', 'success');
                loadBattles();
            } catch (e) {
                showToast('Erro', 'error');
            }
        }

        async function endBattle(id) {
            try {
                await fetch(`../api/battle.php?action=end&id=${id}`, {
                    method: 'POST',
                    credentials: 'include'
                });
                showToast('Batalha encerrada!', 'success');
                loadBattles();
            } catch (e) {
                showToast('Erro', 'error');
            }
        }

        async function deleteBattle(id) {
            if (!confirm('Excluir esta batalha?')) return;

            try {
                await fetch(`../api/battle.php?action=delete&id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                showToast('Batalha excluída', 'success');
                loadBattles();
            } catch (e) {
                showToast('Erro', 'error');
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
