// sw-advanced.js - Service Worker Avançado para PWA
const CACHE_NAME = 'painel-financeiro-v2.0.0';
const OFFLINE_URL = '/seu_projeto/offline.html';
const STATIC_CACHE = 'static-v2.0.0';
const DYNAMIC_CACHE = 'dynamic-v2.0.0';

// Recursos essenciais para cache estático
const STATIC_ASSETS = [
    '/seu_projeto/',
    '/seu_projeto/dashboard.php',
    '/seu_projeto/index.php',
    '/seu_projeto/offline.html',
    '/seu_projeto/manifest.json',
    '/seu_projeto/pwa-install-prompt.js',
    '/seu_projeto/sw.js',
    '/seu_projeto/sw-advanced.js'
];

// Recursos externos para cache
const EXTERNAL_ASSETS = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
    'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
    'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js',
    'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css'
];

// Estratégias de cache
const CACHE_STRATEGIES = {
    CACHE_FIRST: 'cache-first',
    NETWORK_FIRST: 'network-first',
    STALE_WHILE_REVALIDATE: 'stale-while-revalidate',
    NETWORK_ONLY: 'network-only',
    CACHE_ONLY: 'cache-only'
};

// Configuração de estratégias por tipo de recurso
const RESOURCE_STRATEGIES = {
    // Páginas HTML - Cache First com fallback
    'text/html': CACHE_STRATEGIES.CACHE_FIRST,
    // CSS e JS - Cache First
    'text/css': CACHE_STRATEGIES.CACHE_FIRST,
    'application/javascript': CACHE_STRATEGIES.CACHE_FIRST,
    // Imagens - Cache First
    'image/png': CACHE_STRATEGIES.CACHE_FIRST,
    'image/jpeg': CACHE_STRATEGIES.CACHE_FIRST,
    'image/svg+xml': CACHE_STRATEGIES.CACHE_FIRST,
    // APIs - Network First
    'application/json': CACHE_STRATEGIES.NETWORK_FIRST,
    // Fontes - Cache First
    'font/woff': CACHE_STRATEGIES.CACHE_FIRST,
    'font/woff2': CACHE_STRATEGIES.CACHE_FIRST
};

// Instalar service worker
self.addEventListener('install', event => {
    console.log('Service Worker Avançado: Instalando...');
    
    event.waitUntil(
        Promise.all([
            // Cache de recursos estáticos
            caches.open(STATIC_CACHE).then(cache => {
                console.log('Service Worker: Cache estático aberto');
                return cache.addAll(STATIC_ASSETS);
            }),
            // Cache de recursos externos
            caches.open(DYNAMIC_CACHE).then(cache => {
                console.log('Service Worker: Cache dinâmico aberto');
                return cache.addAll(EXTERNAL_ASSETS);
            })
        ]).then(() => {
            console.log('Service Worker Avançado: Instalação concluída');
            return self.skipWaiting();
        }).catch(error => {
            console.error('Service Worker Avançado: Erro na instalação:', error);
        })
    );
});

// Ativar service worker
self.addEventListener('activate', event => {
    console.log('Service Worker Avançado: Ativando...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Service Worker: Removendo cache antigo:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker Avançado: Ativação concluída');
                return self.clients.claim();
            })
    );
});

// Interceptar requisições
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Ignorar requisições que não são GET
    if (request.method !== 'GET') {
        return;
    }
    
    // Ignorar requisições de API e formulários
    if (shouldIgnoreRequest(request)) {
        return;
    }
    
    // Determinar estratégia de cache
    const strategy = getCacheStrategy(request);
    
    event.respondWith(
        handleRequest(request, strategy)
    );
});

// Verificar se deve ignorar a requisição
function shouldIgnoreRequest(request) {
    const url = request.url;
    
    // Ignorar APIs e formulários
    const ignorePatterns = [
        '/api/',
        'salvar_',
        'atualizar_',
        'excluir_',
        'login_process.php',
        'logout.php',
        'registrar.php',
        'upload.php',
        'processar_',
        'buscar_',
        'concluir_',
        'editar_',
        'adicionar_'
    ];
    
    return ignorePatterns.some(pattern => url.includes(pattern)) ||
           request.redirect !== 'follow' ||
           url.includes('googleapis.com') ||
           url.includes('gstatic.com') ||
           url.includes('onesignal.com');
}

// Obter estratégia de cache baseada no tipo de recurso
function getCacheStrategy(request) {
    const acceptHeader = request.headers.get('accept') || '';
    
    // Páginas HTML
    if (acceptHeader.includes('text/html')) {
        return CACHE_STRATEGIES.CACHE_FIRST;
    }
    
    // CSS e JS
    if (acceptHeader.includes('text/css') || acceptHeader.includes('application/javascript')) {
        return CACHE_STRATEGIES.CACHE_FIRST;
    }
    
    // Imagens
    if (acceptHeader.includes('image/')) {
        return CACHE_STRATEGIES.CACHE_FIRST;
    }
    
    // APIs
    if (acceptHeader.includes('application/json')) {
        return CACHE_STRATEGIES.NETWORK_FIRST;
    }
    
    // Padrão: Cache First
    return CACHE_STRATEGIES.CACHE_FIRST;
}

