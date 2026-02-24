// sw.js - Service Worker para PWA
const CACHE_NAME = 'painel-financeiro-v1.0.1';
const OFFLINE_URL = 'offline.html';

// Recursos essenciais para cache
const CORE_ASSETS = [
  '/',
  '/dashboard.php',
  '/index.php',
  '/offline.html',
  '/manifest.json',
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
  if (event.request.method !== 'GET') {
    return;
  }

  // Ignorar completamente requisições cross-origin (deixa o navegador tratar)
  try {
    const reqUrl = new URL(event.request.url);
    const swOrigin = self.location.origin;
    if (reqUrl.origin !== swOrigin) {
      return;
    }
  } catch (e) {
    // Se não conseguir parsear, não intercepta
    return;
  }

  // Ignorar requisições de API e formulários
  if (event.request.url.includes('/api/') ||
    event.request.url.includes('salvar_') ||
    event.request.url.includes('atualizar_') ||
    event.request.url.includes('excluir_') ||
    event.request.url.includes('login_process.php') ||
    event.request.url.includes('logout.php') ||
    event.request.url.includes('registrar.php') ||
    event.request.url.includes('upload.php')) {
    return;
  }

  // Ignorar requisições com redirecionamentos
  if (event.request.redirect !== 'follow') {
    return;
  }

  // Ignorar requisições de recursos externos que podem causar problemas
  // e bibliotecas via CDN (deixar o navegador tratar)
  if (event.request.url.includes('googleapis.com') ||
    event.request.url.includes('gstatic.com') ||
    event.request.url.includes('onesignal.com') ||
    event.request.url.includes('unpkg.com') ||
    event.request.url.includes('cdn.jsdelivr.net')) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Retornar do cache se disponível
        if (response) {
          console.log('Service Worker: Servindo do cache:', event.request.url);
          return response;
        }

        // Buscar da rede com configurações específicas para Safari
        return fetch(event.request, {
          method: event.request.method,
          headers: event.request.headers,
          mode: 'cors',
          credentials: 'same-origin',
          redirect: 'follow'
        })
          .then(response => {
            // Verificar se a resposta é válida e não é um redirecionamento
            if (!response || response.status !== 200 || response.type !== 'basic' || response.redirected) {
              return response;
            }

            // Clonar a resposta para cache
            const responseToCache = response.clone();

            // Adicionar ao cache apenas se for uma resposta válida
            if (responseToCache.status === 200) {
              caches.open(CACHE_NAME)
                .then(cache => {
                  cache.put(event.request, responseToCache);
                })
                .catch(error => {
                  console.log('Service Worker: Erro ao salvar no cache:', error);
                });
            }

            return response;
          })
          .catch(error => {
            console.log('Service Worker: Erro na requisição:', error);

            // Se for uma página HTML, mostrar página offline
            if (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html')) {
              return caches.match(OFFLINE_URL);
            }

            // Para outros recursos, retornar erro
            throw error;
          });
      })
      .catch(error => {
        console.log('Service Worker: Erro geral:', error);

        // Fallback para página offline se for HTML
        if (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html')) {
          return caches.match(OFFLINE_URL);
        }

        throw error;
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