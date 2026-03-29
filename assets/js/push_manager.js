// assets/js/push_manager.js
// Sistema completo de notificações Push para PWA (2025/2026)

(function () {
    const AppPush = {
        get publicKey() { return window.VAPID_PUBLIC_KEY || ''; },
        get apiPath() { return window.PUSH_API_PATH || 'api_push_subscribe.php'; },
        get notifPath() { return window.NOTIF_API_PATH || 'api_notificacoes.php'; },

        _isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
        _isStandalone: window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches,

        isSupported: function () {
            if (!('serviceWorker' in navigator)) return false;
            if (!('PushManager' in window)) {
                if (this._isIOS && !this._isStandalone) {
                    if (window.Swal) {
                        Swal.fire({
                            title: '📱 Instale o App',
                            html: 'Para receber notificações no iPhone, instale o app:<br><br>1. Toque em <b>Compartilhar</b> 🔗<br>2. Selecione <b>"Adicionar à Tela de Início"</b><br>3. Reabra o app instalado',
                            icon: 'info', confirmButtonText: 'Entendi'
                        });
                    }
                    return false;
                }
                return false;
            }
            return true;
        },

        getStatus: async function () {
            if (!this.isSupported()) return 'unsupported';
            const permission = Notification.permission;
            if (permission === 'denied') return 'denied';
            if (permission === 'default') return 'default';
            try {
                const sw = await navigator.serviceWorker.ready;
                const sub = await sw.pushManager.getSubscription();
                return sub ? 'subscribed' : 'granted-not-subscribed';
            } catch (e) {
                return 'error';
            }
        },

        init: async function () {
            if (!this.isSupported()) return;

            this.clearBadge();

            try {
                const sw = await navigator.serviceWorker.ready;
                let sub = await sw.pushManager.getSubscription();

                if (sub) {
                    console.log('[Push] Dispositivo já inscrito.');
                    this._updateButtonState('subscribed');
                    return;
                }

                if (Notification.permission === 'granted') {
                    await this.subscribeUser();
                } else {
                    const permission = await Notification.requestPermission();
                    if (permission === 'granted') {
                        await this.subscribeUser();
                    } else {
                        this._updateButtonState('denied');
                        console.warn('[Push] Permissão negada.');
                    }
                }
            } catch (err) {
                console.error('[Push] Erro no init:', err);
            }
        },

        subscribeUser: async function () {
            try {
                const sw = await navigator.serviceWorker.ready;
                const binaryKey = this._urlB64ToUint8Array(this.publicKey);

                const sub = await sw.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: binaryKey
                });

                const ok = await this._syncWithServer(sub, 'POST');
                if (ok) {
                    this._updateButtonState('subscribed');
                    if (window.showToast) showToast('Notificações ativas!', 'Você receberá alertas neste dispositivo.');
                    this.loadUnreadCount();
                }
            } catch (err) {
                console.error('[Push] Erro ao assinar:', err.name, err.message, err);
                let msg = 'Não foi possível ativar as notificações.';
                if (err.name === 'AbortError') {
                    msg = 'Servidor push inacessível. Tente novamente ou use outro navegador.';
                } else if (err.name === 'NotAllowedError') {
                    msg = 'Permissão negada. Verifique as configurações do navegador.';
                } else if (err.name === 'InvalidStateError') {
                    msg = 'Service Worker não está ativo ainda. Recarregue a página e tente de novo.';
                } else if (err.message) {
                    msg = err.message;
                }
                if (window.showToast) showToast('Erro (' + err.name + ')', msg, true);
                // Dispara evento para página de configuração capturar
                window.dispatchEvent(new CustomEvent('push-subscribe-error', { detail: { name: err.name, message: msg } }));
            }
        },

        unsubscribeUser: async function () {
            try {
                const sw = await navigator.serviceWorker.ready;
                const sub = await sw.pushManager.getSubscription();
                if (!sub) { this._updateButtonState('default'); return; }

                await this._syncWithServer(sub, 'DELETE');
                await sub.unsubscribe();
                this._updateButtonState('default');
                if (window.showToast) showToast('Notificações desativadas', 'Você não receberá mais alertas neste dispositivo.');
            } catch (err) {
                console.error('[Push] Erro ao desinscrever:', err);
                if (window.showToast) showToast('Erro', 'Não foi possível desativar as notificações.', true);
            }
        },

        toggle: async function () {
            const status = await this.getStatus();
            if (status === 'subscribed') {
                await this.unsubscribeUser();
            } else if (status === 'denied') {
                if (window.Swal) {
                    Swal.fire({
                        title: '🔔 Notificações Bloqueadas',
                        html: 'Você bloqueou as notificações neste navegador.<br><br>Para reativar:<br>1. Clique no cadeado 🔒 na barra de endereços<br>2. Altere Notificações para "Permitir"<br>3. Recarregue a página',
                        icon: 'warning', confirmButtonText: 'Entendi'
                    });
                }
            } else {
                await this.init();
            }
        },

        _syncWithServer: async function (sub, method) {
            try {
                const res = await fetch(this.apiPath, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(method === 'DELETE' ? { endpoint: sub.endpoint } : sub)
                });
                const data = await res.json();
                return data.success === true;
            } catch (err) {
                console.error('[Push] Erro sync server:', err);
                return false;
            }
        },

        clearBadge: function () {
            if ('clearAppBadge' in navigator) navigator.clearAppBadge().catch(() => {});
            navigator.serviceWorker.ready.then(reg => {
                if (reg.active) reg.active.postMessage({ type: 'CLEAR_BADGE' });
            }).catch(() => {});
        },

        _updateButtonState: function (status) {
            const btn = document.getElementById('push-toggle-btn');
            const icon = document.getElementById('push-toggle-icon');
            const label = document.getElementById('push-toggle-label');
            if (!btn) return;

            btn.classList.remove('btn-outline-info', 'btn-outline-danger', 'btn-outline-secondary', 'btn-success');
            icon?.classList.remove('bi-bell-fill', 'bi-bell-slash-fill', 'bi-bell');

            switch (status) {
                case 'subscribed':
                    btn.classList.add('btn-success');
                    icon?.classList.add('bi-bell-fill');
                    if (label) label.textContent = 'Notificações Ativas';
                    btn.title = 'Clique para desativar notificações';
                    break;
                case 'denied':
                    btn.classList.add('btn-outline-danger');
                    icon?.classList.add('bi-bell-slash-fill');
                    if (label) label.textContent = 'Notificações Bloqueadas';
                    btn.title = 'Notificações bloqueadas no navegador';
                    break;
                default:
                    btn.classList.add('btn-outline-info');
                    icon?.classList.add('bi-bell');
                    if (label) label.textContent = 'Ativar Notificações';
                    btn.title = 'Clique para ativar notificações push';
            }
        },

        loadUnreadCount: async function () {
            try {
                const res = await fetch(this.notifPath + '?action=count');
                if (!res.ok) return;
                const data = await res.json();
                const count = data.unread || 0;
                const badge = document.getElementById('notif-badge');
                if (badge) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = count > 0 ? 'inline-flex' : 'none';
                }
                if ('setAppBadge' in navigator && count > 0) {
                    navigator.setAppBadge(count).catch(() => {});
                }
            } catch (e) { /* silencioso */ }
        },

        loadNotifications: async function () {
            try {
                const res = await fetch(this.notifPath + '?action=list&limit=10');
                if (!res.ok) return [];
                const data = await res.json();
                return data.notifications || [];
            } catch (e) { return []; }
        },

        markAllRead: async function () {
            try {
                await fetch(this.notifPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_read' })
                });
                this.loadUnreadCount();
                const badge = document.getElementById('notif-badge');
                if (badge) badge.style.display = 'none';
            } catch (e) { /* silencioso */ }
        },

        openPanel: async function () {
            const notifications = await this.loadNotifications();
            await this.markAllRead();

            const items = notifications.length > 0
                ? notifications.map(n => `
                    <a href="${n.url || '#'}" class="notif-item ${n.lida == 0 ? 'notif-unread' : ''}" onclick="document.getElementById('notif-panel').classList.remove('show')">
                        <div class="notif-icon notif-icon-${n.tipo || 'info'}">
                            ${this._getTypeIcon(n.tipo)}
                        </div>
                        <div class="notif-body">
                            <div class="notif-title">${this._esc(n.titulo)}</div>
                            <div class="notif-msg">${this._esc(n.mensagem)}</div>
                            <div class="notif-time">${this._formatTime(n.created_at)}</div>
                        </div>
                    </a>`).join('')
                : '<div class="notif-empty"><i class="bi bi-bell-slash"></i><p>Nenhuma notificação ainda.</p></div>';

            const status = await this.getStatus();
            const isSubscribed = status === 'subscribed';

            let panel = document.getElementById('notif-panel');
            if (!panel) {
                panel = document.createElement('div');
                panel.id = 'notif-panel';
                document.body.appendChild(panel);
            }

            panel.innerHTML = `
                <div class="notif-panel-header">
                    <span><i class="bi bi-bell-fill me-2"></i>Notificações</span>
                    <button class="notif-close" onclick="document.getElementById('notif-panel').classList.remove('show')">&times;</button>
                </div>
                <div class="notif-push-status">
                    <span>${isSubscribed ? '<i class="bi bi-check-circle-fill text-success me-1"></i>Push ativo neste dispositivo' : '<i class="bi bi-exclamation-circle text-warning me-1"></i>Push inativo'}</span>
                    <button class="notif-push-btn ${isSubscribed ? 'active' : ''}" onclick="window.AppPush.toggle().then(()=>window.AppPush.openPanel())">
                        ${isSubscribed ? 'Desativar' : 'Ativar Push'}
                    </button>
                </div>
                <div class="notif-list">${items}</div>
                <div class="notif-panel-footer">
                    <a href="notificacoes_inteligentes.php">Ver todos os alertas</a>
                </div>`;

            setTimeout(() => panel.classList.add('show'), 10);

            document.addEventListener('click', function closePanel(e) {
                if (!panel.contains(e.target) && !e.target.closest('[onclick*="openPanel"]') && !e.target.closest('#notif-bell-btn')) {
                    panel.classList.remove('show');
                    document.removeEventListener('click', closePanel);
                }
            });
        },

        _getTypeIcon: function (tipo) {
            const icons = { success: '✅', warning: '⚠️', danger: '🚨', info: '💡', tarefa: '📋', financeiro: '💰' };
            return icons[tipo] || '🔔';
        },

        _esc: function (str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        _formatTime: function (dateStr) {
            if (!dateStr) return '';
            try {
                const d = new Date(dateStr);
                const now = new Date();
                const diff = Math.floor((now - d) / 1000);
                if (diff < 60) return 'Agora mesmo';
                if (diff < 3600) return Math.floor(diff / 60) + ' min atrás';
                if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
                return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            } catch (e) { return dateStr; }
        },

        _urlB64ToUint8Array: function (base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const data = window.atob(base64);
            const output = new Uint8Array(data.length);
            for (let i = 0; i < data.length; ++i) output[i] = data.charCodeAt(i);
            return output;
        }
    };

    // Expor globalmente
    window.AppPush = AppPush;

    // Estilos do painel de notificações
    const style = document.createElement('style');
    style.textContent = `
        #notif-panel {
            position: fixed; top: 0; right: -380px; width: 360px; max-width: 95vw;
            height: 100vh; background: #1a1a1e; border-left: 1px solid rgba(255,255,255,0.12);
            z-index: 9999; display: flex; flex-direction: column;
            transition: right 0.3s cubic-bezier(.4,0,.2,1);
            box-shadow: -8px 0 32px rgba(0,0,0,0.4); font-family: 'Poppins', sans-serif;
        }
        #notif-panel.show { right: 0; }
        .notif-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.2rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.1);
            font-weight: 700; font-size: 1.05rem; color: #f5f5f1; flex-shrink: 0;
            background: rgba(229,9,20,0.08);
        }
        .notif-close {
            background: none; border: none; color: #aaa; font-size: 1.5rem; cursor: pointer;
            line-height: 1; padding: 0; transition: color .2s;
        }
        .notif-close:hover { color: #fff; }
        .notif-push-status {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.75rem 1.25rem; background: rgba(255,255,255,0.04);
            border-bottom: 1px solid rgba(255,255,255,0.07); flex-shrink: 0;
            font-size: 0.8rem; color: #aaa;
        }
        .notif-push-btn {
            background: rgba(229,9,20,0.2); border: 1px solid rgba(229,9,20,0.4);
            color: #e50914; border-radius: 6px; padding: 4px 12px; font-size: 0.78rem;
            cursor: pointer; transition: all .2s; font-weight: 600;
        }
        .notif-push-btn.active { background: rgba(40,167,69,0.2); border-color: rgba(40,167,69,0.4); color: #28a745; }
        .notif-push-btn:hover { opacity: 0.8; }
        .notif-list { flex: 1; overflow-y: auto; padding: 0.5rem 0; }
        .notif-item {
            display: flex; gap: 0.85rem; padding: 0.9rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none;
            color: #f5f5f1; transition: background .15s; align-items: flex-start;
        }
        .notif-item:hover { background: rgba(255,255,255,0.05); }
        .notif-item.notif-unread { background: rgba(229,9,20,0.06); border-left: 3px solid #e50914; }
        .notif-icon { font-size: 1.4rem; flex-shrink: 0; margin-top: 2px; }
        .notif-body { flex: 1; min-width: 0; }
        .notif-title { font-weight: 600; font-size: 0.88rem; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-msg { font-size: 0.8rem; color: #aaa; margin-bottom: 4px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notif-time { font-size: 0.72rem; color: #666; }
        .notif-empty { text-align: center; padding: 3rem 1rem; color: #555; }
        .notif-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }
        .notif-panel-footer {
            padding: 1rem 1.25rem; border-top: 1px solid rgba(255,255,255,0.1); flex-shrink: 0;
            text-align: center;
        }
        .notif-panel-footer a {
            color: #e50914; text-decoration: none; font-size: 0.85rem; font-weight: 600;
        }
        .notif-panel-footer a:hover { opacity: 0.8; }
        #notif-badge {
            position: absolute; top: -4px; right: -6px; background: #e50914; color: #fff;
            border-radius: 10px; min-width: 18px; height: 18px; font-size: 0.65rem;
            font-weight: 700; display: inline-flex; align-items: center; justify-content: center;
            padding: 0 4px; line-height: 1;
        }
    `;
    document.head.appendChild(style);

    // Auto-inicializar ao carregar
    document.addEventListener('DOMContentLoaded', async () => {
        const status = await AppPush.getStatus();
        AppPush._updateButtonState(status === 'subscribed' ? 'subscribed' : status === 'denied' ? 'denied' : 'default');

        if (status === 'granted-not-subscribed') {
            await AppPush.subscribeUser();
        } else if (status === 'subscribed') {
            AppPush.loadUnreadCount();
        }
    });

})();
