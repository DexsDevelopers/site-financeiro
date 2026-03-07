// sw.js - Service Worker para PWA
const CACHE_NAME = 'painel-financeiro-v1.0.3';
const OFFLINE_URL = 'offline.html';

// Recursos essenciais para cache
const CORE_ASSETS = [
  './',
  'dashboard.php',
  'index.php',
  'offline.html',
  'manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
  'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js',
  'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css'
];

// Instalar service worker
self.addEventListener('install', event => {
  console.log('Service Worker: Instalando...');

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Cache aberto');
        return cache.addAll(CORE_ASSETS);
      })
      .then(() => {
        console.log('Service Worker: Instalação concluída');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Erro na instalação:', error);
      })
  );
});

// Ativar service worker
self.addEventListener('activate', event => {
  console.log('Service Worker: Ativando...');

  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('Service Worker: Removendo cache antigo:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Ativação concluída');
        return self.clients.claim();
      })
  );
});

// Interceptar requisições
self.addEventListener('fetch', event => {
  // Ignorar requisições que não são GET
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  const isLocal = url.origin === self.location.origin;

  // Ignorar requisições de API, processos e externos críticos
  if (url.pathname.includes('/api/') ||
    url.pathname.includes('salvar_') ||
    url.pathname.includes('atualizar_') ||
    url.pathname.includes('excluir_') ||
    url.pathname.includes('processar_') ||
    url.pathname.includes('login_') ||
    url.pathname.includes('logout.php') ||
    url.pathname.includes('cdn.jsdelivr.net') ||
    url.pathname.includes('googleapis.com')) {
    return;
  }

  // ESTRATÉGIA: Network First para arquivos do sistema (CSS/JS/PHP)
  // Isso garante que se houver internet, ele pega a versão mais nova.
  // Se estiver offline, pega o que estiver no cache.
  if (isLocal && (url.pathname.endsWith('.php') || url.pathname.endsWith('.css') || url.pathname.endsWith('.js'))) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          if (response && response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
          }
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // ESTRATÉGIA: Cache First para o resto (Imagens, Fontes estáveis)
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) return response;

        return fetch(event.request).then(response => {
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseToCache));
          return response;
        });
      })
      .catch(() => {
        if (event.request.headers.get('accept').includes('text/html')) {
          return caches.match(OFFLINE_URL);
        }
      })
  );
});

// Notificações push
self.addEventListener('push', event => {
  console.log('Service Worker: Push recebido');

  const options = {
    body: event.data ? event.data.text() : 'Nova notificação do Painel Financeiro',
    icon: 'icons/icon-192x192.png',
    badge: 'icons/icon-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'Ver detalhes',
        icon: 'icons/action-explore.png'
      },
      {
        action: 'close',
        title: 'Fechar',
        icon: 'icons/action-close.png'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification('Painel Financeiro', options)
  );
});

// Clique em notificação
self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Clique na notificação');

  event.notification.close();

  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('dashboard.php')
    );
  } else if (event.action === 'close') {
    // Apenas fechar a notificação
  } else {
    // Clique na notificação (não em uma ação)
    event.waitUntil(
      clients.openWindow('dashboard.php')
    );
  }
});

// Sincronização em background
self.addEventListener('sync', event => {
  console.log('Service Worker: Sincronização em background');

  if (event.tag === 'background-sync') {
    event.waitUntil(
      // Implementar sincronização de dados offline
      syncOfflineData()
    );
  }
});

// Função para sincronizar dados offline
async function syncOfflineData() {
  try {
    // Aqui você pode implementar a sincronização de dados
    // que foram salvos offline
    console.log('Service Worker: Sincronizando dados offline...');
  } catch (error) {
    console.error('Service Worker: Erro na sincronização:', error);
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
});

// Atualização automática
self.addEventListener('message', event => {
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
});