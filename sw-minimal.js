// sw-minimal.js - Service Worker minimalista para Safari

const CACHE_NAME = 'painel-financeiro-minimal-v1.0.0';

// Instalar service worker
self.addEventListener('install', event => {
  console.log('Service Worker Minimal: Instalando...');
  self.skipWaiting();
});

// Ativar service worker
self.addEventListener('activate', event => {
  console.log('Service Worker Minimal: Ativando...');
  event.waitUntil(self.clients.claim());
});

// Interceptar requisições (versão minimalista)
self.addEventListener('fetch', event => {
  // Apenas para páginas HTML principais
  if (event.request.method === 'GET' && 
      event.request.url.includes('.php') && 
      !event.request.url.includes('salvar_') &&
      !event.request.url.includes('atualizar_') &&
      !event.request.url.includes('excluir_') &&
      !event.request.url.includes('login_process.php') &&
      !event.request.url.includes('logout.php')) {
    
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          // Se falhar, retornar uma resposta básica
          return new Response('Página não disponível offline', {
            status: 200,
            headers: { 'Content-Type': 'text/html' }
          });
        })
    );
  }
});
