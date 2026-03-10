// push_manager.js
// Lógica para pedir permissão ao usuário e assinar notificações push nativas VAPID

const PushManager = {
    // Atenção: A CHAVE PÚBLICA DEVE REPOSITAR DO SEU config_push.php
    // Esta constante será substituída pelo PHP em tempo de execução 
    // com base no window.VAPID_PUBLIC_KEY se definida, ou codificada
    applicationServerKey: window.VAPID_PUBLIC_KEY || '',

    init: async function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push messaging is not supported');
            return;
        }

        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.subscribeUser();
            } else {
                console.log('Permissão para notificações negada.');
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
            console.error('Chave VAPID_PUBLIC_KEY não configurada no window.');
            return;
        }

        try {
            const swRegistration = await navigator.serviceWorker.ready;
            const applicationServerKey = this.urlB64ToUint8Array(this.applicationServerKey);

            const subscription = await swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });

            console.log('User is subscribed:', subscription);
            await this.sendSubscriptionToBackEnd(subscription);

        } catch (err) {
            console.error('Failed to subscribe the user: ', err);
        }
    },

    sendSubscriptionToBackEnd: async function (subscription) {
        try {
            const response = await fetch('/api_push_subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'subscribe',
                    subscription: subscription.toJSON()
                })
            });

            if (!response.ok) {
                throw new Error('Bad status code from server.');
            }

            const responseData = await response.json();
            if (!responseData.success) {
                console.error('Erro salvo banco: ', responseData.message);
            } else {
                console.log('Subscription salva com sucesso no BD.');
            }
        } catch (err) {
            console.error('Erro ao enviar subscription para o backend:', err);
        }
    }
};
