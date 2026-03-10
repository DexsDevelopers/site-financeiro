// assets/js/push_manager.js
// Lógica Elite de Notificações Push (2025/2026)

(function () {
    const PushManager = {
        get publicKey() { return window.VAPID_PUBLIC_KEY || ''; },
        get apiPath() { return window.PUSH_API_PATH || '/seu_projeto/api_push_subscribe.php'; },

        /**
         * Inicializa o sistema de push ou resubscreve se necessário
         */
        init: async function () {
            if (!this.isSupported()) return;

            try {
                const sw = await navigator.serviceWorker.ready;
                let sub = await sw.pushManager.getSubscription();

                // Se já tem inscrição, limpa o badge e sai
                if (sub) {
                    console.log('[Push] Dispositivo já inscrito.');
                    this.clearBadge();
                    return;
                }

                // Pedir permissão e inscrever
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    await this.subscribeUser();
                } else {
                    console.warn('[Push] Permissão negada.');
                }
            } catch (err) {
                console.error('[Push] Erro no init:', err);
            }
        },

        isSupported: function () {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
                const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;
                if (isIOS && !isStandalone) {
                    Swal.fire('Instale o App!', 'No iPhone, clique em "Compartilhar" > "Adicionar à Tela de Início" para receber alertas.', 'info');
                }
                return false;
            }
            return true;
        },

        subscribeUser: async function () {
            try {
                const sw = await navigator.serviceWorker.ready;
                const binaryKey = this.urlB64ToUint8Array(this.publicKey);

                const sub = await sw.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: binaryKey
                });

                await this.syncWithServer(sub);
            } catch (err) {
                console.error('[Push] Erro ao assinar:', err);
                showToast('Erro', 'Não foi possível ativar as notificações.', true);
            }
        },

        syncWithServer: async function (sub) {
            try {
                await fetch(this.apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(sub)
                });
                showToast('Sucesso', 'Notificações ativadas para este dispositivo!');
            } catch (err) {
                console.error('[Push] Erro sync:', err);
            }
        },

        clearBadge: function () {
            if ('clearAppBadge' in navigator) {
                navigator.clearAppBadge().catch(() => { });
            }
            // Informar ao Service Worker para limpar também
            navigator.serviceWorker.ready.then(reg => {
                if (reg.active) reg.active.postMessage({ type: 'CLEAR_BADGE' });
            });
        },

        urlB64ToUint8Array: function (base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const data = window.atob(base64);
            const output = new Uint8Array(data.length);
            for (let i = 0; i < data.length; ++i) output[i] = data.charCodeAt(i);
            return output;
        }
    };

    window.PushManager = PushManager;

    // Auto-exec Check ao carregar a página
    document.addEventListener('DOMContentLoaded', () => {
        if (Notification.permission === 'granted') {
            PushManager.init();
        }
    });

})();
