// assets/js/push_manager.js
// Lógica para pedir permissão ao usuário e assinar notificações push nativas VAPID

const PushManager = {
    // A chave será passada pelo window.VAPID_PUBLIC_KEY configurado no footer/header
    applicationServerKey: window.VAPID_PUBLIC_KEY || '',

    // Caminho da API (pode ser ajustado se o site estiver em subpasta)
    apiPath: window.PUSH_API_PATH || 'api_push_subscribe.php',

    init: async function () {
        console.log('Iniciando PushManager...');

        if (!('serviceWorker' in navigator)) {
            console.warn('Service Worker não suportado neste navegador.');
            return;
        }

        if (!('PushManager' in window)) {
            console.warn('Push Manager não suportado (comum em iOS Safari fora da Home Screen).');
            // No iOS, Push só funciona se o PWA estiver instalado ("Adicionar à Tela de Início")
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
                console.warn('Permissão para notificações negada pelo usuário.');
                if (typeof showToast === 'function') {
                    showToast('Aviso', 'Você bloqueou as notificações. Ative-as nas configurações do seu navegador.', true);
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
            console.error('Chave VAPID_PUBLIC_KEY não encontrada.');
            return;
        }

        try {
            const swRegistration = await navigator.serviceWorker.ready;
            const applicationServerKey = this.urlB64ToUint8Array(this.applicationServerKey);

            const subscription = await swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });

            console.log('Usuário inscrito com sucesso:', subscription);
            await this.sendSubscriptionToBackEnd(subscription);

        } catch (err) {
            console.error('Falha ao inscrever o usuário:', err);
            if (typeof showToast === 'function') {
                showToast('Erro', 'Falha técnica ao assinar o serviço de Push.', true);
            }
        }
    },

    sendSubscriptionToBackEnd: async function (subscription) {
        const subData = subscription.toJSON();

        try {
            // Enviamos o objeto retornado pelo toJSON diretamente, que contém endpoint e keys
            const response = await fetch(this.apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(subData)
            });

            if (!response.ok) {
                throw new Error('Erro na resposta do servidor (' + response.status + ')');
            }

            const responseData = await response.json();
            if (!responseData.success) {
                console.error('Erro ao salvar no banco:', responseData.message);
                if (typeof showToast === 'function') showToast('Erro', responseData.message, true);
            } else {
                console.log('Token Push salvo no banco com sucesso.');
                if (typeof showToast === 'function') showToast('Sucesso', 'Notificações ativadas neste dispositivo!');
            }
        } catch (err) {
            console.error('Erro de conexão ao salvar token:', err);
            if (typeof showToast === 'function') showToast('Erro de Conexão', 'Não foi possível salvar o token de notificação.', true);
        }
    }
};
