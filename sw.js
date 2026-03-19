/**
 * WosKaraoke - Service Worker
 * Suporta notificacoes push e cache offline
 *
 * @version 1.1 - Paths dinamicos baseados no escopo de registro
 */

const CACHE_NAME = 'woskaraoke-v2';

// Derive base path from the SW scope (works in any deploy directory)
const BASE_PATH = new URL('.', self.location.href).pathname;

const STATIC_ASSETS = [
    BASE_PATH,
    BASE_PATH + 'index.php',
    BASE_PATH + 'assets/css/styles.css',
    BASE_PATH + 'assets/js/app.js',
    BASE_PATH + 'manifest.json'
];

// ============================================
// INSTALACAO - Cache de assets estaticos
// ============================================
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// ============================================
// ATIVACAO - Limpa caches antigos
// ============================================
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name !== CACHE_NAME)
                        .map((name) => caches.delete(name))
                );
            })
            .then(() => self.clients.claim())
    );
});

// ============================================
// FETCH - Network First para API, Cache First para assets
// ============================================
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (url.pathname.includes('/api/')) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    event.respondWith(cacheFirst(event.request));
});

async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        return cachedResponse || new Response('Offline', { status: 503 });
    }
}

async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        return new Response('Offline', { status: 503 });
    }
}

// ============================================
// PUSH NOTIFICATIONS
// ============================================
self.addEventListener('push', (event) => {
    console.log('[SW] Push received');

    let data = {
        title: 'WosKaraoke',
        body: 'Voce tem uma notificacao!',
        icon: BASE_PATH + 'assets/images/icon-192.png',
        badge: BASE_PATH + 'assets/images/badge-72.png',
        tag: 'woskaraoke-notification',
        data: {}
    };

    if (event.data) {
        try {
            data = { ...data, ...event.data.json() };
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: data.badge,
        tag: data.tag,
        vibrate: [200, 100, 200],
        data: data.data,
        actions: data.actions || [],
        requireInteraction: data.requireInteraction || false
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// ============================================
// CLICK NA NOTIFICACAO
// ============================================
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked');

    event.notification.close();

    const urlToOpen = event.notification.data?.url || BASE_PATH;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                for (const client of windowClients) {
                    if (client.url.includes(BASE_PATH) && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// ============================================
// MENSAGENS DO APP
// ============================================
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);

    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data.type === 'SHOW_NOTIFICATION') {
        const { title, body, data } = event.data;
        self.registration.showNotification(title, {
            body,
            icon: BASE_PATH + 'assets/images/icon-192.png',
            tag: 'queue-notification',
            vibrate: [200, 100, 200],
            data
        });
    }
});

console.log('[SW] Service Worker loaded');
