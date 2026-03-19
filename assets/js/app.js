/**
 * WosKaraoke - App Principal
 * Sistema de busca de músicas para karaokê
 */

// ============================================
// SERVICE WORKER & NOTIFICATIONS
// ============================================

/**
 * Registra o Service Worker para PWA e notificações
 */
async function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        try {
            const registration = await navigator.serviceWorker.register('/WosKaraoke/sw.js');
            console.log('[App] Service Worker registrado:', registration.scope);

            // Verifica atualizações
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        showToast('Nova versão disponível! Recarregue a página.', 'info');
                    }
                });
            });

            return registration;
        } catch (error) {
            console.error('[App] Erro ao registrar Service Worker:', error);
        }
    }
    return null;
}

/**
 * Solicita permissão para notificações
 */
async function requestNotificationPermission() {
    if (!('Notification' in window)) {
        console.log('[App] Notificações não suportadas');
        return false;
    }

    if (Notification.permission === 'granted') {
        return true;
    }

    if (Notification.permission !== 'denied') {
        const permission = await Notification.requestPermission();
        return permission === 'granted';
    }

    return false;
}

/**
 * Envia notificação local
 */
function sendLocalNotification(title, body, data = {}) {
    if (Notification.permission !== 'granted') return;

    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({
            type: 'SHOW_NOTIFICATION',
            title,
            body,
            data
        });
    } else {
        new Notification(title, { body, icon: '/WosKaraoke/assets/images/icon-192.png' });
    }
}

/**
 * Verifica posição na fila e notifica se próximo
 */
function checkQueuePositionAndNotify(queue, profileId) {
    const myPosition = queue.findIndex(q => q.profile_id == profileId);

    if (myPosition === -1) return;

    const position = myPosition + 1;

    if (position === 1) {
        sendLocalNotification('🎤 Você é o próximo!', 'Prepare-se para cantar!', { url: '/WosKaraoke/?tab=queue' });
    } else if (position <= 3) {
        sendLocalNotification(`🎤 Faltam ${position - 1} para sua vez!`, 'Prepare-se para cantar!', { url: '/WosKaraoke/?tab=queue' });
    }
}

// Registra SW na inicialização
registerServiceWorker();

// ===== ESTADO DA APLICAÇÃO =====
const state = {
    profile: null,
    favorites: [],
    queue: [], // Fila de músicas para cantar
    songs: [],
    songsMap: {}, // Mapa de código -> música para favoritos
    searchQuery: '',
    currentTab: 'search',
    isLoading: false,
    searchTimeout: null,
    queueRefreshInterval: null, // Timer para atualização automática da fila
    eventCodeVerified: false, // Se o código do evento foi verificado
    pendingSong: null, // Música pendente para adicionar após verificar código
    notificationsEnabled: false // Se notificações estão habilitadas
};

// ===== CONSTANTES =====
const API_BASE = './api';
const STORAGE_KEY = 'woskaraoke_profile';
const EVENT_CODE_KEY = 'woskaraoke_event_code'; // Armazena código verificado
const EVENT_ID_KEY = 'woskaraoke_event_id'; // Armazena ID do evento atual
const TABLE_NUMBER_KEY = 'woskaraoke_table_number'; // Armazena número da mesa
const DEBOUNCE_MS = 300;
const QUEUE_REFRESH_MS = 5000; // Atualiza fila a cada 5 segundos

// ===== PUSHER REAL-TIME =====
const PUSHER_KEY = '7ee5ce528cd2ebad5010';
const PUSHER_CLUSTER = 'sa1';
let pusherChannel = null;

// ===== AUDIO PLAYER (Preview) =====
let audioPlayer = null;
let currentPreviewCode = null;

/**
 * Toca preview de 30 segundos de uma música via Deezer API
 */
async function playPreview(songCode) {
    const btn = document.getElementById(`preview-btn-${songCode}`);
    const song = state.songsMap[songCode];

    if (!song) {
        showToast('Erro: Música não encontrada', 'error');
        return;
    }

    // Se já está tocando essa música, pausar
    if (currentPreviewCode === songCode && audioPlayer && !audioPlayer.paused) {
        audioPlayer.pause();
        updatePreviewButton(btn, false);
        currentPreviewCode = null;
        return;
    }

    // Parar áudio anterior se existir
    if (audioPlayer) {
        audioPlayer.pause();
        // Resetar botão anterior
        if (currentPreviewCode) {
            const oldBtn = document.getElementById(`preview-btn-${currentPreviewCode}`);
            if (oldBtn) updatePreviewButton(oldBtn, false);
        }
    }

    // Mostrar loading no botão
    if (btn) {
        btn.innerHTML = '<div class="spinner-small"></div>';
        btn.disabled = true;
    }

    try {
        // Buscar preview da API
        const response = await fetch(`${API_BASE}/preview.php?artist=${encodeURIComponent(song.artist || '')}&title=${encodeURIComponent(song.title || '')}`);
        const data = await response.json();

        if (!data.success || !data.preview?.preview_url) {
            throw new Error(data.error || 'Preview não disponível');
        }

        // Criar ou reutilizar player
        if (!audioPlayer) {
            audioPlayer = new Audio();
            audioPlayer.addEventListener('ended', () => {
                if (currentPreviewCode) {
                    const endBtn = document.getElementById(`preview-btn-${currentPreviewCode}`);
                    if (endBtn) updatePreviewButton(endBtn, false);
                }
                currentPreviewCode = null;
            });
            audioPlayer.addEventListener('error', () => {
                showToast('Erro ao reproduzir áudio', 'error');
                if (currentPreviewCode) {
                    const errBtn = document.getElementById(`preview-btn-${currentPreviewCode}`);
                    if (errBtn) updatePreviewButton(errBtn, false);
                }
                currentPreviewCode = null;
            });
        }

        // Tocar preview
        audioPlayer.src = data.preview.preview_url;
        audioPlayer.play();
        currentPreviewCode = songCode;

        if (btn) {
            btn.disabled = false;
            updatePreviewButton(btn, true);
        }

        showToast(`🎵 Tocando: ${data.preview.title} - ${data.preview.artist}`, 'success');

    } catch (error) {
        console.error('Erro ao buscar preview:', error);
        showToast(error.message || 'Erro ao carregar preview', 'error');

        if (btn) {
            btn.disabled = false;
            updatePreviewButton(btn, false);
        }
    }
}

/**
 * Atualiza ícone do botão de preview (play/pause)
 */
function updatePreviewButton(btn, isPlaying) {
    if (!btn) return;

    if (isPlaying) {
        btn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        `;
        btn.classList.add('playing');
    } else {
        btn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        `;
        btn.classList.remove('playing');
    }
}

/**
 * Inicializa conexão Pusher para atualizações em tempo real
 */