// Manipular requisição baseada na estratégia
async function handleRequest(request, strategy) {
    try {
        switch (strategy) {
            case CACHE_STRATEGIES.CACHE_FIRST:
                return await cacheFirst(request);
            case CACHE_STRATEGIES.NETWORK_FIRST:
                return await networkFirst(request);
            case CACHE_STRATEGIES.STALE_WHILE_REVALIDATE:
                return await staleWhileRevalidate(request);
            case CACHE_STRATEGIES.NETWORK_ONLY:
                return await networkOnly(request);
            case CACHE_STRATEGIES.CACHE_ONLY:
                return await cacheOnly(request);
            default:
                return await cacheFirst(request);
        }
    } catch (error) {
        console.error('Service Worker: Erro ao processar requisição:', error);
        return await handleOffline(request);
    }
}

// Estratégia Cache First
async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        console.log('Service Worker: Servindo do cache:', request.url);
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Erro na rede, tentando cache:', error);
        return await handleOffline(request);
    }
}

// Estratégia Network First
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Erro na rede, tentando cache:', error);
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        return await handleOffline(request);
    }
}

// Estratégia Stale While Revalidate
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    // Buscar da rede em background
    const networkResponsePromise = fetch(request).then(response => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);
    
    // Retornar cache imediatamente se disponível
    if (cachedResponse) {
        console.log('Service Worker: Servindo cache enquanto revalida:', request.url);
        return cachedResponse;
    }
    
    // Se não há cache, aguardar rede
    return await networkResponsePromise || await handleOffline(request);
}

// Estratégia Network Only
async function networkOnly(request) {
    return await fetch(request);
}

// Estratégia Cache Only
async function cacheOnly(request) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    return await handleOffline(request);
}

// Manipular offline
async function handleOffline(request) {
    const acceptHeader = request.headers.get('accept') || '';
    
    // Se for uma página HTML, mostrar página offline
    if (acceptHeader.includes('text/html')) {
        const offlineResponse = await caches.match(OFFLINE_URL);
        if (offlineResponse) {
            return offlineResponse;
        }
    }
    
    // Para outros recursos, retornar erro
    return new Response('Recurso não disponível offline', {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'text/plain' }
    });
}

// Notificações push
self.addEventListener('push', event => {
    console.log('Service Worker: Push recebido');
    
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { body: event.data.text() };
        }
    }
    
    const options = {
        body: data.body || 'Nova notificação do Painel Financeiro',
        icon: '/seu_projeto/icons/icon-192x192.png',
        badge: '/seu_projeto/icons/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1,
            url: data.url || '/seu_projeto/dashboard.php'
        },
        actions: [
            {
                action: 'explore',
                title: 'Ver detalhes',
                icon: '/seu_projeto/icons/icon-96x96.png'
            },
            {
                action: 'close',
                title: 'Fechar',
                icon: '/seu_projeto/icons/icon-72x72.png'
            }
        ],
        tag: 'painel-financeiro-notification',
        requireInteraction: false,
        silent: false
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'Painel Financeiro', options)
    );
});

// Clique em notificação
self.addEventListener('notificationclick', event => {
    console.log('Service Worker: Clique na notificação');
    
    event.notification.close();
    
    const url = event.notification.data?.url || '/seu_projeto/dashboard.php';
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow(url)
        );
    } else if (event.action === 'close') {
        // Apenas fechar a notificação
    } else {
        // Clique na notificação (não em uma ação)
        event.waitUntil(
            clients.openWindow(url)
        );
    }
});

// Sincronização em background
self.addEventListener('sync', event => {
    console.log('Service Worker: Sincronização em background');
    
    if (event.tag === 'background-sync') {
        event.waitUntil(
            syncOfflineData()
        );
    }
    
    if (event.tag === 'cache-update') {
        event.waitUntil(
            updateCache()
        );
    }
});

// Sincronizar dados offline
async function syncOfflineData() {
    try {
        console.log('Service Worker: Sincronizando dados offline...');
        
        // Implementar sincronização de dados que foram salvos offline
        // Aqui você pode implementar a lógica para sincronizar dados
        // que foram salvos localmente quando offline
        
        console.log('Service Worker: Sincronização concluída');
    } catch (error) {
        console.error('Service Worker: Erro na sincronização:', error);
    }
}

// Atualizar cache
async function updateCache() {
    try {
        console.log('Service Worker: Atualizando cache...');
        
        // Implementar lógica de atualização de cache
        // Aqui você pode implementar a lógica para atualizar
        // recursos em cache quando houver novas versões
        
        console.log('Service Worker: Cache atualizado');
    } catch (error) {
        console.error('Service Worker: Erro na atualização do cache:', error);
    }
}

// Mensagens do cliente
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => caches.delete(cacheName))
                );
            }).then(() => {
                event.ports[0].postMessage({ success: true });
            })
        );
    }
    
    if (event.data && event.data.type === 'UPDATE_CACHE') {
        event.waitUntil(
            updateCache().then(() => {
                event.ports[0].postMessage({ success: true });
            })
        );
    }
});

// Limpeza periódica do cache
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'CLEANUP_CACHE') {
        event.waitUntil(
            cleanupCache()
        );
    }
});

// Limpar cache antigo
async function cleanupCache() {
    try {
        const cacheNames = await caches.keys();
        const currentCaches = [STATIC_CACHE, DYNAMIC_CACHE];
        
        const oldCaches = cacheNames.filter(name => !currentCaches.includes(name));
        
        await Promise.all(
            oldCaches.map(cacheName => caches.delete(cacheName))
        );
        
        console.log('Service Worker: Cache limpo');
    } catch (error) {
        console.error('Service Worker: Erro na limpeza do cache:', error);
    }
}
