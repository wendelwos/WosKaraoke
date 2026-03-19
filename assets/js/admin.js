/**
 * WosKaraoke - Painel Admin
 * Gerenciamento de fila de karaokê
 */

// ===== ESTADO =====
const state = {
    admin: null,
    queue: null,
    event: null, // Configurações do evento
    refreshInterval: null
};

const API_BASE = './api/admin';
const REFRESH_MS = 5000; // Atualiza a cada 5 segundos

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
});

// ===== API =====
async function fetchAPI(endpoint, options = {}) {
    const response = await fetch(`${API_BASE}/${endpoint}`, {
        headers: { 'Content-Type': 'application/json' },
        ...options
    });
    return response.json();
}

// ===== AUTENTICAÇÃO =====
async function checkAuth() {
    try {
        const result = await fetchAPI('auth.php');
        if (result.success && result.logged) {
            state.admin = result.logged;
            state.event = result.event || { event_code: '1234', is_open: true };
            renderDashboard();
            startAutoRefresh();
        } else {
            renderLogin();
        }
    } catch (e) {
        renderLogin();
    }
}

async function doLogin() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    if (!username || !password) {
        showMessage('Preencha usuário e senha', 'error');
        return;
    }

    try {
        const result = await fetchAPI('auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });

        if (result.success) {
            state.admin = result.data;
            renderDashboard();
            startAutoRefresh();
        } else {
            showMessage(result.error || 'Erro no login', 'error');
        }
    } catch (e) {
        showMessage('Erro de conexão', 'error');
    }
}

async function doLogout() {
    await fetchAPI('auth.php?action=logout', { method: 'POST' });
    state.admin = null;
    stopAutoRefresh();
    renderLogin();
}

async function doRegister() {
    const username = document.getElementById('reg-username').value.trim();
    const password = document.getElementById('reg-password').value;
    const name = document.getElementById('reg-name').value.trim();

    if (!username || !password || !name) {
        showMessage('Preencha todos os campos', 'error');
        return;
    }

    try {
        const result = await fetchAPI('auth.php', {
            method: 'POST',
            body: JSON.stringify({ username, password, name })
        });

        if (result.success) {
            showMessage('Admin criado! Faça login.', 'success');
            renderLogin();
        } else {
            showMessage(result.error || 'Erro ao criar', 'error');
        }
    } catch (e) {
        showMessage('Erro de conexão', 'error');
    }
}

// ===== FILA =====
async function loadQueue() {
    try {
        const result = await fetchAPI('queue.php');
        if (result.success) {
            state.queue = result.data;
            renderQueue();
        }
    } catch (e) {
        console.error('Erro ao carregar fila:', e);
    }
}

async function nextSong() {
    try {
        await fetchAPI('queue.php?action=next', { method: 'POST' });
        await loadQueue();
    } catch (e) {
        showMessage('Erro ao avançar', 'error');
    }
}

async function skipSong() {
    if (!confirm('Pular esta pessoa? Ela irá para o final da fila.')) return;

    try {
        await fetchAPI('queue.php?action=skip', { method: 'POST' });
        await loadQueue();
    } catch (e) {
        showMessage('Erro ao pular', 'error');
    }
}

async function clearQueue() {
    if (!confirm('Limpar TODA a fila? Esta ação não pode ser desfeita.')) return;

    try {
        await fetchAPI('queue.php?action=clear', { method: 'POST' });
        await loadQueue();
        showMessage('Fila limpa!', 'success');
    } catch (e) {
        showMessage('Erro ao limpar', 'error');
    }
}

async function removeFromQueue(id) {
    try {
        await fetchAPI(`queue.php?id=${id}`, { method: 'DELETE' });
        await loadQueue();
    } catch (e) {
        showMessage('Erro ao remover', 'error');
    }
}

/**
 * Move item para nova posição na fila
 */