function initPusher() {
    if (typeof Pusher === 'undefined') {
        console.log('Pusher não disponível, usando polling');
        return;
    }

    const eventId = sessionStorage.getItem(EVENT_ID_KEY);
    if (!eventId) return;

    try {
        const pusher = new Pusher(PUSHER_KEY, {
            cluster: PUSHER_CLUSTER
        });

        // Desinscrever canal anterior se existir
        if (pusherChannel) {
            pusherChannel.unsubscribe();
        }

        // Inscrever no canal do evento atual
        pusherChannel = pusher.subscribe(`event-${eventId}`);

        // Listener: Fila atualizada
        pusherChannel.bind('queue_updated', (data) => {
            console.log('📡 Fila atualizada via Pusher:', data);
            loadQueue(); // Recarrega a fila
        });

        // Listener: Música atual mudou
        pusherChannel.bind('current_song_changed', (data) => {
            console.log('📡 Música atual mudou:', data);
            loadQueue();
            if (data.song) {
                showToast(`🎤 Agora: ${data.song.song_title}`, 'info');
            }
        });

        // Listener: Anúncio do KJ
        pusherChannel.bind('announcement', (data) => {
            console.log('📡 Anúncio:', data);
            showToast(`📢 ${data.message}`, data.type || 'info');
        });

        // Listener: Atualização de batalha
        pusherChannel.bind('battle_update', (data) => {
            console.log('📡 Batalha atualizada:', data);
            if (state.currentTab === 'battle') {
                renderBattle();
            }
        });

        console.log(`📡 Conectado ao Pusher - Canal: event-${eventId}`);

    } catch (e) {
        console.error('Erro ao inicializar Pusher:', e);
    }
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verificar parâmetro de mesa na URL
    const urlParams = new URLSearchParams(window.location.search);
    const tableParam = urlParams.get('table');
    if (tableParam) {
        sessionStorage.setItem(TABLE_NUMBER_KEY, tableParam);
        // Opcional: Auto-join logic could go here if table implies a public event
    }

    // Verificar perfil existente
    const savedToken = localStorage.getItem(STORAGE_KEY);

    if (savedToken) {
        try {
            const profile = await fetchProfile(savedToken);
            if (profile) {
                state.profile = profile;
                await loadFavorites();
                renderApp();
                return;
            }
        } catch (e) {
            console.error('Erro ao carregar perfil:', e);
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    // Mostrar modal de login
    // Mostrar modal de login
    showLoginModal();

    // GOOGLE AUTH DESATIVADO - Reativar quando configurar credenciais
    // Load Google Identity Services
    // const script = document.createElement('script');
    // script.src = 'https://accounts.google.com/gsi/client';
    // script.async = true;
    // script.defer = true;
    // document.body.appendChild(script);
});

// GOOGLE AUTH DESATIVADO - Callback global do Google
// window.handleGoogleCredential = async (response) => {
//     try {
//         const result = await fetchAPI('profiles.php?action=google_login', {
//             method: 'POST',
//             body: JSON.stringify({ credential: response.credential })
//         });
//
//         state.profile = result.data;
//         localStorage.setItem(STORAGE_KEY, result.data.token);
//
//         renderApp();
//         showToast(`Bem-vindo, ${result.data.name}! 🇬`, 'success');
//
//         // Fecha modal se estiver aberto
//         const modal = document.querySelector('.modal-backdrop.active');
//         if (modal) modal.remove();
//
//     } catch (e) {
//         showToast(e.message || 'Erro no login Google', 'error');
//     }
// };

// ===== API CALLS =====
async function fetchAPI(endpoint, options = {}) {
    const response = await fetch(`${API_BASE}/${endpoint}`, {
        headers: {
            'Content-Type': 'application/json',
        },
        ...options
    });

    const data = await response.json();

    if (!data.success) {
        throw new Error(data.error || 'Erro desconhecido');
    }

    return data;
}

async function fetchProfile(token) {
    const result = await fetchAPI(`profiles.php?token=${encodeURIComponent(token)}`);
    return result.data;
}

async function createProfile(name) {
    const result = await fetchAPI('profiles.php', {
        method: 'POST',
        body: JSON.stringify({ name })
    });
    return result.data;
}

async function searchSongs(query = '', limit = 50, offset = 0) {
    const params = new URLSearchParams({ search: query, limit, offset });
    const result = await fetchAPI(`songs.php?${params}`);
    return result;
}

async function loadFavorites() {
    if (!state.profile) return;

    try {
        const result = await fetchAPI(`favorites.php?token=${encodeURIComponent(state.profile.token)}`);
        state.favorites = result.data || [];
    } catch (e) {
        console.error('Erro ao carregar favoritos:', e);
        state.favorites = [];
    }
}

async function addFavorite(song) {
    if (!state.profile) return;

    await fetchAPI('favorites.php', {
        method: 'POST',
        body: JSON.stringify({
            token: state.profile.token,
            song_code: song.code,
            song_title: song.title,
            song_artist: song.artist
        })
    });

    await loadFavorites();
    updateFavoritesCount();
    showToast('Adicionado aos favoritos!', 'success');
}

async function removeFavorite(songCode) {
    if (!state.profile) return;

    await fetchAPI(`favorites.php?token=${encodeURIComponent(state.profile.token)}&song_code=${encodeURIComponent(songCode)}`, {
        method: 'DELETE'
    });

    await loadFavorites();
    updateFavoritesCount();
    showToast('Removido dos favoritos', 'success');
}

/**
 * Toggle favorito - adiciona ou remove baseado no estado atual
 */
async function toggleFavorite(songCode) {
    if (!state.profile) {
        showToast('Faça login para adicionar favoritos', 'error');
        return;
    }

    const isFavorite = state.favorites.some(f => f.song_code === songCode);

    try {
        if (isFavorite) {
            await removeFavorite(songCode);
        } else {
            // Buscar música do mapa
            const song = state.songsMap[songCode];
            if (song) {
                await addFavorite(song);
            } else {
                showToast('Música não encontrada', 'error');
            }
        }
    } catch (e) {
        console.error('Erro ao atualizar favorito:', e);
        showToast('Erro ao atualizar favorito', 'error');
    }
}

/**
 * Toggle fila - adiciona ou remove música da fila global
 */
async function toggleQueue(songCode) {
    if (!state.profile) {
        showToast('Faça login para entrar na fila', 'error');
        return;
    }

    const queueIndex = state.queue.findIndex(q => q.code === songCode);

    try {
        if (queueIndex !== -1) {
            // Remover da fila global - não precisa de código
            const queueId = state.queue[queueIndex].id;
            await fetchAPI(`admin/queue.php?id=${queueId}&token=${encodeURIComponent(state.profile.token)}`, {
                method: 'DELETE'
            });
            state.queue.splice(queueIndex, 1);
            showToast('Removido da fila', 'success');
        } else {
            // Adicionar à fila - precisa verificar código do evento primeiro
            const song = state.songsMap[songCode];
            if (!song) return;

            // Verifica se já tem código verificado
            if (!state.eventCodeVerified) {
                // Salva música pendente e mostra modal de código
                state.pendingSong = song;
                showEventCodeModal();
                return;
            }

            // Código já verificado, adiciona diretamente
            await addSongToQueue(song);
        }
    } catch (e) {
        // Se o erro for sobre evento fechado, mostra modal de código
        if (e.message && e.message.includes('fechado')) {
            state.eventCodeVerified = false;
            sessionStorage.removeItem(EVENT_CODE_KEY);
            showToast('O evento está fechado no momento', 'error');
        } else {
            showToast(e.message || 'Erro ao processar', 'error');
        }
    }

    // Re-renderizar botões
    updateQueueCount();
    if (state.currentTab === 'search' && state.songs.length > 0) {
        const favoriteCodes = new Set(state.favorites.map(f => f.song_code));
        const container = document.getElementById('results-container');
        const resultsList = container?.querySelector('.results-list');
        if (resultsList) {
            resultsList.innerHTML = state.songs.map(song =>
                renderSongCard(song, favoriteCodes.has(song.code))
            ).join('');
        }
    } else if (state.currentTab === 'queue') {
        renderTabContent();
    } else if (state.currentTab === 'favorites') {
        renderTabContent();
    }
}


/**
 * Renderiza modal para Dedicatória e Código (Se necessário)
 * @param {boolean} onlyEnter - Se true, mostra apenas o campo de código para entrar no evento
 */
function showEventCodeModal(onlyEnter = false) {
    // Remove modal anterior
    document.getElementById('event-code-modal')?.remove();

    const needsCode = !state.eventCodeVerified;

    // Se já está verificado e clicou apenas para entrar, avisa e retorna
    if (onlyEnter && state.eventCodeVerified) {
        showToast('✅ Você já está no evento!', 'success');
        return;
    }

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop active';
    modal.id = 'event-code-modal';

    const title = onlyEnter ? 'Entrar no Evento' : 'Pedir Música';
    const subtitle = onlyEnter ? 'Digite o código para participar' : 'Configure seu pedido';
    const btnText = onlyEnter ? 'Entrar' : (needsCode ? 'Entrar / Pedir' : 'Pedir Música');

    let html = `
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon">${onlyEnter ? '🔐' : '🎤'}</div>
                <h2 class="modal-title">${title}</h2>
                <p class="modal-subtitle">${subtitle}</p>
            </div>
            <div class="modal-body">
    `;

    // Sempre mostra código se for onlyEnter ou se precisar de código
    if (onlyEnter || needsCode) {
        html += `
                <div class="form-group">
                    <label>Código do Evento</label>
                    <input 
                        type="text" 
                        class="input modal-input event-code-input" 
                        id="event-code-input" 
                        placeholder="0000"
                        maxlength="10"
                        autocomplete="off"
                        style="text-align: center; font-size: 1.5rem; letter-spacing: 0.2em; text-transform: uppercase;"
                    >
                    <p class="hint" style="text-align: center;">Peça ao DJ</p>
                </div>
        `;
    }

    // Dedicatória só aparece se NÃO for apenas para entrar
    if (!onlyEnter) {
        html += `
                    <div class="form-group">
                        <label>Dedicatória / Recado (Opcional)</label>
                        <input 
                            type="text" 
                            class="input" 
                            id="song-message-input" 
                            placeholder="Ex: Para a aniversariante Ana!"
                            maxlength="100"
                            autocomplete="off"
                        >
                    </div>
        `;
    }

    html += `
                <div class="form-group" style="display:none;"> <!-- Futuro: Mesa -->
                     <input type="hidden" id="table-number-input" value="">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEventCodeModal()">Cancelar</button>
                <button class="btn btn-primary" onclick="confirmSongRequest(${needsCode}, ${onlyEnter})">
                    ${btnText}
                </button>
            </div>
        </div>
    `;

    modal.innerHTML = html;
    document.body.appendChild(modal);

    // Focus
    setTimeout(() => {
        if (onlyEnter || needsCode) {
            document.getElementById('event-code-input')?.focus();
        } else {
            document.getElementById('song-message-input')?.focus();
        }
    }, 100);
}

function closeEventCodeModal() {
    const modal = document.getElementById('event-code-modal');
    if (modal) {
        modal.remove();
    }
}

async function confirmSongRequest(needsCode, onlyEnter = false) {
    const messageInput = document.getElementById('song-message-input');
    const message = messageInput?.value.trim() || '';

    // Se for só para entrar ou se precisa de código
    if (onlyEnter || needsCode) {
        const codeInput = document.getElementById('event-code-input');
        const code = codeInput?.value.trim().toUpperCase();

        if (!code) {
            showToast('Digite o código do evento', 'error');
            return;
        }

        try {
            const result = await fetchAPI('admin/event.php?action=verify', {
                method: 'POST',
                body: JSON.stringify({ code })
            });

            if (result.valid) {
                state.eventCodeVerified = true;
                sessionStorage.setItem(EVENT_CODE_KEY, code);
                if (result.event_id) {
                    sessionStorage.setItem(EVENT_ID_KEY, result.event_id);
                }
                showToast(`✅ Bem-vindo ao ${result.event_name || 'Evento'}!`, 'success');

                // Se só estava entrando, fecha. Se tinha música pendente, adiciona.
                if (!onlyEnter && state.pendingSong) {
                    await addSongToQueue(state.pendingSong, message);
                    state.pendingSong = null;
                }

                // Force reload queue for this event
                renderApp();

                closeEventCodeModal();
            } else {
                showToast(result.error || 'Código inválido', 'error');
            }
        } catch (e) {
            showToast('Erro ao verificar código', 'error');
        }
    } else {
        // Já tem código, só manda
        if (state.pendingSong) {
            await addSongToQueue(state.pendingSong, message);
            state.pendingSong = null;
        }
        closeEventCodeModal();
    }
}

/**
 * Adiciona música à fila
 */
async function addSongToQueue(song, message = '') {
    const eventId = sessionStorage.getItem(EVENT_ID_KEY) || 1;
    const tableNumber = sessionStorage.getItem(TABLE_NUMBER_KEY) || null;

    const result = await fetchAPI('admin/queue.php', {
        method: 'POST',
        body: JSON.stringify({
            token: state.profile.token,
            song_code: song.code,
            song_title: song.title,
            song_artist: song.artist,
            message: message,
            event_id: eventId,
            table_number: tableNumber
        })
    });

    if (result.success) {
        state.queue.push({
            id: result.data.queue_id,
            code: song.code,
            title: song.title,
            artist: song.artist,
            message: message
        });
        showToast(`"${song.title}" adicionada à fila! 🎤`, 'success');
        updateQueueCount();

        // Atualiza UI
        if (state.currentTab === 'search') {
            const container = document.getElementById('results-container');
            // Força update visual (simplificado)
            performSearch(state.searchQuery);
        }
    }
}

/**
 * Modal para digitar código do evento
 */


/**
 * Atualiza contador da fila
 */
function updateQueueCount() {
    const badge = document.getElementById('queue-count');
    if (badge) {
        badge.textContent = state.queue.length;
    }
}

/**
 * Remove próxima música da fila (já cantou)
 */
function nextInQueue() {
    if (state.queue.length === 0) {
        showToast('A fila está vazia', 'error');
        return;
    }

    const song = state.queue.shift();
    showToast(`"${song.title}" encerrada! 🎉`, 'success');
    updateQueueCount();
    renderTabContent();
}

/**
 * Limpa toda a fila
 */
function clearQueue() {
    if (state.queue.length === 0) return;

    if (confirm(`Limpar todas as ${state.queue.length} músicas da fila?`)) {
        state.queue = [];
        updateQueueCount();
        renderTabContent();
        showToast('Fila limpa!', 'success');
    }
}

// ===== RENDERIZAÇÃO =====
function renderApp() {
    const app = document.getElementById('app');
    app.innerHTML = `
        <header class="header">
            <div class="container header-content">
                <a href="#" class="logo" onclick="switchTab('search'); return false;">
                    <span class="logo-icon">🎤</span>
                    <span>WosKaraoke</span>
                </a>
                <div class="header-actions">
                    <button class="btn-icon" onclick="showProfileInfo()" title="Perfil">
                        <div class="avatar" style="background-color: ${state.profile.avatar_color}; width: 40px; height: 40px; font-size: 0.875rem;">
                            ${state.profile.initials}
                        </div>
                    </button>
                </div>
            </div>
        </header>
        
        <main class="container">
            <nav class="nav-tabs">
                <button class="nav-tab ${state.currentTab === 'search' ? 'active' : ''}" onclick="switchTab('search')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <span class="nav-text">Buscar</span>
                </button>
                <button class="nav-tab ${state.currentTab === 'queue' ? 'active' : ''}" onclick="switchTab('queue')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18V5l12-2v13"></path>
                        <circle cx="6" cy="18" r="3"></circle>
                        <circle cx="18" cy="16" r="3"></circle>
                    </svg>
                    <span class="nav-text">Fila</span>
                    <span class="badge badge-queue" id="queue-count">${state.queue.length}</span>
                </button>
                <button class="nav-tab ${state.currentTab === 'favorites' ? 'active' : ''}" onclick="switchTab('favorites')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="nav-text">Favoritos</span>
                    <span class="badge" id="favorites-count">${state.favorites.length}</span>
                </button>
                <button class="nav-tab ${state.currentTab === 'history' ? 'active' : ''}" onclick="switchTab('history')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span class="nav-text">Histórico</span>
                </button>
                <button class="nav-tab ${state.currentTab === 'ranking' ? 'active' : ''}" onclick="switchTab('ranking')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"></path>
                    </svg>
                    <span class="nav-text">🏆 Ranking</span>
                </button>
                <button class="nav-tab ${state.currentTab === 'battle' ? 'active' : ''}" onclick="switchTab('battle')">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.5 17.5L3 6V3h3l11.5 11.5"></path>
                        <path d="M13 19l6-6"></path>
                        <path d="M16 16l4 4"></path>
                        <path d="M19 21l2-2"></path>
                        <path d="M14.5 6.5L21 3v3L9.5 17.5"></path>
                        <path d="M5 14l4 4"></path>
                        <path d="M7 17l-4 4"></path>
                    </svg>
                    <span class="nav-text">Duelo</span>
                </button>
                <button class="nav-tab btn-secondary" onclick="showEventsListModal()">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span class="nav-text">Eventos</span>
                </button>
                <button class="nav-tab btn-success" onclick="showEventCodeModal(true)">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <span class="nav-text">Entrar</span>
                </button>
            </nav>
            
            <div id="tab-content"></div>
        </main>
        
        <div class="toast-container" id="toast-container"></div>
        
        <!-- Floating Action Buttons -->
        <div class="fab-container" id="fab-container">
            <button class="fab fab-main" onclick="scrollToTop()" title="Voltar ao topo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 19V5M5 12l7-7 7 7"/>
                </svg>
            </button>
            <button class="fab fab-action" onclick="toggleFloatingSearch()" title="Buscar" id="fab-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
            </button>
            <button class="fab fab-action" onclick="switchTab('queue'); scrollToTop();" title="Fila">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 18V5l12-2v13"></path>
                    <circle cx="6" cy="18" r="3"></circle>
                    <circle cx="18" cy="16" r="3"></circle>
                </svg>
            </button>
            <button class="fab fab-action fab-success" onclick="showEventCodeModal(true)" title="Entrar no Evento">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
            </button>
        </div>
        
        <!-- Floating Search Box -->
        <div class="floating-search" id="floating-search">
            <div class="floating-search-box">
                <input type="text" id="floating-search-input" class="input" placeholder="Buscar música ou artista..." 
                       oninput="debouncedFloatingSearch(this.value)"
                       onkeydown="if(event.key === 'Enter') selectFloatingResult()" autocomplete="off">
                <button class="floating-search-btn" onclick="selectFloatingResult()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                </button>
                <button class="floating-search-close" onclick="closeFloatingSearch()">✕</button>
            </div>
        </div>
    `;

    renderTabContent();

    // Inicializar listener de scroll para FABs
    initFabScrollListener();
}

// ===== FLOATING ACTION BUTTONS =====
function initFabScrollListener() {
    const fabContainer = document.getElementById('fab-container');
    if (!fabContainer) return;

    let lastScrollY = 0;
    let ticking = false;

    const handleScroll = () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                const scrollY = window.scrollY || window.pageYOffset;

                // Mostrar FABs apenas se scrollou mais de 200px
                if (scrollY > 200) {
                    fabContainer.classList.add('visible');
                } else {
                    fabContainer.classList.remove('visible');
                }

                lastScrollY = scrollY;
                ticking = false;
            });
            ticking = true;
        }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ===== FLOATING SEARCH =====
