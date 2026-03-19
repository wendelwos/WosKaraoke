
/**
 * Renderiza o histórico de músicas e nível do usuário
 */
async function renderHistory(container) {
    if (!state.profile) {
        showLoginModal();
        return;
    }

    container.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Carregando seu histórico...</p>
        </div>
    `;

    try {
        const result = await fetchAPI(`history.php?token=${encodeURIComponent(state.profile.token)}`);

        if (!result.success) {
            throw new Error(result.error || 'Erro ao carregar histórico');
        }

        const { history, stats } = result.data;

        container.innerHTML = `
            <div class="profile-header slide-up">
                <div class="avatar avatar-lg" style="background-color: ${state.profile.avatar_color}">
                    ${state.profile.initials}
                </div>
                <h2 class="profile-name">${state.profile.name}</h2>
                <div class="level-badge shadow-sm">
                    <span class="level-title">${stats.level_title}</span>
                    <span class="level-number">Nível ${stats.level}</span>
                </div>
                
                <div class="level-progress-container">
                    <div class="level-info">
                        <span>${stats.total_songs} músicas cantadas</span>
                        <span>Próximo nível: ${stats.next_level_at}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${stats.progress}%"></div>
                    </div>
                </div>
            </div>

            <div class="history-list slide-up">
                <h3 class="section-title">Minhas Cantorias 📜</h3>
                ${history.length === 0 ? `
                    <div class="empty-state">
                        <p>Você ainda não cantou nada!</p>
                        <button class="btn btn-primary btn-sm" onclick="switchTab('search')">Começar agora</button>
                    </div>
                ` : history.map(item => `
                    <div class="card history-card">
                        <div class="history-date">
                            <span class="date-day">${new Date(item.sung_at).toLocaleDateString('pt-BR')}</span>
                            <span class="date-time">${new Date(item.sung_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</span>
                        </div>
                        <div class="history-info">
                            <div class="song-title">${escapeHtml(item.song_title)}</div>
                            <div class="song-artist">${escapeHtml(item.song_artist)}</div>
                            <div class="event-tag">📍 ${escapeHtml(item.event_name || 'Evento')}</div>
                        </div>
                        <button class="btn-icon btn-replay" onclick="toggleQueue('${item.song_code}')" title="Cantar de novo">
                            🔁
                        </button>
                    </div>
                `).join('')}
            </div>
        `;

    } catch (e) {
        console.error('Erro ao renderizar histórico:', e);
        container.innerHTML = `
            <div class="results-empty slide-up">
                <h3>Erro ao carregar</h3>
                <p>${e.message}</p>
                <button class="btn btn-primary" onclick="renderTabContent()">Tentar novamente</button>
            </div>
        `;
    }
}
