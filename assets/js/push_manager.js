// assets/js/push_manager.js
// Lógica robusta para Notificações Push VAPID com foco em Mobile/PWA

(function () {
    const PushManager = {
        // As chaves e caminhos são injetados pelo window.VAPID_PUBLIC_KEY e window.PUSH_API_PATH no footer
        get applicationServerKey() { return window.VAPID_PUBLIC_KEY || ''; },
        get apiPath() { return window.PUSH_API_PATH || 'api_push_subscribe.php'; },

        init: async function () {
            console.log('%c [Push] Iniciando Setup...', 'color: #00e5ff; font-weight: bold;');

            // 1. Verificar suporte básico do Service Worker
            if (!('serviceWorker' in navigator)) {
                console.error('[Push] SW não suportado neste navegador.');
                alert('Seu navegador não suporta notificações. Tente o Chrome ou Safari atualizado.');
                return;
            }

            // 2. Verificar se o PushManager existe (iOS só permite isso no modo Standalone/Instalado)
            if (!('PushManager' in window)) {
                console.warn('[Push] PushManager não encontrado no window.');

                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;

                if (isIOS && !isStandalone) {
                    Swal.fire({
                        title: 'Atenção (iPhone/iOS)',
                        text: 'Para receber notificações no iPhone, você precisa: 1. Clicar no botão Compartilhar (baixo) -> 2. "Adicionar à Tela de Início" -> 3. Abrir o App pelo ícone na sua tela.',
                        icon: 'info',
                        confirmButtonText: 'Entendi'
                    });
                } else {
                    alert('Este navegador não suporta Notificações Push ou você está em uma aba privada.');
                }
                return;
            }

            try {
                console.log('[Push] Solicitando permissão...');
                const permission = await Notification.requestPermission();
                console.log('[Push] Status da permissão:', permission);

                if (permission === 'granted') {
                    this.subscribeUser();
                } else if (permission === 'denied') {
                    showToast('Permissão Negada', 'Você bloqueou as notificações nas configurações do navegador.', true);
                }
            } catch (error) {
                console.error('[Push] Erro ao pedir permissão:', error);
                alert('Erro ao tentar habilitar notificações: ' + error.message);
            }
        },

        urlB64ToUint8Array: function (base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) { outputArray[i] = rawData.charCodeAt(i); }
            return outputArray;
        },

        subscribeUser: async function () {
            if (!this.applicationServerKey) {
                console.error('[Push] Chave VAPID pública não encontrada.');
                return;
            }

            try {
                // Registrar/Aguardar Service Worker
                const swRegistration = await navigator.serviceWorker.ready;
                console.log('[Push] Service Worker pronto.');

                const applicationServerKey = this.urlB64ToUint8Array(this.applicationServerKey);

                // Subscrever
                const subscription = await swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                });

                console.log('[Push] Dispositivo inscrito:', subscription.endpoint);
                await this.sendSubscriptionToBackEnd(subscription);

            } catch (err) {
                console.error('[Push] Falha ao assinar push:', err);
                if (typeof showToast === 'function') {
                    showToast('Erro Técnico', 'Não foi possível conectar com o servidor da Google/Apple para notificações.', true);
                }
            }
        },

        sendSubscriptionToBackEnd: async function (subscription) {
            const payload = subscription.toJSON();
            console.log('[Push] Enviando token para o backend:', this.apiPath);

            try {
                const response = await fetch(this.apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) throw new Error('Erro HTTP ' + response.status);

                const data = await response.json();
                if (data.success) {
                    console.info('[Push] Sucesso: Token salvo no banco.');
                    if (typeof showToast === 'function') showToast('Sucesso!', 'Notificações ativadas neste dispositivo.');
                } else {
                    throw new Error(data.message || 'Erro desconhecido no servidor');
                }
            } catch (err) {
                console.error('[Push] Erro ao salvar no banco:', err);
                if (typeof showToast === 'function') {
                    showToast('Erro de Conexão', 'Token gerado, mas não salvo no banco: ' + err.message, true);
                }
            }
        }
    };

    // Exportar Global
    window.PushManager = PushManager;
    console.log('[Push] PushManager carregado globalmente.');
})();