function toggleFloatingSearch() {
    const searchBox = document.getElementById('floating-search');
    const fabSearch = document.getElementById('fab-search');

    if (searchBox.classList.contains('active')) {
        closeFloatingSearch();
    } else {
        searchBox.classList.add('active');
        fabSearch.classList.add('active');
        setTimeout(() => {
            document.getElementById('floating-search-input')?.focus();
        }, 100);
    }
}

function closeFloatingSearch() {
    const searchBox = document.getElementById('floating-search');
    const fabSearch = document.getElementById('fab-search');

    searchBox?.classList.remove('active');
    fabSearch?.classList.remove('active');

    // Limpar input
    const input = document.getElementById('floating-search-input');
    if (input) input.value = '';
}

// Debounce para busca ao vivo na lista principal
let floatingSearchTimeout = null;
function debouncedFloatingSearch(value) {
    clearTimeout(floatingSearchTimeout);

    if (!value || value.trim().length < 2) {
        return;
    }

    // Debounce de 300ms - depois busca na lista principal
    floatingSearchTimeout = setTimeout(() => {
        // Garantir que está na aba de busca
        if (state.currentTab !== 'search') {
            state.currentTab = 'search';
            renderApp();
        }

        // Atualizar input principal e executar busca
        const mainSearch = document.getElementById('search-input');
        if (mainSearch) {
            mainSearch.value = value.trim();
        }

        // Executar busca na lista principal
        performSearch(value.trim());
    }, 300);
}

function selectFloatingResult() {
    // Quando pressiona Enter, fecha a busca flutuante
    const input = document.getElementById('floating-search-input');
    if (input && input.value.trim().length >= 2) {
        closeFloatingSearch();
    }
}

function handleFloatingSearch(value) {
    // Esta função agora só é chamada quando pressiona Enter
    if (!value || value.trim().length < 2) {
        showToast('Digite pelo menos 2 caracteres', 'error');
        return;
    }

    const searchTerm = value.trim();

    // Fechar busca flutuante
    closeFloatingSearch();

    // Mudar para aba de busca SEM ir ao topo
    state.currentTab = 'search';
    state.searchQuery = searchTerm;

    // Renderizar app (mantém posição)
    renderApp();

    // Executar busca
    setTimeout(() => {
        const mainSearch = document.getElementById('search-input');
        if (mainSearch) {
            mainSearch.value = searchTerm;
        }
        performSearch(searchTerm);
    }, 100);
}

function renderTabContent() {
    const container = document.getElementById('tab-content');

    if (state.currentTab === 'search') {
        container.innerHTML = `
            <section class="search-section slide-up">
                <h1 class="search-title">Encontre sua música</h1>
                <p class="search-subtitle">Busque por código, cantor ou trecho da música</p>
                
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input 
                        type="search" 
                        class="input" 
                        id="search-input" 
                        placeholder="Digite código, cantor ou música..."
                        value="${state.searchQuery}"
                        oninput="handleSearch(this.value)"
                        autocomplete="off"
                    >
                </div>
            </section>
            
            <div id="results-container"></div>
        `;

        // Focus no input
        setTimeout(() => {
            document.getElementById('search-input')?.focus();
        }, 100);

        // Carregar músicas (com ou sem busca)
        performSearch(state.searchQuery);
    } else if (state.currentTab === 'queue') {
        renderQueue(container);
    } else if (state.currentTab === 'history') {
        renderHistory(container);
    } else if (state.currentTab === 'ranking') {
        renderRanking(container);
    } else if (state.currentTab === 'battle') {
        renderBattle(container);
    } else {
        renderFavorites(container);
    }
}

/**
 * Renderiza a fila de músicas (busca do servidor)
 */
