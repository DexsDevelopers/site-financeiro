// sw-safari.js - Service Worker otimizado para Safari

const CACHE_NAME = 'painel-financeiro-safari-v1.0.0';
const OFFLINE_URL = '/seu_projeto/offline.html';

// Recursos essenciais para cache (apenas recursos estáticos)
const STATIC_ASSETS = [
  '/seu_projeto/',
  '/seu_projeto/dashboard.php',
  '/seu_projeto/index.php',
  '/seu_projeto/offline.html',
  '/seu_projeto/manifest.json'
];

// Instalar service worker
self.addEventListener('install', event => {
  console.log('Service Worker Safari: Instalando...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker Safari: Cache aberto');
        // Adicionar apenas recursos estáticos essenciais
        return cache.addAll(STATIC_ASSETS.filter(url => {
          // Filtrar apenas URLs válidas
          try {
            new URL(url, self.location.origin);
            return true;
          } catch (e) {
            return false;
          }
        }));
      })
      .then(() => {
        console.log('Service Worker Safari: Instalação concluída');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker Safari: Erro na instalação:', error);
      })
  );
});

// Ativar service worker
self.addEventListener('activate', event => {
  console.log('Service Worker Safari: Ativando...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('Service Worker Safari: Removendo cache antigo:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker Safari: Ativação concluída');
        return self.clients.claim();
      })
  );
});

// Interceptar requisições (versão simplificada para Safari)
self.addEventListener('fetch', event => {
  // Ignorar requisições que não são GET
  if (event.request.method !== 'GET') {
    return;
  }

  // Ignorar requisições de API, formulários e redirecionamentos
  if (event.request.url.includes('salvar_') || 
      event.request.url.includes('atualizar_') || 
      event.request.url.includes('excluir_') ||
      event.request.url.includes('login_process.php') ||
      event.request.url.includes('logout.php') ||
      event.request.url.includes('registrar.php') ||
      event.request.url.includes('upload.php') ||
      event.request.url.includes('/api/') ||
      event.request.redirect !== 'follow') {
    return;
  }

  // Ignorar recursos externos que podem causar problemas no Safari
  if (event.request.url.includes('googleapis.com') ||
      event.request.url.includes('gstatic.com') ||
      event.request.url.includes('onesignal.com') ||
      event.request.url.includes('cdn.jsdelivr.net') ||
      event.request.url.includes('cdnjs.cloudflare.com')) {
    return;
  }

  // Apenas para páginas HTML principais
  if (!event.request.url.includes('.php') && !event.request.url.endsWith('/')) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Retornar do cache se disponível
        if (response) {
          console.log('Service Worker Safari: Servindo do cache:', event.request.url);
          return response;
        }

        // Buscar da rede com configurações específicas para Safari
        return fetch(event.request, {
          method: 'GET',
          mode: 'same-origin',
          credentials: 'same-origin',
          redirect: 'follow'
        })
          .then(response => {
            // Verificar se a resposta é válida
            if (!response || response.status !== 200 || response.type !== 'basic') {
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
                  console.log('Service Worker Safari: Erro ao salvar no cache:', error);
                });
            }

            return response;
          })
          .catch(error => {
            console.log('Service Worker Safari: Erro na requisição:', error);
            
            // Se for uma página HTML, mostrar página offline
            if (event.request.headers.get('accept') && 
                event.request.headers.get('accept').includes('text/html')) {
              return caches.match(OFFLINE_URL);
            }
            
            // Para outros recursos, retornar erro
            throw error;
          });
      })
      .catch(error => {
        console.log('Service Worker Safari: Erro geral:', error);
        
        // Fallback para página offline se for HTML
        if (event.request.headers.get('accept') && 
            event.request.headers.get('accept').includes('text/html')) {
          return caches.match(OFFLINE_URL);
        }
        
        throw error;
      })
  );
});

// Mensagens do cliente
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({ version: CACHE_NAME });
  }
});
