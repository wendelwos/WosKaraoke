<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KJs - Super Admin Karaoke Show</title>
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
                <span class="logo-text">Karaoke Show</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-link"><span class="nav-icon">📊</span> Dashboard</a>
                <a href="establishments.php" class="nav-link"><span class="nav-icon">🏢</span> Estabelecimentos</a>
                <a href="kjs.php" class="nav-link active"><span class="nav-icon">🎤</span> KJs</a>
                <a href="settings.php" class="nav-link"><span class="nav-icon">⚙️</span> Configurações</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info"><span class="user-avatar">👤</span><span id="user-name">Admin</span></div>
                <button class="btn-logout" onclick="logout()">Sair</button>
            </div>
        </aside>

        <main class="main-content">
            <header class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🎤 Karaoke Jockeys</h1>
                    <p class="subtitle">Gerenciar DJs e operadores de karaoke</p>
                </div>
                <button class="btn" onclick="openCreateModal()">+ Novo KJ</button>
            </header>

            <div class="content-card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th class="hide-mobile">Username</th>
                                <th>Estabelecimento</th>
                                <th class="hide-mobile">Role</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <tr>
                                <td colspan="6" class="loading">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Create/Edit -->
    <div class="modal-backdrop" id="modal-form">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-title">Novo KJ</h2>
                <button class="modal-close" onclick="closeModal('modal-form')">&times;</button>
            </div>
            <form id="form-kj">
                <input type="hidden" id="form-id">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" class="form-input" id="form-name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" class="form-input" id="form-username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="form-email">
                </div>
                <div class="form-group">
                    <label class="form-label" id="password-label">Senha *</label>
                    <input type="password" class="form-input" id="form-password">
                    <small id="password-hint" style="color: var(--text-muted);">Mín. 4 caracteres</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Estabelecimento</label>
                    <select class="form-select" id="form-establishment">
                        <option value="">-- Sem vínculo --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select class="form-select" id="form-role">
                        <option value="kj">KJ (Operador)</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-form')">Cancelar</button>
                    <button type="submit" class="btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="mobile-menu.js"></script>
    <script src="app.js"></script>
    <script>
        let kjs = [];
        let establishments = [];

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            await loadEstablishments();
            loadData();
        });

        async function loadEstablishments() {
            try {
                const data = await fetchAPI('establishments.php');
                establishments = data.data;

                const select = document.getElementById('form-establishment');
                select.innerHTML = '<option value="">-- Sem vínculo --</option>' +
                    establishments.map(e => `<option value="${e.id}">${escapeHtml(e.name)}</option>`).join('');
            } catch (e) { }
        }

        async function loadData() {
            try {
                const data = await fetchAPI('kjs.php');
                kjs = data.data;
                renderTable();
            } catch (e) {
                showError(e.message);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('table-body');

            if (kjs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty">Nenhum KJ cadastrado</td></tr>';
                return;
            }

            tbody.innerHTML = kjs.map(k => `
                <tr>
                    <td>
                        <strong>${escapeHtml(k.name)}</strong>
                        ${k.email ? `<br><small style="color: var(--text-muted);">${escapeHtml(k.email)}</small>` : ''}
                    </td>
                    <td class="hide-mobile"><code>${escapeHtml(k.username)}</code></td>
                    <td>${escapeHtml(k.establishment_name || '-')}</td>
                    <td class="hide-mobile"><span class="badge">${k.role}</span></td>
                    <td><span class="badge ${k.is_active ? 'badge-success' : 'badge-muted'}">${k.is_active ? 'Ativo' : 'Inativo'}</span></td>
                    <td style="white-space: nowrap;">
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="editItem(${k.id})" title="Editar">✏️</button>
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="sendPasswordReset(${k.id}, 'kj')" title="Enviar Reset de Senha">📧</button>
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="impersonate(${k.id}, 'kj')" title="Acessar como">🔑</button>
                        <button class="btn btn-danger" style="padding: 0.5rem;" onclick="deleteItem(${k.id})" title="Excluir">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }

        function openCreateModal() {
            document.getElementById('modal-title').textContent = 'Novo KJ';
            document.getElementById('form-kj').reset();
            document.getElementById('form-id').value = '';
            document.getElementById('form-password').required = true;
            document.getElementById('password-label').textContent = 'Senha *';
            document.getElementById('password-hint').textContent = 'Mín. 4 caracteres';
            document.getElementById('form-username').disabled = false;
            openModal('modal-form');
        }

        async function editItem(id) {
            try {
                const data = await fetchAPI(`kjs.php?id=${id}`);
                const k = data.data;

                document.getElementById('modal-title').textContent = 'Editar KJ';
                document.getElementById('form-id').value = k.id;
                document.getElementById('form-name').value = k.name;
                document.getElementById('form-username').value = k.username;
                document.getElementById('form-username').disabled = true;
                document.getElementById('form-email').value = k.email || '';
                document.getElementById('form-password').value = '';
                document.getElementById('form-password').required = false;
                document.getElementById('password-label').textContent = 'Nova Senha';
                document.getElementById('password-hint').textContent = 'Deixe vazio para manter';
                document.getElementById('form-establishment').value = k.establishment_id || '';
                document.getElementById('form-role').value = k.role;

                openModal('modal-form');
            } catch (e) {
                showError(e.message);
            }
        }

        document.getElementById('form-kj').addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('form-id').value;
            const payload = {
                name: document.getElementById('form-name').value,
                email: document.getElementById('form-email').value,
                establishment_id: document.getElementById('form-establishment').value || null,
                role: document.getElementById('form-role').value
            };

            const password = document.getElementById('form-password').value;
            if (password) payload.password = password;

            if (!id) {
                payload.username = document.getElementById('form-username').value;
            }

            try {
                if (id) {
                    await fetchAPI(`kjs.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) });
                    toastSuccess('KJ atualizado!');
                } else {
                    await fetchAPI('kjs.php', { method: 'POST', body: JSON.stringify(payload) });
                    toastSuccess('KJ criado!');
                }
                closeModal('modal-form');
                loadData();
            } catch (err) {
                showError(err.message);
            }
        });

        async function deleteItem(id) {
            const confirmed = await showDeleteConfirm('este KJ');
            if (!confirmed) return;

            try {
                await fetchAPI(`kjs.php?id=${id}`, { method: 'DELETE' });
                toastSuccess('KJ excluído!');
                loadData();
            } catch (err) {
                showError(err.message);
            }
        }

        // Enviar reset de senha
        async function sendPasswordReset(id, type) {
            const confirmed = await showConfirm(
                'Um email com link de reset será enviado para o usuário.',
                'Enviar Reset de Senha?',
                'Enviar',
                'Cancelar'
            );
            if (!confirmed) return;

            showLoading('Enviando...');

            try {
                const result = await fetchAPI('password_reset.php', {
                    method: 'POST',
                    body: JSON.stringify({ type, id })
                });
                
                hideLoading();
                
                if (result.data.reset_url) {
                    await Swal.fire({
                        title: 'Token Gerado!',
                        html: `
                            <p>Email não configurado. Copie o link abaixo:</p>
                            <input type="text" value="${result.data.reset_url}" 
                                   style="width: 100%; padding: 0.5rem; margin-top: 1rem; background: #1a1a2e; color: #fff; border: 1px solid #333; border-radius: 4px;"
                                   onclick="this.select()" readonly>
                        `,
                        icon: 'info',
                        confirmButtonColor: '#8b5cf6'
                    });
                } else {
                    showSuccess(result.message);
                }
            } catch (err) {
                hideLoading();
                showError(err.message);
            }
        }

        // Acessar como (impersonate)
        async function impersonate(id, type) {
            const typeName = type === 'establishment' ? 'estabelecimento' : 'KJ';
            const confirmed = await showConfirm(
                `Você será redirecionado para o painel do ${typeName}. Uma nova aba será aberta.`,
                `Acessar como ${typeName}?`,
                'Acessar',
                'Cancelar'
            );
            if (!confirmed) return;

            showLoading('Preparando acesso...');

            try {
                const result = await fetchAPI('impersonate.php', {
                    method: 'POST',
                    body: JSON.stringify({ type, id })
                });
                
                hideLoading();
                
                await Swal.fire({
                    title: 'Acesso Concedido!',
                    text: result.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
                
                window.open(result.data.redirect_url, '_blank');
                
            } catch (err) {
                hideLoading();
                showError(err.message);
            }
        }
    </script>
</body>

</html>