async function renderQueue(container) {
    // Mostrar loading
    container.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Carregando fila...</p>
        </div>
    `;

    try {
        // Busca fila do servidor (com filtro de evento)
        const eventId = sessionStorage.getItem(EVENT_ID_KEY) || 1;
        const result = await fetchAPI(`admin/queue.php?event_id=${eventId}`);

        if (!result.success) {
            throw new Error(result.error || 'Erro ao carregar fila');
        }

        const { current, waiting, stats } = result.data;

        // Atualiza state.queue com os IDs para sincronização
        state.queue = waiting.map(w => ({
            id: w.id,
            code: w.song_code,
            title: w.song_title,
            artist: w.song_artist,
            profile_id: w.profile_id,
            profile_name: w.profile_name
        }));

        // Atualiza badge
        updateQueueCount();

        // Minhas músicas na fila (do usuário logado)
        const myProfileId = state.profile?.id;
        const myQueue = waiting.filter(w => w.profile_id == myProfileId);

        container.innerHTML = `
            <div class="queue-header slide-up">
                <h2 class="queue-title">🎤 Fila de Karaokê</h2>
                <p class="queue-subtitle">${stats.waiting_count} na fila | ${stats.unique_people} pessoas | ${stats.done_today} cantadas hoje</p>
            </div>
            
            ${current ? `
                <div class="queue-current slide-up">
                    <div class="queue-current-label">🎤 Cantando agora:</div>
                    <div class="queue-current-song">
                        <div class="song-code">${current.song_code}</div>
                        <div class="song-info">
                            <div class="song-title">${escapeHtml(current.song_title || 'Sem título')}</div>
                            <div class="song-artist">${escapeHtml(current.song_artist || 'Artista desconhecido')}</div>
                            <div class="queue-singer">
                                ${current.level_icon ? `<span title="${current.level_title}">${current.level_icon}</span> ` : ''}
                                👤 ${escapeHtml(current.profile_name)}
                                ${current.table_number ? `<span class="table-badge">Mesa ${escapeHtml(current.table_number)}</span>` : ''}
                            </div>
                        </div>
                        <button class="btn btn-lyrics" onclick="showLyricsModal('${escapeHtml(current.song_artist)}', '${escapeHtml(current.song_title)}')" title="Ver Letra">
                            📜 Letra
                        </button>
                    </div>
                </div>
            ` : `
                <div class="queue-empty-current slide-up">
                    <p>Nenhuma música tocando</p>
                </div>
            `}
            
            ${myQueue.length > 0 ? `
                <div class="my-queue slide-up">
                    <div class="my-queue-label">📋 Minhas músicas na fila (arraste para reordenar):</div>
                    ${myQueue.map((item, idx) => {
            const position = waiting.findIndex(w => w.id === item.id) + 1;
            const globalIndex = waiting.findIndex(w => w.id === item.id);
            return `
                            <div class="my-queue-item" 
                                 data-id="${item.id}"
                                 draggable="true"
                                 ondragstart="handleClientDragStart(event)"
                                 ondragover="handleClientDragOver(event)"
                                 ondragleave="handleClientDragLeave(event)"
                                 ondrop="handleClientDrop(event)"
                                 ondragend="handleClientDragEnd(event)">
                                <span class="drag-handle-small">☰</span>
                                <span class="my-queue-position">#${position}</span>
                                <span class="my-queue-title">${escapeHtml(item.song_title)}</span>
                                <div class="my-queue-btns">
                                    <button class="btn-move-sm ${globalIndex === 0 ? 'disabled' : ''}" onclick="moveMyQueue(${item.id}, 'up')" ${globalIndex === 0 ? 'disabled' : ''}>▲</button>
                                    <button class="btn-move-sm ${globalIndex === waiting.length - 1 ? 'disabled' : ''}" onclick="moveMyQueue(${item.id}, 'down')" ${globalIndex === waiting.length - 1 ? 'disabled' : ''}>▼</button>
                                    <button class="btn-icon btn-remove-small" onclick="removeFromGlobalQueue(${item.id})" title="Cancelar">✕</button>
                                </div>
                            </div>
                        `;
        }).join('')}
                </div>
            ` : ''}
            
            <div class="queue-full-list slide-up">
                <div class="queue-next-label">Próximos:</div>
                ${waiting.length === 0 ? `
                    <div class="queue-empty">
                        <p>Fila vazia - adicione músicas clicando no 🎤</p>
                    </div>
                ` : `
                    <div class="results-list" id="queue-list">
                        ${waiting.slice(0, 10).map((item, index) => `
                            <div class="card song-card slide-up queue-item ${item.profile_id == myProfileId ? 'my-song' : ''}">
                                <div class="queue-number">${index + 1}</div>
                                <div class="song-info">
                                    <div class="song-title">${escapeHtml(item.song_title || 'Sem título')}</div>
                                    <div class="song-artist">${escapeHtml(item.song_artist || 'Artista desconhecido')}</div>
                                    <div class="queue-singer-small">
                                        ${item.level_icon ? `<span title="${item.level_title}">${item.level_icon}</span> ` : ''}
                                        👤 ${escapeHtml(item.profile_name)}
                                        ${item.table_number ? `<span class="table-badge-sm">Mesa ${escapeHtml(item.table_number)}</span>` : ''}
                                    </div>
                                </div>
                                ${item.profile_id == myProfileId ? `
                                    <button class="btn-icon" onclick="removeFromGlobalQueue(${item.id})" title="Cancelar">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                ` : ''}
                            </div>
                        `).join('')}
                        ${waiting.length > 10 ? `<p class="queue-more">+ ${waiting.length - 10} mais na fila</p>` : ''}
                    </div>
                `}
            </div>
        `;

    } catch (e) {
        console.error('Erro ao carregar fila:', e);
        container.innerHTML = `
            <div class="results-empty slide-up">
                <div class="results-empty-icon">❌</div>
                <h3>Erro ao carregar fila</h3>
                <p>${e.message}</p>
                <button class="btn btn-primary" onclick="renderTabContent()">Tentar novamente</button>
            </div>
        `;
    }
}

/**
 * Remove música da fila global
 */
async function removeFromGlobalQueue(queueId) {
    try {
        await fetchAPI(`admin/queue.php?id=${queueId}&token=${encodeURIComponent(state.profile.token)}`, {
            method: 'DELETE'
        });
        showToast('Removido da fila', 'success');

        // Atualiza a lista local
        state.queue = state.queue.filter(q => q.id !== queueId);
        updateQueueCount();

        // Re-renderizar
        if (state.currentTab === 'queue') {
            renderTabContent();
        }
    } catch (e) {
        showToast('Erro ao remover', 'error');
    }
}

/**
 * Reordena própria música na fila
 */
async function reorderMyQueue(queueId, newPosition) {
    try {
        await fetchAPI('admin/queue.php?action=reorder', {
            method: 'POST',
            body: JSON.stringify({
                queue_id: queueId,
                new_position: newPosition,
                token: state.profile.token
            })
        });
        if (state.currentTab === 'queue') {
            renderTabContent();
        }
    } catch (e) {
        showToast('Erro ao mover', 'error');
    }
}

/**
 * Move minha música para cima ou baixo
 */
async function moveMyQueue(queueId, direction) {
    // Busca fila atualizada
    const result = await fetchAPI('admin/queue.php');
    if (!result.success) return;

    const waiting = result.data.waiting;
    const myProfileId = state.profile?.id;

    // Filtra apenas minhas músicas
    const myQueue = waiting.filter(w => w.profile_id == myProfileId);
    const currentIndex = myQueue.findIndex(w => w.id === queueId);

    if (currentIndex === -1) return;

    // Determina nova posição global
    const globalIndex = waiting.findIndex(w => w.id === queueId);
    let newPosition;

    if (direction === 'up' && globalIndex > 0) {
        newPosition = globalIndex - 1;
    } else if (direction === 'down' && globalIndex < waiting.length - 1) {
        newPosition = globalIndex + 1;
    } else {
        return;
    }

    await reorderMyQueue(queueId, newPosition);
}

// ===== DRAG AND DROP (Cliente) =====
let clientDraggedItem = null;

function handleClientDragStart(e) {
    clientDraggedItem = e.target.closest('.my-queue-item, .queue-item.my-song');
    if (clientDraggedItem) {
        clientDraggedItem.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', clientDraggedItem.dataset.id);
    }
}

function handleClientDragOver(e) {
    e.preventDefault();
    const target = e.target.closest('.my-queue-item, .queue-item.my-song');
    if (target && target !== clientDraggedItem) {
        target.classList.add('drag-over');
    }
}

function handleClientDragLeave(e) {
    const target = e.target.closest('.my-queue-item, .queue-item.my-song');
    if (target) {
        target.classList.remove('drag-over');
    }
}

async function handleClientDrop(e) {
    e.preventDefault();
    const target = e.target.closest('.my-queue-item, .queue-item.my-song');
    if (target) {
        target.classList.remove('drag-over');
    }

    if (!clientDraggedItem || !target || clientDraggedItem === target) return;

    const draggedId = parseInt(clientDraggedItem.dataset.id);
    const targetId = parseInt(target.dataset.id);

    // Busca posição do target na fila global
    const result = await fetchAPI('admin/queue.php');
    if (result.success) {
        const targetIndex = result.data.waiting.findIndex(w => w.id === targetId);
        if (targetIndex !== -1) {
            await reorderMyQueue(draggedId, targetIndex);
        }
    }
}

function handleClientDragEnd(e) {
    if (clientDraggedItem) {
        clientDraggedItem.classList.remove('dragging');
    }
    document.querySelectorAll('.my-queue-item, .queue-item.my-song').forEach(item => {
        item.classList.remove('drag-over');
    });
    clientDraggedItem = null;
}


function renderFavorites(container) {
    if (state.favorites.length === 0) {
        container.innerHTML = `
            <div class="results-empty slide-up">
                <div class="results-empty-icon">💔</div>
                <h3>Nenhum favorito ainda</h3>
                <p>Busque músicas e clique no coração para adicionar!</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="profile-header slide-up">
            <div class="avatar avatar-lg" style="background-color: ${state.profile.avatar_color}">
                ${state.profile.initials}
            </div>
            <h2 class="profile-name">${state.profile.name}</h2>
            <p class="profile-stats">${state.favorites.length} músicas favoritas</p>
        </div>
        
        <div class="results-list" id="favorites-list">
            ${state.favorites.map(fav => renderSongCard({
        code: fav.song_code,
        title: fav.song_title,
        artist: fav.song_artist
    }, true)).join('')}
        </div>
    `;
}

function renderSongCard(song, isFavorite = false, showQueueButton = true) {
    const favoriteClass = isFavorite ? 'active' : '';

    // Armazenar música no mapa para referência posterior
    state.songsMap[song.code] = song;

    // Verificar se está na fila
    const queueIndex = state.queue.findIndex(q => q.code === song.code);
    const isInQueue = queueIndex !== -1;
    const queueClass = isInQueue ? 'active' : '';
    const queuePosition = isInQueue ? queueIndex + 1 : null;

    const lyricsHtml = song.lyrics
        ? `<div class="song-lyrics">🎵 ${escapeHtml(song.lyrics)}</div>`
        : '';

    const queueButtonHtml = showQueueButton ? `
        <button 
            class="btn-icon btn-sing ${queueClass}" 
            data-song-code="${escapeHtml(song.code)}"
            onclick="event.stopPropagation(); toggleQueue('${escapeHtml(song.code)}')"
            title="${isInQueue ? 'Remover da fila (#' + queuePosition + ')' : 'Adicionar à fila para cantar'}">
            ${isInQueue ? `<span class="queue-position">${queuePosition}</span>` : `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
            </svg>`}
        </button>
    ` : '';

    // Botão de preview de áudio
    const previewButtonHtml = `
        <button 
            class="btn-icon btn-preview" 
            id="preview-btn-${escapeHtml(song.code)}"
            data-song-code="${escapeHtml(song.code)}"
            data-artist="${escapeHtml(song.artist || '')}"
            data-title="${escapeHtml(song.title || '')}"
            onclick="event.stopPropagation(); playPreview('${escapeHtml(song.code)}')"
            title="Ouvir prévia de 30 segundos">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
    `;

    return `
        <div class="card song-card slide-up" onclick="showSongDetails('${escapeHtml(song.code)}')" style="cursor: pointer;">
            <div class="song-code">${song.code}</div>
            <div class="song-info">
                <div class="song-title">${escapeHtml(song.title || 'Sem título')}</div>
                <div class="song-artist">${escapeHtml(song.artist || 'Artista desconhecido')}</div>
                ${lyricsHtml}
            </div>
            <div class="song-actions" onclick="event.stopPropagation();">
                ${previewButtonHtml}
                ${queueButtonHtml}
                <button 
                    class="btn-icon ${favoriteClass}" 
                    data-song-code="${escapeHtml(song.code)}" 
                    data-is-favorite="${isFavorite}"
                    onclick="event.stopPropagation(); toggleFavorite('${escapeHtml(song.code)}')"
                    title="${isFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}">
                    <svg xmlns="http://www.w3.org/2000/svg" ${isFavorite ? 'fill="currentColor"' : 'fill="none"'} viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </button>
            </div>
        </div>
    `;
}

function renderResults(data, meta) {
    const container = document.getElementById('results-container');

    if (!data || data.length === 0) {
        if (meta.search) {
            container.innerHTML = `
                <div class="results-empty">
                    <div class="results-empty-icon">🔍</div>
                    <h3>Nenhuma música encontrada</h3>
                    <p>Tente buscar por outro termo</p>
                </div>
            `;
        } else {
            container.innerHTML = '';
        }
        return;
    }

    // Verificar quais são favoritos
    const favoriteCodes = new Set(state.favorites.map(f => f.song_code));

    container.innerHTML = `
        <div class="results-header">
            <span>${meta.total} músicas encontradas</span>
        </div>
        <div class="results-list">
            ${data.map(song => renderSongCard(song, favoriteCodes.has(song.code))).join('')}
        </div>
        ${meta.hasMore ? `
            <div style="text-align: center; margin-top: 1rem;">
                <button class="btn btn-secondary" onclick="loadMore()">
                    Carregar mais
                </button>
            </div>
        ` : ''}
    `;
}

// ===== HANDLERS =====
function handleSearch(value) {
    state.searchQuery = value;

    // Debounce
    clearTimeout(state.searchTimeout);
    state.searchTimeout = setTimeout(() => {
        performSearch(value);
    }, DEBOUNCE_MS);
}

async function performSearch(query) {
    const container = document.getElementById('results-container');

    container.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <span>Carregando...</span>
        </div>
    `;

    try {
        const result = await searchSongs(query);
        state.songs = result.data;
        renderResults(result.data, result.meta);
    } catch (e) {
        console.error('Erro na busca:', e);
        container.innerHTML = `
            <div class="results-empty">
                <div class="results-empty-icon">⚠️</div>
                <h3>Erro ao buscar</h3>
                <p>${e.message}</p>
            </div>
        `;
    }
}

