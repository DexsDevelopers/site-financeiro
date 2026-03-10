// assets/js/push_manager.js
// Lógica para pedir permissão ao usuário e assinar notificações push nativas VAPID

(function () {
    const PushManager = {
        // A chave será passada pelo window.VAPID_PUBLIC_KEY configurado no footer/header
        applicationServerKey: window.VAPID_PUBLIC_KEY || '',

        // Caminho da API
        apiPath: window.PUSH_API_PATH || 'api_push_subscribe.php',

        init: async function () {
            console.log('Iniciando PushManager...');

            if (!('serviceWorker' in navigator)) {
                console.warn('Service Worker não suportado neste navegador.');
                return;
            }

            if (!('PushManager' in window)) {
                console.warn('Push Manager não suportado (ex: iOS fora da Home Screen).');
                if (/iPhone|iPad|iPod/.test(navigator.userAgent) && !window.navigator.standalone) {
                    alert('No iPhone, as notificações só funcionam se você instalar o App (clique em Compartilhar > Adicionar à Tela de Início).');
                }
                return;
            }

            try {
                const permission = await Notification.requestPermission();
                console.log('Status da permissão:', permission);

                if (permission === 'granted') {
                    this.subscribeUser();
                } else {
                    console.warn('Permissão negada.');
                    if (typeof showToast === 'function') {
                        showToast('Aviso', 'Você bloqueou as notificações. Ative-as nas configurações.', true);
                    }
                }
            } catch (error) {
                console.error('Erro ao pedir permissão', error);
            }
        },

        urlB64ToUint8Array: function (base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },

        subscribeUser: async function () {
            if (!this.applicationServerKey) {
                console.error('VAPID_PUBLIC_KEY não configurada.');
                return;
            }

            try {
                const swRegistration = await navigator.serviceWorker.ready;
                const applicationServerKey = this.urlB64ToUint8Array(this.applicationServerKey);

                const subscription = await swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey
                });

                console.log('Inscrito:', subscription);
                await this.sendSubscriptionToBackEnd(subscription);

            } catch (err) {
                console.error('Erro ao assinar:', err);
                if (typeof showToast === 'function') {
                    showToast('Erro', 'Não foi possível assinar o serviço de Push.', true);
                }
            }
        },

        sendSubscriptionToBackEnd: async function (subscription) {
            try {
                const response = await fetch(this.apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(subscription.toJSON())
                });

                const responseData = await response.json();
                if (!responseData.success) {
                    console.error('Erro salvo banco:', responseData.message);
                    if (typeof showToast === 'function') showToast('Erro', responseData.message, true);
                } else {
                    console.log('Token Push salvo.');
                    if (typeof showToast === 'function') showToast('Sucesso', 'Notificações ativadas!');
                }
            } catch (err) {
                console.error('Erro de conexão:', err);
                if (typeof showToast === 'function') showToast('Erro', 'Não foi possível salvar seu token.', true);
            }
        }
    };

    // Tornar global explicitamente
    window.PushManager = PushManager;
})();
