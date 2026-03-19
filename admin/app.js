// KJ Dashboard JavaScript

const API_BASE = '../api/admin/';

// Check authentication
async function checkAuth() {
    try {
        const response = await fetch(API_BASE + 'auth.php', {
            credentials: 'include'
        });
        const data = await response.json();

        if (!data.logged) {
            window.location.href = 'login.php';
            return false;
        }

        // Update sidebar (only if element exists)
        const userName = document.getElementById('user-name');
        if (userName) userName.textContent = data.logged.name;

        const estNameEl = document.getElementById('establishment-name');
        const estName = localStorage.getItem('kjEstablishment');
        if (estNameEl && estName) {
            estNameEl.textContent = estName;
        }

        // Update event info (only on index.php)
        const eventNameEl = document.getElementById('event-name');
        if (eventNameEl && data.event) {
            eventNameEl.textContent =
                `${data.event.event_name} | Código: ${data.event.event_code} | ${data.event.is_open ? '🟢 Aberto' : '🔴 Fechado'}`;
        }

        return true;
    } catch (e) {
        console.error('Auth check failed:', e);
        window.location.href = 'login.php';
        return false;
    }
}

// Logout
async function logout() {
    try {
        await fetch(API_BASE + 'auth.php?action=logout', {
            method: 'POST',
            credentials: 'include'
        });
    } catch (e) { }

    localStorage.removeItem('kjName');
    localStorage.removeItem('kjEstablishment');
    window.location.href = 'login.php';
}

// Fetch API helper
async function fetchAPI(endpoint, options = {}) {
    const response = await fetch(API_BASE + endpoint, {
        ...options,
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        }
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || 'Erro na requisição');
    }

    return data;
}

// HTML escape
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show toast
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

// Modal helpers
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