async function loadMore() {
    const currentOffset = state.songs.length;
    try {
        const result = await searchSongs(state.searchQuery, 50, currentOffset);
        state.songs = [...state.songs, ...result.data];

        // Re-renderizar com todos os resultados
        renderResults(state.songs, {
            ...result.meta,
            total: result.meta.total,
            hasMore: (currentOffset + result.data.length) < result.meta.total
        });
    } catch (e) {
        showToast('Erro ao carregar mais', 'error');
    }
}

function switchTab(tab) {
    const previousTab = state.currentTab;
    state.currentTab = tab;

    // Atualizar classes dos tabs
    document.querySelectorAll('.nav-tab').forEach(el => {
        el.classList.toggle('active', el.textContent.toLowerCase().includes(tab));
    });

    // Controlar auto-refresh da fila
    if (tab === 'queue') {
        startQueueRefresh();
    } else if (previousTab === 'queue') {
        stopQueueRefresh();
    }

    renderTabContent();
}

/**
 * Inicia atualização automática da fila
 */
function startQueueRefresh() {
    stopQueueRefresh(); // Para timer anterior se existir

    // Atualiza a cada QUEUE_REFRESH_MS
    state.queueRefreshInterval = setInterval(async () => {
        if (state.currentTab === 'queue') {
            await refreshQueueSilently();
        }
    }, QUEUE_REFRESH_MS);
}

/**
 * Para atualização automática da fila
 */
function stopQueueRefresh() {
    if (state.queueRefreshInterval) {
        clearInterval(state.queueRefreshInterval);
        state.queueRefreshInterval = null;
    }
}

/**
 * Atualiza a fila silenciosamente (sem loading e sem flicker)
 */
async function refreshQueueSilently() {
    try {
        const result = await fetchAPI('admin/queue.php');
        if (!result.success) return;

        const { current, waiting, stats } = result.data;

        // Cria uma "assinatura" da fila atual para comparar
        const currentSignature = JSON.stringify(state.queue.map(q => q.id + ':' + q.code));
        const newSignature = JSON.stringify(waiting.map(w => w.id + ':' + w.song_code));

        // Só atualiza se houver mudanças
        if (currentSignature === newSignature) {
            return; // Nada mudou, não re-renderiza
        }

        // Salva posição do scroll antes de atualizar
        const scrollPosition = window.scrollY;

        // Atualiza state.queue
        state.queue = waiting.map(w => ({
            id: w.id,
            code: w.song_code,
            title: w.song_title,
            artist: w.song_artist,
            profile_id: w.profile_id,
            profile_name: w.profile_name
        }));

        // Atualiza badge
        updateQueueCount();

        // Re-renderiza a fila se ainda estiver na aba
        if (state.currentTab === 'queue') {
            const container = document.getElementById('tab-content');
            if (container) {
                await renderQueueWithoutLoading(container, current, waiting, stats);
            }
        }

        // Restaura posição do scroll
        requestAnimationFrame(() => {
            window.scrollTo(0, scrollPosition);
        });

    } catch (e) {
        console.log('Erro ao atualizar fila:', e);
    }
}

/**
 * Renderiza fila sem mostrar loading (para atualizações silenciosas)
 */
async function renderQueueWithoutLoading(container, current, waiting, stats) {
    const myProfileId = state.profile?.id;
    const myQueue = waiting.filter(w => w.profile_id == myProfileId);

    container.innerHTML = `
        <div class="queue-header">
            <h2 class="queue-title">🎤 Fila de Karaokê</h2>
            <p class="queue-subtitle">${stats.waiting_count} na fila | ${stats.unique_people} pessoas | ${stats.done_today} cantadas hoje</p>
        </div>
        
        ${current ? `
            <div class="queue-current">
                <div class="queue-current-label">🎤 Cantando agora:</div>
                <div class="queue-current-song">
                    <div class="song-code">${current.song_code}</div>
                    <div class="song-info">
                        <div class="song-title">${escapeHtml(current.song_title || 'Sem título')}</div>
                        <div class="song-artist">${escapeHtml(current.song_artist || 'Artista desconhecido')}</div>
                        <div class="queue-singer">👤 ${escapeHtml(current.profile_name)}</div>
                    </div>
                </div>
            </div>
        ` : `
            <div class="queue-empty-current">
                <p>Nenhuma música tocando</p>
            </div>
        `}
        
        ${myQueue.length > 0 ? `
            <div class="my-queue">
                <div class="my-queue-label">📋 Minhas músicas na fila (arraste para reordenar):</div>
                ${myQueue.map((item, idx) => {
        const position = waiting.findIndex(w => w.id === item.id) + 1;
        const globalIndex = waiting.findIndex(w => w.id === item.id);
        return `
                        <div class="my-queue-item" 
                             data-id="${item.id}"
                             draggable="true"
                             ondragstart="handleClientDragStart(event)"
                             ondragover="handleClientDragOver(event)"
                             ondragleave="handleClientDragLeave(event)"
                             ondrop="handleClientDrop(event)"
                             ondragend="handleClientDragEnd(event)">
                            <span class="drag-handle-small">☰</span>
                            <span class="my-queue-position">#${position}</span>
                            <span class="my-queue-title">${escapeHtml(item.song_title)}</span>
                            <div class="my-queue-btns">
                                <button class="btn-move-sm ${globalIndex === 0 ? 'disabled' : ''}" onclick="moveMyQueue(${item.id}, 'up')" ${globalIndex === 0 ? 'disabled' : ''}>▲</button>
                                <button class="btn-move-sm ${globalIndex === waiting.length - 1 ? 'disabled' : ''}" onclick="moveMyQueue(${item.id}, 'down')" ${globalIndex === waiting.length - 1 ? 'disabled' : ''}>▼</button>
                                <button class="btn-icon btn-remove-small" onclick="removeFromGlobalQueue(${item.id})" title="Cancelar">✕</button>
                            </div>
                        </div>
                    `;
    }).join('')}
            </div>
        ` : ''}
        
        <div class="queue-full-list">
            <div class="queue-next-label">Próximos:</div>
            ${waiting.length === 0 ? `
                <div class="queue-empty">
                    <p>Fila vazia - adicione músicas clicando no 🎤</p>
                </div>
            ` : `
                <div class="results-list" id="queue-list">
                    ${waiting.slice(0, 10).map((item, index) => `
                        <div class="card song-card queue-item ${item.profile_id == myProfileId ? 'my-song' : ''}">
                            <div class="queue-number">${index + 1}</div>
                            <div class="song-info">
                                <div class="song-title">${escapeHtml(item.song_title || 'Sem título')}</div>
                                <div class="song-artist">${escapeHtml(item.song_artist || 'Artista desconhecido')}</div>
                                <div class="queue-singer-small">👤 ${escapeHtml(item.profile_name)}</div>
                            </div>
                            ${item.profile_id == myProfileId ? `
                                <button class="btn-icon" onclick="removeFromGlobalQueue(${item.id})" title="Cancelar">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            ` : ''}
                        </div>
                    `).join('')}
                    ${waiting.length > 10 ? `<p class="queue-more">+ ${waiting.length - 10} mais na fila</p>` : ''}
                </div>
            `}
        </div>
    `;
}

