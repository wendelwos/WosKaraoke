<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estabelecimentos - Super Admin Karaoke Show</title>
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
                <span class="logo-text">Karaoke Show</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-link"><span class="nav-icon">📊</span> Dashboard</a>
                <a href="establishments.php" class="nav-link active"><span class="nav-icon">🏢</span> Estabelecimentos</a>
                <a href="kjs.php" class="nav-link"><span class="nav-icon">🎤</span> KJs</a>
                <a href="settings.php" class="nav-link"><span class="nav-icon">⚙️</span> Configurações</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info"><span class="user-avatar">👤</span><span id="user-name">Admin</span></div>
                <button class="btn-logout" onclick="logout()">Sair</button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🏢 Estabelecimentos</h1>
                    <p class="subtitle">Gerenciar bares e casas de karaoke</p>
                </div>
                <button class="btn" onclick="openCreateModal()">+ Novo Estabelecimento</button>
            </header>

            <div class="content-card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th class="hide-mobile">Slug</th>
                                <th class="hide-mobile">KJs</th>
                                <th>Plano</th>
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
                <h2 class="modal-title" id="modal-title">Novo Estabelecimento</h2>
                <button class="modal-close" onclick="closeModal('modal-form')">&times;</button>
            </div>
            <form id="form-establishment">
                <input type="hidden" id="form-id">
                <div class="form-group">
                    <label class="form-label">Nome *</label>
                    <input type="text" class="form-input" id="form-name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Slug (URL amigável)</label>
                    <input type="text" class="form-input" id="form-slug" placeholder="Ex: bar-do-joao">
                </div>
                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <input type="text" class="form-input" id="form-address">
                </div>
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="text" class="form-input" id="form-phone">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="form-email">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Máx. KJs</label>
                        <input type="number" class="form-input" id="form-max-kjs" value="5" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Plano</label>
                        <select class="form-select" id="form-plan">
                            <option value="free">Free</option>
                            <option value="starter">Starter</option>
                            <option value="pro">Pro</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Senha de Acesso <span id="password-hint">(para login no painel)</span></label>
                    <input type="password" class="form-input" id="form-password" placeholder="Deixe em branco para manter">
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
        let establishments = [];

        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            loadData();
        });

        async function loadData() {
            try {
                const data = await fetchAPI('establishments.php');
                establishments = data.data;
                renderTable();
            } catch (e) {
                showError(e.message);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('table-body');

            if (establishments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty">Nenhum estabelecimento cadastrado</td></tr>';
                return;
            }

            tbody.innerHTML = establishments.map(e => `
                <tr>
                    <td>
                        <strong>${escapeHtml(e.name)}</strong>
                        ${e.email ? `<br><small style="color: var(--text-muted);">${escapeHtml(e.email)}</small>` : ''}
                    </td>
                    <td class="hide-mobile"><code>${escapeHtml(e.slug)}</code></td>
                    <td class="hide-mobile">${e.kj_count || 0}</td>
                    <td><span class="badge badge-${e.subscription_plan === 'pro' ? 'warning' : 'muted'}">${e.subscription_plan}</span></td>
                    <td><span class="badge ${e.is_active ? 'badge-success' : 'badge-muted'}">${e.is_active ? 'Ativo' : 'Inativo'}</span></td>
                    <td style="white-space: nowrap;">
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="editItem(${e.id})" title="Editar">✏️</button>
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="sendPasswordReset(${e.id}, 'establishment')" title="Enviar Reset de Senha">📧</button>
                        <button class="btn btn-secondary" style="padding: 0.5rem;" onclick="impersonate(${e.id}, 'establishment')" title="Acessar como">🔑</button>
                        <button class="btn btn-danger" style="padding: 0.5rem;" onclick="deleteItem(${e.id})" title="Excluir">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }

        function openCreateModal() {
            document.getElementById('modal-title').textContent = 'Novo Estabelecimento';
            document.getElementById('form-establishment').reset();
            document.getElementById('form-id').value = '';
            openModal('modal-form');
        }

        async function editItem(id) {
            try {
                const data = await fetchAPI(`establishments.php?id=${id}`);
                const e = data.data;

                document.getElementById('modal-title').textContent = 'Editar Estabelecimento';
                document.getElementById('form-id').value = e.id;
                document.getElementById('form-name').value = e.name;
                document.getElementById('form-slug').value = e.slug;
                document.getElementById('form-address').value = e.address || '';
                document.getElementById('form-phone').value = e.phone || '';
                document.getElementById('form-email').value = e.email || '';
                document.getElementById('form-max-kjs').value = e.max_kjs;
                document.getElementById('form-plan').value = e.subscription_plan;

                openModal('modal-form');
            } catch (e) {
                showError(e.message);
            }
        }

        document.getElementById('form-establishment').addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('form-id').value;
            const payload = {
                name: document.getElementById('form-name').value,
                slug: document.getElementById('form-slug').value,
                address: document.getElementById('form-address').value,
                phone: document.getElementById('form-phone').value,
                email: document.getElementById('form-email').value,
                max_kjs: parseInt(document.getElementById('form-max-kjs').value),
                subscription_plan: document.getElementById('form-plan').value,
                password: document.getElementById('form-password').value
            };

            try {
                if (id) {
                    await fetchAPI(`establishments.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) });
                    toastSuccess('Estabelecimento atualizado!');
                } else {
                    await fetchAPI('establishments.php', { method: 'POST', body: JSON.stringify(payload) });
                    toastSuccess('Estabelecimento criado!');
                }
                closeModal('modal-form');
                loadData();
            } catch (err) {
                showError(err.message);
            }
        });

        async function deleteItem(id) {
            const confirmed = await showDeleteConfirm('este estabelecimento');
            if (!confirmed) return;

            try {
                await fetchAPI(`establishments.php?id=${id}`, { method: 'DELETE' });
                toastSuccess('Estabelecimento excluído!');
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
                    // Email não configurado, mostra o link
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
                
                // Abrir em nova aba
                window.open(result.data.redirect_url, '_blank');
                
            } catch (err) {
                hideLoading();
                showError(err.message);
            }
        }
    </script>
</body>

</html>
