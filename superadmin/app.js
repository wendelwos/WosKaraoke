// Super Admin JavaScript

const API_BASE = '../api/superadmin/';

// Check authentication
async function checkAuth() {
    try {
        const response = await fetch(API_BASE + 'auth.php?action=check', {
            credentials: 'include'
        });
        const data = await response.json();

        if (!data.authenticated) {
            window.location.href = 'login.php';
            return false;
        }

        // Update user name in sidebar
        document.getElementById('user-name').textContent = data.data.name;
        return true;
    } catch (e) {
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

    localStorage.removeItem('superAdminToken');
    localStorage.removeItem('superAdminName');
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

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR');
}

// Show toast
function showToast(message, type = 'success') {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#22c55e' : '#ef4444'};
        color: white;
        border-radius: 8px;
        font-weight: 500;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
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

// Confirm dialog
function confirmAction(message) {
    return confirm(message);
}