function updateFavoritesCount() {
    const badge = document.getElementById('favorites-count');
    if (badge) {
        badge.textContent = state.favorites.length;
    }

    // Re-renderizar se estiver na aba de busca (para atualizar ícones de coração)
    if (state.currentTab === 'search' && state.songs.length > 0) {
        const favoriteCodes = new Set(state.favorites.map(f => f.song_code));
        const container = document.getElementById('results-container');
        const resultsList = container.querySelector('.results-list');
        if (resultsList) {
            resultsList.innerHTML = state.songs.map(song =>
                renderSongCard(song, favoriteCodes.has(song.code))
            ).join('');
        }
    }
}

// ===== MODAL =====
function showLoginModal() {
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="modal-backdrop active">
            <div class="modal modal-login">
                <div class="modal-header">
                    <div class="modal-icon">🎤</div>
                    <h2 class="modal-title">Bem-vindo ao WosKaraoke!</h2>
                </div>
                
                <!-- Tabs de Login -->
                <div class="login-tabs">
                    <button class="login-tab active" onclick="switchLoginTab('quick')">Acesso Rápido</button>
                    <button class="login-tab" onclick="switchLoginTab('account')">Tenho Conta</button>
                </div>
                
                <!-- Acesso Rápido -->
                <div id="login-quick" class="login-form">
                    <p class="modal-subtitle">Digite seu nome para começar</p>
                    <input 
                        type="text" 
                        class="input modal-input" 
                        id="name-input" 
                        placeholder="Seu nome"
                        maxlength="50"
                        autofocus
                        onkeypress="if(event.key === 'Enter') handleQuickLogin()"
                    >
                    <button class="btn btn-primary" onclick="handleQuickLogin()">
                        Entrar Rápido
                    </button>
                    <p class="login-hint">⚠️ Acesso temporário. Para manter seus favoritos, crie uma conta depois.</p>
                </div>
                
                <!-- Login com Conta -->
                <div id="login-account" class="login-form" style="display: none;">
                    
                    <!-- GOOGLE AUTH DESATIVADO
                    <div id="google-btn" style="height: 40px; margin-bottom: 1.5rem;"></div>
                    <div style="text-align: center; margin-bottom: 1rem; color: var(--text-muted); font-size: 0.8rem;">OU</div>
                    -->

                    <p class="modal-subtitle">Entre com usuário e senha</p>
                    <input 
                        type="text" 
                        class="input modal-input" 
                        id="account-name" 
                        placeholder="Nome de usuário"
                        maxlength="50"
                        onkeypress="if(event.key === 'Enter') document.getElementById('account-password').focus()"
                    >
                    <input 
                        type="password" 
                        class="input modal-input" 
                        id="account-password" 
                        placeholder="Senha"
                        onkeypress="if(event.key === 'Enter') handleAccountLogin()"
                    >
                    <button class="btn btn-primary" onclick="handleAccountLogin()">
                        Entrar
                    </button>
                </div>
            </div>
        </div>
    `;

    // Focus
    setTimeout(() => {
        document.getElementById('name-input')?.focus();
        // GOOGLE AUTH DESATIVADO
        // Render Google Button
        // if (window.google) {
        //     renderGoogleButton();
        // } else {
        //     setTimeout(() => { if (window.google) renderGoogleButton() }, 1000);
        // }
    }, 300);
}

function switchLoginTab(tab) {
    document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.login-form').forEach(f => f.style.display = 'none');

    if (tab === 'quick') {
        document.querySelector('.login-tab:first-child').classList.add('active');
        document.getElementById('login-quick').style.display = 'block';
        document.getElementById('name-input')?.focus();
    } else {
        document.querySelector('.login-tab:last-child').classList.add('active');
        document.getElementById('login-account').style.display = 'block';
        document.getElementById('account-name')?.focus();
    }

    // GOOGLE AUTH DESATIVADO
    // Re-render Google Button if switching tabs
    // if (tab === 'account' && window.google) {
    //     renderGoogleButton();
    // }
}

// GOOGLE AUTH DESATIVADO - Função para renderizar botão do Google
// function renderGoogleButton() {
//     if (!document.getElementById('google-btn')) return;
//
//     google.accounts.id.initialize({
//         client_id: '1059330834808-1ms7j5htj214t2bqko16lcq9g4u559nt.apps.googleusercontent.com',
//         callback: handleGoogleCredential
//     });
//     google.accounts.id.renderButton(
//         document.getElementById("google-btn"),
//         { theme: "outline", size: "large", width: "100%", text: "signin_with" }
//     );
// }

async function handleQuickLogin() {
    const input = document.getElementById('name-input');
    const name = input.value.trim();

    if (!name) {
        input.classList.add('error');
        showToast('Por favor, digite seu nome', 'error');
        return;
    }

    try {
        const profile = await createProfile(name);
        state.profile = profile;
        localStorage.setItem(STORAGE_KEY, profile.token);

        renderApp();
        showToast(`Olá, ${profile.name}! 👋`, 'success');
    } catch (e) {
        showToast('Erro ao criar perfil: ' + e.message, 'error');
    }
}

async function handleAccountLogin() {
    const nameInput = document.getElementById('account-name');
    const passInput = document.getElementById('account-password');
    const name = nameInput.value.trim();
    const password = passInput.value;

    if (!name || !password) {
        showToast('Digite nome e senha', 'error');
        return;
    }

    try {
        const result = await fetchAPI('profiles.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ name, password })
        });

        state.profile = result.data;
        localStorage.setItem(STORAGE_KEY, result.data.token);

        renderApp();
        showToast(`Bem-vindo de volta, ${result.data.name}! 👋`, 'success');
    } catch (e) {
        showToast(e.message || 'Erro no login', 'error');
    }
}

// Compatibilidade - função antiga
async function handleLogin() {
    await handleQuickLogin();
}

function showProfileInfo() {
    showProfileModal();
}

/**
 * Modal de perfil com opção de definir senha
 */
function showProfileModal() {
    const profile = state.profile;
    const hasPassword = profile.has_password;

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop active';
    modal.id = 'profile-modal';
    modal.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <div class="avatar" style="background-color: ${profile.avatar_color}; width: 60px; height: 60px; font-size: 1.5rem; margin: 0 auto 1rem;">
                    ${profile.initials}
                </div>
                <h2 class="modal-title">${escapeHtml(profile.name)}</h2>
                <p class="modal-subtitle">${profile.favorites_count || 0} favoritos</p>
            </div>
            
            <div class="modal-body">
                <div class="profile-status ${hasPassword ? 'has-account' : 'no-account'}">
                    ${hasPassword
            ? '✅ Conta fixa - seus dados estão seguros!'
            : '⚠️ Acesso temporário - crie uma senha para manter seus favoritos'}
                </div>
                
                ${!hasPassword ? `
                    <div class="set-password-form" id="set-password-form">
                        <input type="password" id="new-password" class="input" placeholder="Criar senha (mín. 4 caracteres)" minlength="4">
                        <input type="password" id="confirm-password" class="input" placeholder="Confirmar senha">
                        <button class="btn btn-success" onclick="handleSetPassword()">
                            🔒 Criar Conta Fixa
                        </button>
                    </div>
                ` : `
                    <div class="change-password-form" id="change-password-form">
                        <details>
                            <summary>Alterar senha</summary>
                            <input type="password" id="current-password" class="input" placeholder="Senha atual">
                            <input type="password" id="new-password" class="input" placeholder="Nova senha">
                            <button class="btn btn-secondary" onclick="handleChangePassword()">Alterar</button>
                        </details>
                    </div>
                `}
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeProfileModal()">Fechar</button>
                <button class="btn btn-danger" onclick="handleLogout()">Sair</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

function closeProfileModal() {
    document.getElementById('profile-modal')?.remove();
}

async function handleSetPassword() {
    const password = document.getElementById('new-password').value;
    const confirm = document.getElementById('confirm-password').value;

    if (password.length < 4) {
        showToast('Senha deve ter pelo menos 4 caracteres', 'error');
        return;
    }

    if (password !== confirm) {
        showToast('Senhas não conferem', 'error');
        return;
    }

    try {
        await fetchAPI('profiles.php?action=set_password', {
            method: 'POST',
            body: JSON.stringify({
                token: state.profile.token,
                password: password
            })
        });

        state.profile.has_password = true;
        closeProfileModal();
        showToast('🎉 Conta criada! Agora seus dados estão seguros.', 'success');
    } catch (e) {
        showToast(e.message || 'Erro ao criar senha', 'error');
    }
}

async function handleChangePassword() {
    const current = document.getElementById('current-password').value;
    const newPass = document.getElementById('new-password').value;

    if (newPass.length < 4) {
        showToast('Nova senha deve ter pelo menos 4 caracteres', 'error');
        return;
    }

    try {
        await fetchAPI('profiles.php?action=set_password', {
            method: 'POST',
            body: JSON.stringify({
                token: state.profile.token,
                current_password: current,
                password: newPass
            })
        });

        closeProfileModal();
        showToast('Senha alterada!', 'success');
    } catch (e) {
        showToast(e.message || 'Erro ao alterar senha', 'error');
    }
}

function handleLogout() {
    localStorage.removeItem(STORAGE_KEY);
    state.profile = null;
    state.favorites = [];
    state.queue = [];
    closeProfileModal();
    showLoginModal();
}


// ===== TOAST =====
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${type === 'success' ? '✓' : '⚠'}</span>
        <span class="toast-message">${message}</span>
    `;

    container.appendChild(toast);

    // Remover após 3 segundos
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== UTILITÁRIOS =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===== LYRICS MODAL =====
async function showLyricsModal(artist, title) {
    // Remove modal anterior
    document.getElementById('lyrics-modal')?.remove();

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop active';
    modal.id = 'lyrics-modal';
    modal.innerHTML = `
        <div class="modal modal-lyrics">
            <div class="modal-header">
                <h2 class="modal-title">📜 ${escapeHtml(title)}</h2>
                <p class="modal-subtitle">${escapeHtml(artist)}</p>
            </div>
            <div class="modal-body lyrics-body">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Buscando letra...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeLyricsModal()">Fechar</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    try {
        const result = await fetchAPI(`lyrics.php?artist=${encodeURIComponent(artist)}&title=${encodeURIComponent(title)}`);

        const body = modal.querySelector('.lyrics-body');

        if (result.found) {
            body.innerHTML = `
                <div class="lyrics-content">
                    <pre class="lyrics-text">${escapeHtml(result.data.lyrics)}</pre>
                </div>
            `;
        } else {
            body.innerHTML = `
                <div class="lyrics-not-found">
                    <div class="empty-icon">🔍</div>
                    <p>${result.message || 'Letra não encontrada'}</p>
                    <a href="https://www.google.com/search?q=${encodeURIComponent(artist + ' ' + title + ' letra')}" 
                       target="_blank" class="btn btn-link">
                        Buscar no Google
                    </a>
                </div>
            `;
        }
    } catch (e) {
        const body = modal.querySelector('.lyrics-body');
        body.innerHTML = `
            <div class="lyrics-not-found">
                <p>Erro ao buscar letra: ${e.message}</p>
            </div>
        `;
    }
}

function closeLyricsModal() {
    document.getElementById('lyrics-modal')?.remove();
}

// ===== EVENTS LIST MODAL =====
async function showEventsListModal() {
    // Criar modal
    const modal = document.createElement('div');
    modal.id = 'events-modal';
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <div class="modal modal-lyrics">
            <div class="modal-header">
                <h2>📅 Eventos Disponíveis</h2>
            </div>
            <div class="modal-body events-body">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Carregando eventos...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEventsModal()">Cancelar</button>
            </div>
        </div>
    `;
    modal.onclick = (e) => { if (e.target === modal) closeEventsModal(); };
    document.body.appendChild(modal);

    // Ativar modal (necessário para CSS animation)
    requestAnimationFrame(() => modal.classList.add('active'));

    try {
        const response = await fetch(`${API_BASE}/admin/event.php?action=public`);
        const result = await response.json();

        const body = modal.querySelector('.events-body');

        if (!result.success || !result.data || result.data.length === 0) {
            body.innerHTML = `
                <div class="events-empty">
                    <div class="empty-icon">📅</div>
                    <h3>Nenhum evento aberto</h3>
                    <p>No momento não há eventos disponíveis.</p>
                    <button class="btn btn-primary" onclick="closeEventsModal(); showEventCodeModal(true);">
                        Tenho um código
                    </button>
                </div>
            `;
            return;
        }

        body.innerHTML = `
            <p class="events-hint">Selecione um evento para participar:</p>
            <div class="events-list">
                ${result.data.map(event => `
                    <button class="event-card" onclick="selectEventToJoin(${event.id}, '${escapeHtml(event.event_name)}')">
                        <span class="event-name">${escapeHtml(event.event_name)}</span>
                        <span class="event-arrow">→</span>
                    </button>
                `).join('')}
            </div>
            <div class="events-footer">
                <p>Tem um código específico?</p>
            </div>
        `;

        // Atualizar footer do modal com botões lado a lado
        const footer = modal.querySelector('.modal-footer');
        if (footer) {
            footer.innerHTML = `
                <button class="btn btn-secondary" onclick="closeEventsModal(); showEventCodeModal(true);" style="flex: 1;">Digitar código</button>
                <button class="btn btn-secondary" onclick="closeEventsModal()" style="flex: 1;">Cancelar</button>
            `;
        }
    } catch (e) {
        const body = modal.querySelector('.events-body');
        body.innerHTML = `
            <div class="events-empty">
                <p>Erro ao carregar eventos: ${e.message}</p>
                <button class="btn btn-primary" onclick="closeEventsModal(); showEventCodeModal(true);">
                    Digitar código manualmente
                </button>
            </div>
        `;
    }
}

function closeEventsModal() {
    document.getElementById('events-modal')?.remove();
}

async function selectEventToJoin(eventId, eventName) {
    closeEventsModal();

    // Criar modal para digitar o código do evento selecionado
    const modal = document.createElement('div');
    modal.id = 'event-code-select-modal';
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <div class="modal">
            <div class="modal-header">
                <h2>🎤 ${escapeHtml(eventName)}</h2>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 1rem;">Digite o código do evento para entrar:</p>
                <input type="text" id="select-event-code" class="input" placeholder="Código do evento" 
                       maxlength="10" style="text-transform: uppercase; text-align: center; font-size: 1.5rem; letter-spacing: 0.2em;">
            </div>
            <div class="modal-footer" style="display: flex; gap: 0.75rem;">
                <button class="btn btn-secondary" onclick="document.getElementById('event-code-select-modal').remove()" style="flex: 1;">Cancelar</button>
                <button class="btn btn-success" onclick="confirmSelectedEvent(${eventId})" style="flex: 1;">Entrar</button>
            </div>
        </div>
    `;
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
    document.body.appendChild(modal);

    // Ativar modal
    requestAnimationFrame(() => modal.classList.add('active'));

    // Focus no input
    setTimeout(() => {
        const input = document.getElementById('select-event-code');
        if (input) {
            input.focus();
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') confirmSelectedEvent(eventId);
            });
        }
    }, 100);
}

async function confirmSelectedEvent(eventId) {
    const input = document.getElementById('select-event-code');
    const code = input?.value?.trim().toUpperCase();

    if (!code) {
        showToast('Digite o código do evento', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/admin/event.php?action=verify`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code })
        });
        const result = await response.json();

        if (result.valid) {
            // Armazenar código verificado
            sessionStorage.setItem(EVENT_CODE_KEY, code);
            sessionStorage.setItem(EVENT_ID_KEY, result.event_id);
            state.eventCodeVerified = true;

            // Fechar modal
            document.getElementById('event-code-select-modal')?.remove();

            showToast(`✅ ${result.message}`, 'success');

            // Iniciar Pusher para este evento
            initPusher();

            // Renderizar app
            renderApp();
        } else {
            showToast(result.error || 'Código inválido', 'error');
        }
    } catch (e) {
        showToast('Erro ao verificar código', 'error');
    }
}

