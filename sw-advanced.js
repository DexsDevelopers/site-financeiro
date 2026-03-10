// sw-advanced.js - Service Worker de Elite para PWA (2025/2026)
// Gerencia Cache e Notificações Natas (Push API)

const CACHE_NAME = 'painel-financeiro-v2.1.0';
const STATIC_CACHE = 'static-v2.1.0';
const DYNAMIC_CACHE = 'dynamic-v2.1.0';

// Recursos essenciais para cache
const STATIC_ASSETS = [
    './',
    'dashboard.php',
    'offline.html',
    'manifest.json',
    'assets/js/push_manager.js'
];

self.addEventListener('install', e => {
    e.waitUntil(caches.open(STATIC_CACHE).then(c => c.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys => Promise.all(keys.map(k => {
            if (k !== STATIC_CACHE && k !== DYNAMIC_CACHE) return caches.delete(k);
        })))
    );
    self.clients.claim();
});

// --- LOGICA DE PUSH ---

self.addEventListener('push', event => {
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { body: event.data.text() };
        }
    }

    const title = data.title || 'Ghost Pix';
    const options = {
        body: data.body || 'Nova atualização no sistema.',
        icon: data.icon || '/assets/img/icon_192.png',
        badge: data.badge_icon || '/assets/img/badge_96.png',
        vibrate: [200, 100, 200],
        data: {
            url: data.url || '/dashboard.php',
            badgeCount: data.badge_count || 1
        },
        actions: data.actions || [
            { action: 'open', title: 'Abrir' },
            { action: 'close', title: 'Ignorar' }
        ],
        tag: 'ghost-pix-notif',
        renotify: true
    };

    // Atualizar App Badge (aquele numerozinho no ícone)
    if ('setAppBadge' in navigator) {
        navigator.setAppBadge(options.data.badgeCount).catch(() => { });
    }

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    const notif = event.notification;
    const action = event.action;
    const url = notif.data.url;

    notif.close();

    // Limpar Badge ao abrir
    if ('clearAppBadge' in navigator) {
        navigator.clearAppBadge().catch(() => { });
    }

    if (action === 'close') return;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            for (let client of windowClients) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});

// Limpar Badge ao receber mensagem do front (ex: quando o usuário entra no app)
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'CLEAR_BADGE') {
        if ('clearAppBadge' in navigator) navigator.clearAppBadge();
    }
});

// Cache Fetch (Estratégia Simples Network First para o resto)
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request) || caches.match('offline.html');
        })
    );
});
