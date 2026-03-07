<?php
header('Content-Type: application/javascript');

/**
 * sw.php - Service Worker Dinâmico para PWA
 * Este arquivo gera automaticamente a versão do cache baseada no timestamp dos arquivos.
 */

$filesToWatch = [
    'dashboard.php',
    'tarefas.php',
    'assets/css/dashboard.css',
    'tarefas.css',
    'assets/js/dashboard.js',
    'index.php'
];

$maxVersion = 0;
foreach ($filesToWatch as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $maxVersion = max($maxVersion, filemtime($path));
    }
}

// Se não encontrar nenhum arquivo, usa a hora atual (fallback seguro)
if ($maxVersion === 0) $maxVersion = time();

$cacheName = "painel-auto-v-" . $maxVersion;
?>

// sw.php - Gerado dinamicamente
const CACHE_NAME = '<?= $cacheName ?>';
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
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(CORE_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Ativar service worker e limpar caches antigos
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME && cacheName.startsWith('painel-')) {
            console.log('SW: Limpando cache antigo:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Interceptar requisições
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  const isLocal = url.origin === self.location.origin;

  // Ignorar requisições de API e processos
  if (url.pathname.includes('/api/') || url.pathname.includes('salvar_') || url.pathname.includes('logout.php')) {
    return;
  }

  // ESTRATÉGIA: Network First para arquivos do sistema para garantir atualização imediata
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

  // ESTRATÉGIA: Cache First para o resto
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request).then(fetchRes => {
        if (!fetchRes || fetchRes.status !== 200) return fetchRes;
        const resClone = fetchRes.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, resClone));
        return fetchRes;
      });
    }).catch(() => {
      if (event.request.headers.get('accept').includes('text/html')) {
        return caches.match(OFFLINE_URL);
      }
    })
  );
});

// Listener para skipWaiting manual se necessário
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