async function moveItem(queueId, direction) {
    const waiting = state.queue?.waiting || [];
    const currentIndex = waiting.findIndex(w => w.id === queueId);

    if (currentIndex === -1) return;

    let newPosition;
    if (direction === 'up' && currentIndex > 0) {
        newPosition = currentIndex - 1;
    } else if (direction === 'down' && currentIndex < waiting.length - 1) {
        newPosition = currentIndex + 1;
    } else {
        return; // Não pode mover
    }

    await reorderViaAPI(queueId, newPosition);
}

/**
 * Reordena via API
 */
async function reorderViaAPI(queueId, newPosition) {
    try {
        await fetchAPI('queue.php?action=reorder', {
            method: 'POST',
            body: JSON.stringify({
                queue_id: queueId,
                new_position: newPosition
            })
        });
        await loadQueue();
    } catch (e) {
        showMessage('Erro ao mover', 'error');
    }
}

/**
 * Inicia uma música específica imediatamente
 */
async function playNow(queueId) {
    try {
        const result = await fetchAPI('queue.php?action=play', {
            method: 'POST',
            body: JSON.stringify({ queue_id: queueId })
        });
        if (result.success) {
            showMessage(result.message || 'Música iniciada!', 'success');
        }
        await loadQueue();
    } catch (e) {
        showMessage('Erro ao iniciar música', 'error');
    }
}

// ===== CONTROLE DE EVENTO =====

/**
 * Carrega configurações do evento
 */
async function loadEventSettings() {
    try {
        const result = await fetchAPI('event.php');
        if (result.success) {
            state.event = result.data;
        }
    } catch (e) {
        console.error('Erro ao carregar evento:', e);
    }
}

/**
 * Carrega configurações completas do evento (admin)
 */