// ===== RANKING & GAMIFICATION =====
async function renderRanking(container) {
    container.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Carregando ranking...</p>
        </div>
    `;

    try {
        const token = state.profile?.token || '';
        const eventId = sessionStorage.getItem(EVENT_ID_KEY) || null;

        // Busca dados do usuário e ranking global
        const [userResult, rankingResult] = await Promise.all([
            fetchAPI(`ranking.php?token=${encodeURIComponent(token)}${eventId ? `&event_id=${eventId}` : ''}`),
            fetchAPI(`ranking.php?action=global`)
        ]);

        const userData = userResult.success ? userResult.data : {};
        const globalRanking = rankingResult.success ? rankingResult.data.ranking : [];

        // Renderiza a tela
        container.innerHTML = `
            <div class="ranking-header slide-up">
                <h2>🏆 Ranking & Conquistas</h2>
                <p class="ranking-subtitle">Seu desempenho no karaokê</p>
            </div>
            
            <!-- Card do usuário -->
            <div class="ranking-user-card slide-up">
                <div class="user-stats-grid">
                    <div class="stat-item">
                        <div class="stat-value">${userData.total_points || 0}</div>
                        <div class="stat-label">Pontos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">#${userData.position || '-'}</div>
                        <div class="stat-label">Posição</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${userData.songs_sung || 0}</div>
                        <div class="stat-label">Músicas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${userData.week_points || 0}</div>
                        <div class="stat-label">Pontos Semana</div>
                    </div>
                </div>
            </div>
            
            <!-- Badges conquistadas -->
            <div class="ranking-badges slide-up">
                <h3>🎖️ Suas Badges</h3>
                ${userData.badges && userData.badges.length > 0 ? `
                    <div class="badges-grid">
                        ${userData.badges.map(badge => `
                            <div class="badge-item earned">
                                <span class="badge-icon">${badge.badge_icon}</span>
                                <span class="badge-name">${escapeHtml(badge.badge_name)}</span>
                            </div>
                        `).join('')}
                    </div>
                ` : `
                    <div class="badges-empty">
                        <p>Nenhuma badge ainda. Continue cantando para conquistar! 🎤</p>
                    </div>
                `}
            </div>
            
            <!-- Ranking semanal -->
            <div class="ranking-weekly slide-up">
                <h3>📊 Ranking Semanal</h3>
                ${globalRanking.length > 0 ? `
                    <div class="ranking-list">
                        ${globalRanking.map((player, index) => `
                            <div class="ranking-item ${player.id === userData.profile_id ? 'current-user' : ''}">
                                <div class="ranking-position">
                                    ${index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `#${index + 1}`}
                                </div>
                                <div class="ranking-avatar" style="background-color: ${player.avatar_color || '#6366f1'}">
                                    ${player.name ? player.name.substring(0, 2).toUpperCase() : '??'}
                                </div>
                                <div class="ranking-info">
                                    <div class="ranking-name">${escapeHtml(player.name)}</div>
                                    <div class="ranking-points">${player.week_points} pts esta semana</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : `
                    <div class="ranking-empty">
                        <p>Nenhuma atividade ainda esta semana. Seja o primeiro! 🚀</p>
                    </div>
                `}
            </div>
            
            <!-- Pontuação -->
            <div class="ranking-rules slide-up">
                <h3>📝 Como ganhar pontos</h3>
                <div class="rules-list">
                    <div class="rule-item">
                        <span class="rule-icon">🎤</span>
                        <span class="rule-text">Cantar uma música</span>
                        <span class="rule-points">+5 pts</span>
                    </div>
                    <div class="rule-item">
                        <span class="rule-icon">🌟</span>
                        <span class="rule-text">Primeira música do dia</span>
                        <span class="rule-points">+2 pts</span>
                    </div>
                    <div class="rule-item">
                        <span class="rule-icon">⚔️</span>
                        <span class="rule-text">Vencer uma batalha</span>
                        <span class="rule-points">+7 pts</span>
                    </div>
                </div>
            </div>
            
            <!-- Histórico recente de pontos -->
            ${userData.recent_points && userData.recent_points.length > 0 ? `
                <div class="ranking-history slide-up">
                    <h3>📜 Histórico de Pontos</h3>
                    <div class="points-history-list">
                        ${userData.recent_points.slice(0, 5).map(point => `
                            <div class="history-item">
                                <div class="history-points">+${point.points}</div>
                                <div class="history-desc">${escapeHtml(point.description || point.action_type)}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        `;

    } catch (e) {
        console.error('Erro ao carregar ranking:', e);
        container.innerHTML = `
            <div class="results-empty slide-up">
                <div class="results-empty-icon">❌</div>
                <h3>Erro ao carregar ranking</h3>
                <p>${e.message}</p>
                <button class="btn btn-primary" onclick="renderTabContent()">Tentar novamente</button>
            </div>
        `;
    }
}

// ===== BATTLE / DUEL MODE =====
async function renderBattle(container) {
    container.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Carregando duelo...</p>
        </div>
    `;

    try {
        const eventId = sessionStorage.getItem(EVENT_ID_KEY) || 1;
        const token = state.profile?.token || '';
        const result = await fetchAPI(`battle.php?event_id=${eventId}&token=${encodeURIComponent(token)}`);

        if (!result.has_battle) {
            container.innerHTML = `
                <div class="battle-empty slide-up">
                    <div class="empty-icon">⚔️</div>
                    <h2>Nenhum Duelo Ativo</h2>
                    <p>Aguarde o DJ iniciar uma batalha!</p>
                    <p class="hint">Quando dois cantores se enfrentarem, você poderá votar aqui.</p>
                </div>
            `;
            return;
        }

        const battle = result.data;
        const isActive = battle.status === 'active';
        const hasVoted = battle.has_voted;
        const totalVotes = battle.contestant1.votes + battle.contestant2.votes;

        container.innerHTML = `
            <div class="battle-header slide-up">
                <h2>⚔️ Duelo de Vozes</h2>
                <p class="battle-status">${isActive ? '🔴 VOTAÇÃO ABERTA' : '⏳ Aguardando início...'}</p>
            </div>
            
            <div class="battle-arena slide-up">
                <div class="battle-contestant ${hasVoted && battle.voted_for === battle.contestant1.id ? 'voted' : ''}" 
                     onclick="${isActive && !hasVoted ? `voteBattle(${battle.id}, ${battle.contestant1.id})` : ''}">
                    <div class="contestant-avatar">🎤</div>
                    <div class="contestant-name">${escapeHtml(battle.contestant1.name)}</div>
                    <div class="contestant-song">"${escapeHtml(battle.contestant1.song)}"</div>
                    ${isActive ? `
                        <div class="contestant-votes">${battle.contestant1.votes} votos</div>
                        <div class="vote-bar">
                            <div class="vote-fill" style="width: ${totalVotes ? (battle.contestant1.votes / totalVotes * 100) : 50}%"></div>
                        </div>
                    ` : ''}
                    ${isActive && !hasVoted ? '<button class="btn btn-vote">Votar</button>' : ''}
                    ${hasVoted && battle.voted_for === battle.contestant1.id ? '<div class="voted-badge">✓ Seu voto</div>' : ''}
                </div>
                
                <div class="battle-vs">VS</div>
                
                <div class="battle-contestant ${hasVoted && battle.voted_for === battle.contestant2.id ? 'voted' : ''}"
                     onclick="${isActive && !hasVoted ? `voteBattle(${battle.id}, ${battle.contestant2.id})` : ''}">
                    <div class="contestant-avatar">🎤</div>
                    <div class="contestant-name">${escapeHtml(battle.contestant2.name)}</div>
                    <div class="contestant-song">"${escapeHtml(battle.contestant2.song)}"</div>
                    ${isActive ? `
                        <div class="contestant-votes">${battle.contestant2.votes} votos</div>
                        <div class="vote-bar">
                            <div class="vote-fill" style="width: ${totalVotes ? (battle.contestant2.votes / totalVotes * 100) : 50}%"></div>
                        </div>
                    ` : ''}
                    ${isActive && !hasVoted ? '<button class="btn btn-vote">Votar</button>' : ''}
                    ${hasVoted && battle.voted_for === battle.contestant2.id ? '<div class="voted-badge">✓ Seu voto</div>' : ''}
                </div>
            </div>
            
            ${hasVoted ? '<p class="battle-hint">Você já votou! Aguarde o resultado.</p>' : ''}
        `;

    } catch (e) {
        container.innerHTML = `
            <div class="results-empty slide-up">
                <h3>Erro ao carregar duelo</h3>
                <p>${e.message}</p>
                <button class="btn btn-primary" onclick="renderTabContent()">Tentar novamente</button>
            </div>
        `;
    }
}

async function voteBattle(battleId, voteFor) {
    if (!state.profile) {
        showToast('Faça login para votar', 'error');
        return;
    }

    try {
        const result = await fetchAPI('battle.php?action=vote', {
            method: 'POST',
            body: JSON.stringify({
                battle_id: battleId,
                vote_for: voteFor,
                token: state.profile.token
            })
        });

        showToast('🗳️ Voto registrado!', 'success');
        renderTabContent(); // Refresh

    } catch (e) {
        showToast(e.message || 'Erro ao votar', 'error');
    }
}

// ===== SONG DETAILS MODAL =====

/**
 * Exibe modal com detalhes da música e busca a letra
 */
function showSongDetails(songCode) {
    const song = state.songsMap[songCode];
    if (!song) {
        showToast('Música não encontrada', 'error');
        return;
    }

    // Verificar se está nos favoritos
    const isFavorite = state.favorites.some(f => f.song_code === songCode);

    // Verificar se está na fila
    const queueIndex = state.queue.findIndex(q => q.code === songCode);
    const isInQueue = queueIndex !== -1;

    // Criar modal
    const modalHtml = `
        <div class="modal-backdrop active" id="song-details-modal" onclick="closeSongDetailsModal(event)">
            <div class="modal modal-song-details" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <div class="song-details-header">
                        <div class="song-code-large">${escapeHtml(song.code)}</div>
                        <div>
                            <h2 class="song-title-large">${escapeHtml(song.title || 'Sem título')}</h2>
                            <p class="song-artist-large">${escapeHtml(song.artist || 'Artista desconhecido')}</p>
                        </div>
                    </div>
                    <button class="modal-close" onclick="closeSongDetailsModal()">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div class="song-actions-modal">
                        <button class="btn ${isInQueue ? 'btn-success' : 'btn-primary'}" onclick="toggleQueue('${escapeHtml(songCode)}'); closeSongDetailsModal();">
                            ${isInQueue ? '✓ Na Fila #' + (queueIndex + 1) : '🎤 Quero Cantar'}
                        </button>
                        <button class="btn ${isFavorite ? 'btn-danger' : 'btn-secondary'}" onclick="toggleFavorite('${escapeHtml(songCode)}'); closeSongDetailsModal();">
                            ${isFavorite ? '💔 Remover Favorito' : '❤️ Favoritar'}
                        </button>
                    </div>
                    
                    <div class="lyrics-section">
                        <h3 class="lyrics-title">📜 Letra da Música</h3>
                        <div id="lyrics-content" class="lyrics-content">
                            <div class="loading">
                                <div class="spinner"></div>
                                <span>Buscando letra...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Adicionar ao body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Buscar letra
    fetchLyrics(song.artist, song.title);
}

/**
 * Fecha o modal de detalhes
 */
function closeSongDetailsModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('song-details-modal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Busca a letra da música via API
 */
async function fetchLyrics(artist, title) {
    const container = document.getElementById('lyrics-content');
    if (!container) return;

    try {
        const params = new URLSearchParams({ artist, title });
        const response = await fetch(`api/lyrics.php?${params}`);
        const result = await response.json();

        if (result.found && result.data?.lyrics) {
            // Formata a letra com quebras de linha
            const formattedLyrics = escapeHtml(result.data.lyrics)
                .replace(/\n\n/g, '</p><p class="lyrics-verse">')
                .replace(/\n/g, '<br>');

            container.innerHTML = `
                <div class="lyrics-found">
                    <p class="lyrics-verse">${formattedLyrics}</p>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="lyrics-not-found">
                    <div class="lyrics-not-found-icon">🎵</div>
                    <p>${result.message || 'Letra não disponível para esta música'}</p>
                    <p class="lyrics-hint">Tente pesquisar em sites especializados</p>
                </div>
            `;
        }
    } catch (e) {
        console.error('Erro ao buscar letra:', e);
        container.innerHTML = `
            <div class="lyrics-not-found">
                <div class="lyrics-not-found-icon">⚠️</div>
                <p>Erro ao buscar letra</p>
                <p class="lyrics-hint">${e.message}</p>
            </div>
        `;
    }
}

/**
 * Exibe modal de letra (chamado da fila de karaokê)
 */
function showLyricsModal(artist, title) {
    // Criar modal simplificado apenas com a letra
    const modalHtml = `
        <div class="modal-backdrop active" id="lyrics-modal" onclick="closeLyricsModal(event)">
            <div class="modal modal-lyrics" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <div>
                        <h2 class="song-title-large">${escapeHtml(title)}</h2>
                        <p class="song-artist-large">${escapeHtml(artist)}</p>
                    </div>
                    <button class="modal-close" onclick="closeLyricsModal()">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div id="lyrics-modal-content" class="lyrics-content">
                        <div class="loading">
                            <div class="spinner"></div>
                            <span>Buscando letra...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Buscar letra com ID diferente
    fetchLyricsForModal(artist, title);
}

/**
 * Fecha modal de letra
 */
function closeLyricsModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('lyrics-modal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Busca letra para o modal simplificado
 */
async function fetchLyricsForModal(artist, title) {
    const container = document.getElementById('lyrics-modal-content');
    if (!container) return;

    try {
        const params = new URLSearchParams({ artist, title });
        const response = await fetch(`api/lyrics.php?${params}`);
        const result = await response.json();

        if (result.found && result.data?.lyrics) {
            const formattedLyrics = escapeHtml(result.data.lyrics)
                .replace(/\n\n/g, '</p><p class="lyrics-verse">')
                .replace(/\n/g, '<br>');

            container.innerHTML = `
                <div class="lyrics-found">
                    <p class="lyrics-verse">${formattedLyrics}</p>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="lyrics-not-found">
                    <div class="lyrics-not-found-icon">🎵</div>
                    <p>${result.message || 'Letra não disponível'}</p>
                </div>
            `;
        }
    } catch (e) {
        container.innerHTML = `
            <div class="lyrics-not-found">
                <div class="lyrics-not-found-icon">⚠️</div>
                <p>Erro ao buscar letra</p>
            </div>
        `;
    }
}