async function loadEventSettingsAdmin() {
    try {
        // Admin precisa da versão completa
        const pdo = await fetch(`${API_BASE}/event.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getAdmin' })
        });

        // Usa endpoint específico para admin
        const result = await fetchAPI('auth.php?include_event=true');
        if (result.event) {
            state.event = result.event;
        }
    } catch (e) {
        // Fallback: usa dados básicos
    }
}

/**
 * Toggle abrir/fechar evento
 */
async function toggleEvent() {
    try {
        const result = await fetchAPI('event.php?action=toggle', {
            method: 'POST'
        });
        if (result.success) {
            state.event.is_open = result.is_open;
            showMessage(result.message, 'success');
            updateEventUI();
        }
    } catch (e) {
        showMessage('Erro ao alterar status do evento', 'error');
    }
}

/**
 * Gera novo código de acesso
 */
async function generateNewCode() {
    if (!confirm('Gerar um novo código de acesso? O código atual será invalidado.')) {
        return;
    }

    try {
        const result = await fetchAPI('event.php?action=new_code', {
            method: 'POST'
        });
        if (result.success) {
            state.event.event_code = result.code;
            showMessage(result.message, 'success');
            updateEventUI();
        }
    } catch (e) {
        showMessage('Erro ao gerar código', 'error');
    }
}

/**
 * Atualiza código manualmente
 */
async function updateEventCode() {
    const newCode = document.getElementById('event-code-input')?.value.trim();

    if (!newCode || newCode.length < 4) {
        showMessage('Código deve ter pelo menos 4 caracteres', 'error');
        return;
    }

    try {
        const result = await fetchAPI('event.php?action=update', {
            method: 'POST',
            body: JSON.stringify({ event_code: newCode.toUpperCase() })
        });
        if (result.success) {
            state.event = result.data;
            showMessage('Código atualizado!', 'success');
            updateEventUI();
        }
    } catch (e) {
        showMessage('Erro ao atualizar código', 'error');
    }
}

/**
 * Atualiza UI do painel de evento
 */
function updateEventUI() {
    const codeDisplay = document.getElementById('event-code-display');
    const statusBtn = document.getElementById('event-status-btn');
    const statusIcon = document.getElementById('event-status-icon');

    if (codeDisplay && state.event) {
        codeDisplay.textContent = state.event.event_code || '----';
    }

    if (statusBtn && state.event) {
        const isOpen = state.event.is_open;
        statusBtn.className = `event-toggle-btn ${isOpen ? 'open' : 'closed'}`;
        statusBtn.innerHTML = `
            <span class="toggle-indicator">${isOpen ? '🟢' : '🔴'}</span>
            <span class="toggle-text">${isOpen ? 'Fila Aberta' : 'Fila Fechada'}</span>
        `;

        // Atualiza hint
        const hint = statusBtn.nextElementSibling;
        if (hint && hint.classList.contains('event-status-hint')) {
            hint.textContent = isOpen ? 'Clientes podem adicionar músicas' : 'Clientes não podem adicionar músicas';
        }
    }

    if (statusIcon) {
        statusIcon.textContent = state.event?.is_open ? '🟢' : '🔴';
    }
}

// ===== DRAG AND DROP =====
let draggedItem = null;

function handleDragStart(e) {
    draggedItem = e.target.closest('.queue-item');
    if (draggedItem) {
        draggedItem.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', draggedItem.dataset.id);
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';

    const target = e.target.closest('.queue-item');
    if (target && target !== draggedItem) {
        target.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    const target = e.target.closest('.queue-item');
    if (target) {
        target.classList.remove('drag-over');
    }
}

function handleDrop(e) {
    e.preventDefault();

    const target = e.target.closest('.queue-item');
    if (target) {
        target.classList.remove('drag-over');
    }

    if (!draggedItem || !target || draggedItem === target) return;

    const draggedId = parseInt(draggedItem.dataset.id);
    const waiting = state.queue?.waiting || [];
    const targetIndex = waiting.findIndex(w => w.id === parseInt(target.dataset.id));

    if (targetIndex !== -1) {
        reorderViaAPI(draggedId, targetIndex);
    }
}

function handleDragEnd(e) {
    if (draggedItem) {
        draggedItem.classList.remove('dragging');
    }
    document.querySelectorAll('.queue-item').forEach(item => {
        item.classList.remove('drag-over');
    });
    draggedItem = null;
}


// ===== AUTO REFRESH =====
function startAutoRefresh() {
    loadQueue();
    state.refreshInterval = setInterval(loadQueue, REFRESH_MS);
}

function stopAutoRefresh() {
    if (state.refreshInterval) {
        clearInterval(state.refreshInterval);
        state.refreshInterval = null;
    }
}

// ===== RENDERIZAÇÃO =====
function renderLogin() {
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <span class="logo-icon">🎤</span>
                    <h1>WosKaraoke Admin</h1>
                </div>
                
                <div id="login-form">
                    <input type="text" id="username" placeholder="Usuário" class="input" autocomplete="username">
                    <div class="password-wrapper">
                        <input type="password" id="password" placeholder="Senha" class="input" autocomplete="current-password"
                               onkeypress="if(event.key === 'Enter') doLogin()">
                        <button type="button" class="btn-eye" onclick="togglePasswordVisibility('password', this)">👁️</button>
                    </div>
                    <button class="btn btn-primary" onclick="doLogin()">Entrar</button>
                    <p class="login-link">
                        Primeiro acesso? <a href="#" onclick="renderRegister(); return false;">Criar admin</a>
                    </p>
                    <p class="login-link">
                        <a href="#" onclick="renderForgotPassword(); return false;">Esqueci minha senha</a>
                    </p>
                </div>
                
                <div id="message" class="message hidden"></div>
            </div>
        </div>
    `;

    // Verifica se tem token de reset na URL
    const params = new URLSearchParams(window.location.search);
    const resetToken = params.get('reset');
    if (resetToken) {
        renderResetPassword(resetToken);
    }
}

function renderRegister() {
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <span class="logo-icon">🎤</span>
                    <h1>Criar Administrador</h1>
                </div>
                
                <div id="register-form">
                    <input type="text" id="reg-name" placeholder="Seu nome" class="input">
                    <input type="text" id="reg-username" placeholder="Usuário para login" class="input">
                    <input type="password" id="reg-password" placeholder="Senha (mín. 4 caracteres)" class="input">
                    <button class="btn btn-primary" onclick="doRegister()">Criar Admin</button>
                    <p class="login-link">
                        <a href="#" onclick="renderLogin(); return false;">← Voltar ao login</a>
                    </p>
                </div>
                
                <div id="message" class="message hidden"></div>
            </div>
        </div>
    `;
}

function renderDashboard() {
    const app = document.getElementById('app');
    const event = state.event || {};
    const isOpen = event.is_open !== false;

    app.innerHTML = `
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">🎤</span>
                    <span>WosKaraoke Admin</span>
                    <span id="event-status-icon">${isOpen ? '🟢' : '🔴'}</span>
                </div>
                <div class="header-actions">
                    <span class="admin-name">${state.admin?.name || 'Admin'}</span>
                    <button class="btn btn-secondary btn-sm" onclick="doLogout()">Sair</button>
                </div>
            </div>
        </header>
        
        <!-- Painel de Código do Evento -->
        <div class="event-panel">
            <div class="event-panel-left">
                <div class="event-code-box">
                    <span class="event-code-label">CÓDIGO DO EVENTO</span>
                    <div class="event-code-value">
                        <span id="event-code-display">${event.event_code || '1234'}</span>
                    </div>
                    <button class="btn-refresh-code" onclick="generateNewCode()" title="Gerar novo código">
                        🔄 Novo Código
                    </button>
                </div>
            </div>
            <div class="event-panel-right">
                <button id="event-status-btn" class="event-toggle-btn ${isOpen ? 'open' : 'closed'}" onclick="toggleEvent()">
                    <span class="toggle-indicator">${isOpen ? '🟢' : '🔴'}</span>
                    <span class="toggle-text">${isOpen ? 'Fila Aberta' : 'Fila Fechada'}</span>
                </button>
                <span class="event-status-hint">${isOpen ? 'Clientes podem adicionar músicas' : 'Clientes não podem adicionar músicas'}</span>
            </div>
        </div>
        
        <main class="main">
            <div id="queue-container">
                <div class="loading">Carregando fila...</div>
            </div>
        </main>
        
        <div id="toast-container"></div>
    `;
}

function renderQueue() {
    const container = document.getElementById('queue-container');
    if (!container || !state.queue) return;

    const { current, waiting, stats } = state.queue;

    container.innerHTML = `
        <div class="stats-bar">
            <div class="stat">
                <span class="stat-value">${stats.waiting_count}</span>
                <span class="stat-label">Na fila</span>
            </div>
            <div class="stat">
                <span class="stat-value">${stats.unique_people}</span>
                <span class="stat-label">Pessoas</span>
            </div>
            <div class="stat">
                <span class="stat-value">${stats.done_today}</span>
                <span class="stat-label">Cantadas hoje</span>
            </div>
        </div>
        
        ${current ? `
            <div class="current-song">
                <div class="current-label">🎤 CANTANDO AGORA</div>
                <div class="current-info">
                    <div class="current-code">${current.song_code}</div>
                    <div class="current-details">
                        <div class="current-title">${escapeHtml(current.song_title)}</div>
                        <div class="current-artist">${escapeHtml(current.song_artist)}</div>
                        <div class="current-singer">👤 ${escapeHtml(current.profile_name)}</div>
                    </div>
                </div>
                <div class="current-actions">
                    <button class="btn btn-success btn-lg" onclick="nextSong()">
                        ▶️ Próxima
                    </button>
                    <button class="btn btn-warning" onclick="skipSong()">
                        ⏭️ Pular
                    </button>
                </div>
            </div>
        ` : `
            <div class="no-current">
                <div class="no-current-icon">🎤</div>
                <p>Nenhuma música tocando</p>
                ${waiting.length > 0 ? `
                    <button class="btn btn-success" onclick="nextSong()">Iniciar Primeira</button>
                ` : ''}
            </div>
        `}
        
        <div class="queue-section">
            <div class="queue-header">
                <h2>📋 Próximos (${waiting.length})</h2>
                ${waiting.length > 0 ? `
                    <button class="btn btn-danger btn-sm" onclick="clearQueue()">Limpar</button>
                ` : ''}
            </div>
            
            ${waiting.length === 0 ? `
                <div class="queue-empty">
                    <p>Fila vazia - aguardando pedidos dos clientes</p>
                </div>
            ` : `
                <div class="queue-list" id="queue-list">
                    ${waiting.map((item, index) => `
                        <div class="queue-item" 
                             data-id="${item.id}" 
                             draggable="true"
                             ondragstart="handleDragStart(event)"
                             ondragover="handleDragOver(event)"
                             ondragleave="handleDragLeave(event)"
                             ondrop="handleDrop(event)"
                             ondragend="handleDragEnd(event)">
                            <div class="drag-handle" title="Arraste para reordenar">☰</div>
                            <div class="queue-move-btns">
                                <button class="btn-move ${index === 0 ? 'disabled' : ''}" onclick="moveItem(${item.id}, 'up')" title="Mover para cima" ${index === 0 ? 'disabled' : ''}>▲</button>
                                <button class="btn-move ${index === waiting.length - 1 ? 'disabled' : ''}" onclick="moveItem(${item.id}, 'down')" title="Mover para baixo" ${index === waiting.length - 1 ? 'disabled' : ''}>▼</button>
                            </div>
                            <div class="queue-position">${index + 1}</div>
                            <div class="queue-song">
                                <div class="queue-title">${escapeHtml(item.song_title)}</div>
                                <div class="queue-artist">${escapeHtml(item.song_artist)}</div>
                            </div>
                            <div class="queue-meta">
                                <div class="queue-singer">👤 ${escapeHtml(item.profile_name)}</div>
                                <div class="queue-stats">
                                    ⏱️ ${item.wait_minutes || 0}min
                                    | 🎵 ${item.songs_sung_today || 0} cantadas
                                </div>
                            </div>
                            <div class="queue-actions-btns">
                                <button class="btn-play" onclick="playNow(${item.id})" title="Tocar agora">
                                    <svg viewBox="0 0 24 24" fill="white" width="16" height="16"><polygon points="5,3 19,12 5,21"/></svg>
                                </button>
                                <button class="btn-remove" onclick="removeFromQueue(${item.id})" title="Remover">✕</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `}
        </div>
    `;
}

// ===== UTILS =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function showMessage(text, type = 'info') {
    const msgEl = document.getElementById('message');
    if (msgEl) {
        msgEl.textContent = text;
        msgEl.className = `message ${type}`;
        msgEl.classList.remove('hidden');
        setTimeout(() => msgEl.classList.add('hidden'), 3000);
    }

    // Toast para dashboard
    const toast = document.getElementById('toast-container');
    if (toast) {
        const toastEl = document.createElement('div');
        toastEl.className = `toast ${type}`;
        toastEl.textContent = text;
        toast.appendChild(toastEl);
        setTimeout(() => toastEl.remove(), 3000);
    }
}

/**
 * Toggle visibilidade da senha
 */
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '👁️‍🗨️';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

// ===== RECUPERAÇÃO DE SENHA =====

function renderForgotPassword() {
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <span class="logo-icon">🔑</span>
                    <h1>Esqueci minha senha</h1>
                </div>
                
                <div id="forgot-form">
                    <p class="login-link" style="margin-bottom: 1rem;">
                        Digite seu e-mail cadastrado para receber um link de recuperação.
                    </p>
                    <input type="email" id="reset-email" placeholder="seu@email.com" class="input" 
                           onkeypress="if(event.key === 'Enter') requestPasswordReset()">
                    <button class="btn btn-primary" onclick="requestPasswordReset()">Enviar Link</button>
                    <p class="login-link">
                        <a href="#" onclick="renderLogin(); return false;">← Voltar ao login</a>
                    </p>
                </div>
                
                <div id="message" class="message hidden"></div>
            </div>
        </div>
    `;
}

function renderResetPassword(token) {
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <span class="logo-icon">🔐</span>
                    <h1>Redefinir Senha</h1>
                </div>
                
                <div id="reset-form">
                    <input type="hidden" id="reset-token" value="${token}">
                    <div class="password-wrapper">
                        <input type="password" id="new-password" placeholder="Nova senha" class="input">
                        <button type="button" class="btn-eye" onclick="togglePasswordVisibility('new-password', this)">👁️</button>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" id="confirm-password" placeholder="Confirmar senha" class="input"
                               onkeypress="if(event.key === 'Enter') doResetPassword()">
                        <button type="button" class="btn-eye" onclick="togglePasswordVisibility('confirm-password', this)">👁️</button>
                    </div>
                    <button class="btn btn-primary" onclick="doResetPassword()">Redefinir Senha</button>
                    <p class="login-link">
                        <a href="#" onclick="history.pushState({}, '', 'admin'); renderLogin(); return false;">← Voltar ao login</a>
                    </p>
                </div>
                
                <div id="message" class="message hidden"></div>
            </div>
        </div>
    `;

    // Verifica se token é válido
    verifyResetToken(token);
}

async function verifyResetToken(token) {
    try {
        const res = await fetch('./api/admin/password-reset.php?action=verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
        });
        const data = await res.json();

        if (!data.valid) {
            showMessage(data.error || 'Token inválido', 'error');
            setTimeout(() => {
                history.pushState({}, '', 'admin');
                renderLogin();
            }, 3000);
        }
    } catch (e) {
        showMessage('Erro ao verificar token', 'error');
    }
}

async function requestPasswordReset() {
    const email = document.getElementById('reset-email')?.value.trim();

    if (!email) {
        showMessage('Digite seu e-mail', 'error');
        return;
    }

    try {
        const res = await fetch('./api/admin/password-reset.php?action=request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await res.json();

        if (data.success) {
            showMessage('✅ Se o e-mail estiver cadastrado, você receberá um link em breve.', 'success');
            document.getElementById('forgot-form').innerHTML = `
                <p style="text-align: center; padding: 2rem;">
                    📧 Verifique sua caixa de entrada!
                </p>
                <p class="login-link">
                    <a href="#" onclick="renderLogin(); return false;">← Voltar ao login</a>
                </p>
            `;
        } else {
            showMessage(data.error || 'Erro ao enviar', 'error');
        }
    } catch (e) {
        showMessage('Erro de conexão', 'error');
    }
}

async function doResetPassword() {
    const token = document.getElementById('reset-token')?.value;
    const password = document.getElementById('new-password')?.value;
    const confirm = document.getElementById('confirm-password')?.value;

    if (!password || password.length < 4) {
        showMessage('Senha deve ter pelo menos 4 caracteres', 'error');
        return;
    }

    if (password !== confirm) {
        showMessage('As senhas não conferem', 'error');
        return;
    }

    try {
        const res = await fetch('./api/admin/password-reset.php?action=reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, password })
        });
        const data = await res.json();

        if (data.success) {
            showMessage('✅ Senha alterada com sucesso!', 'success');
            setTimeout(() => {
                history.pushState({}, '', 'admin');
                renderLogin();
            }, 2000);
        } else {
            showMessage(data.error || 'Erro ao redefinir', 'error');
        }
    } catch (e) {
        showMessage('Erro de conexão', 'error');
    }
}
