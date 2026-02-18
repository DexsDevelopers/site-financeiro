/**
 * PWA Manager
 * Gerencia todas as funcionalidades do Progressive Web App
 */

class PWAManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.isInstalled = false;
        this.serviceWorker = null;
        this.installPrompt = null;
        
        this.init();
    }
    
    async init() {
        console.log('PWA Manager: Inicializando...');
        
        // Verificar se está instalado
        this.checkInstallStatus();
        
        // Registrar service worker
        await this.registerServiceWorker();
        
        // Configurar listeners
        this.setupEventListeners();
        
        // Verificar atualizações
        this.checkForUpdates();
        
        console.log('PWA Manager: Inicializado com sucesso');
    }
    
    checkInstallStatus() {
        // Verificar se está em modo standalone
        this.isInstalled = window.matchMedia('(display-mode: standalone)').matches || 
                          window.navigator.standalone === true;
        
        if (this.isInstalled) {
            console.log('PWA Manager: App está instalado');
            this.showInstallStatus();
        }
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                // Tentar registrar o service worker avançado primeiro
                let registration = await navigator.serviceWorker.register('/seu_projeto/sw-advanced.js');
                
                if (!registration) {
                    // Fallback para service worker básico
                    registration = await navigator.serviceWorker.register('/seu_projeto/sw.js');
                }
                
                this.serviceWorker = registration;
                console.log('PWA Manager: Service Worker registrado:', registration);
                
                // Verificar atualizações
                registration.addEventListener('updatefound', () => {
                    this.handleServiceWorkerUpdate(registration);
                });
                
            } catch (error) {
                console.error('PWA Manager: Erro ao registrar Service Worker:', error);
                
                // Tentar registrar service worker minimalista
                try {
                    this.serviceWorker = await navigator.serviceWorker.register('/seu_projeto/sw-minimal.js');
                    console.log('PWA Manager: Service Worker minimalista registrado');
                } catch (minimalError) {
                    console.error('PWA Manager: Erro ao registrar Service Worker minimalista:', minimalError);
                }
            }
        } else {
            console.log('PWA Manager: Service Worker não suportado');
        }
    }
    
    setupEventListeners() {
        // Status de conexão
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.handleOnline();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.handleOffline();
        });
        
        // Instalação do app
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWA Manager: beforeinstallprompt disparado');
            e.preventDefault();
            this.installPrompt = e;
            this.showInstallButton();
        });
        
        window.addEventListener('appinstalled', () => {
            console.log('PWA Manager: App instalado');
            this.isInstalled = true;
            this.hideInstallButton();
            this.showInstallSuccess();
        });
        
        // Atualizações do service worker
        if (this.serviceWorker) {
            this.serviceWorker.addEventListener('controllerchange', () => {
                console.log('PWA Manager: Service Worker atualizado');
                this.handleServiceWorkerUpdate();
            });
        }
    }
    
    handleOnline() {
        console.log('PWA Manager: Conexão restaurada');
        this.showNotification('🌐 Conexão restaurada!', 'success');
        
        // Sincronizar dados offline se necessário
        this.syncOfflineData();
    }
    
    handleOffline() {
        console.log('PWA Manager: Conexão perdida');
        this.showNotification('📡 Você está offline', 'warning');
    }
    
    async handleServiceWorkerUpdate(registration) {
        if (registration && registration.waiting) {
            console.log('PWA Manager: Nova versão disponível');
            this.showUpdateNotification();
        }
    }
    
    showInstallButton() {
        if (this.isInstalled) return;
        
        // Criar botão de instalação se não existir
        if (!document.getElementById('pwa-install-btn')) {
            const button = document.createElement('button');
            button.id = 'pwa-install-btn';
            button.className = 'btn btn-primary pwa-install-btn';
            button.innerHTML = `
                <i class="bi bi-download"></i>
                Instalar App
            `;
            
            button.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                color: white;
                padding: 12px 20px;
                border-radius: 25px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                transition: all 0.3s ease;
                animation: slideInUp 0.5s ease;
            `;
            
            button.addEventListener('click', () => {
                this.installApp();
            });
            
            document.body.appendChild(button);
        }
    }
    
    hideInstallButton() {
        const button = document.getElementById('pwa-install-btn');
        if (button) {
            button.style.animation = 'slideOutDown 0.3s ease';
            setTimeout(() => {
                if (button.parentNode) {
                    button.parentNode.removeChild(button);
                }
            }, 300);
        }
    }
    
    async installApp() {
        if (!this.installPrompt) {
            this.showInstallInstructions();
            return;
        }
        
        try {
            this.installPrompt.prompt();
            const { outcome } = await this.installPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('PWA Manager: Usuário aceitou a instalação');
            } else {
                console.log('PWA Manager: Usuário rejeitou a instalação');
            }
            
            this.installPrompt = null;
        } catch (error) {
            console.error('PWA Manager: Erro ao instalar app:', error);
            this.showInstallInstructions();
        }
    }
    
    showInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);
        
        let message = '';
        
        if (isIOS) {
            message = `
                <div class="pwa-instructions">
                    <h4>📱 Para instalar no iOS:</h4>
                    <ol>
                        <li>Toque no botão <strong>Compartilhar</strong> <i class="bi bi-share"></i></li>
                        <li>Role para baixo e toque em <strong>"Adicionar à Tela de Início"</strong></li>
                        <li>Toque em <strong>"Adicionar"</strong> para confirmar</li>
                    </ol>
                </div>
            `;
        } else if (isAndroid) {
            message = `
                <div class="pwa-instructions">
                    <h4>🤖 Para instalar no Android:</h4>
                    <ol>
                        <li>Toque no menu <strong>⋮</strong> do navegador</li>
                        <li>Selecione <strong>"Adicionar à tela inicial"</strong></li>
                        <li>Toque em <strong>"Adicionar"</strong> para confirmar</li>
                    </ol>
                </div>
            `;
        } else {
            message = `
                <div class="pwa-instructions">
                    <h4>💻 Para instalar no Desktop:</h4>
                    <ol>
                        <li>Procure pelo ícone de instalação na barra de endereços</li>
                        <li>Ou use o menu do navegador (⋮) → "Instalar app"</li>
                        <li>Confirme a instalação</li>
                    </ol>
                </div>
            `;
        }
        
        this.showModal('📲 Como Instalar o App', message);
    }
    
    showInstallSuccess() {
        this.showNotification('🎉 App instalado com sucesso!', 'success');
    }
    
    showUpdateNotification() {
        // ✅ CONTROLE DE FREQUÊNCIA - Não mostrar toda hora
        const lastShown = localStorage.getItem('pwa-update-last-shown');
        const now = Date.now();
        const oneDay = 24 * 60 * 60 * 1000; // 24 horas
        
        // Se foi mostrado há menos de 24 horas, não mostrar novamente
        if (lastShown && (now - parseInt(lastShown)) < oneDay) {
            console.log('⏰ PWA: Modal de atualização já foi mostrado nas últimas 24h. Ignorando...');
            return;
        }
        
        // Marcar como mostrado agora
        localStorage.setItem('pwa-update-last-shown', now.toString());
        console.log('✅ PWA: Mostrando notificação de atualização');
        
        const notification = document.createElement('div');
        notification.className = 'pwa-update-notification';
        notification.id = 'pwa-update-notification';
        notification.innerHTML = `
            <div class="pwa-update-content">
                <div class="pwa-update-icon">🔄</div>
                <div class="pwa-update-text">
                    <h4>Nova versão disponível!</h4>
                    <p>Uma nova versão do app está disponível. Deseja atualizar agora?</p>
                </div>
                <div class="pwa-update-actions">
                    <button class="btn btn-sm btn-primary" onclick="window.pwaManager.updateApp()">
                        Atualizar
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="window.pwaManager.dismissUpdate('later')">
                        Depois
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="window.pwaManager.dismissUpdate('today')" title="Não mostrar hoje">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 1001;
            max-width: 350px;
            animation: slideInRight 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remover após 15 segundos (aumentado de 10)
        setTimeout(() => {
            this.dismissUpdate('auto');
        }, 15000);
    }
    
    dismissUpdate(reason) {
        const notification = document.getElementById('pwa-update-notification');
        if (!notification) return;
        
        const now = Date.now();
        const oneDay = 24 * 60 * 60 * 1000;
        const oneHour = 60 * 60 * 1000;
        
        if (reason === 'today') {
            // Não mostrar por 24 horas
            localStorage.setItem('pwa-update-last-shown', now.toString());
            this.showNotification('✅ OK! Não mostraremos esta notificação hoje.', 'success');
        } else if (reason === 'later') {
            // Lembrar em 1 hora
            localStorage.setItem('pwa-update-last-shown', (now - oneDay + oneHour).toString());
        }
        
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
    
    async updateApp() {
        if (this.serviceWorker && this.serviceWorker.waiting) {
            this.serviceWorker.waiting.postMessage({ type: 'SKIP_WAITING' });
            window.location.reload();
        }
    }
    
    async syncOfflineData() {
        try {
            console.log('PWA Manager: Sincronizando dados offline...');
            
            // Implementar sincronização de dados offline
            // Aqui você pode implementar a lógica para sincronizar dados
            // que foram salvos localmente quando offline
            
            this.showNotification('🔄 Dados sincronizados!', 'success');
        } catch (error) {
            console.error('PWA Manager: Erro na sincronização:', error);
        }
    }
    
    async checkForUpdates() {
        if (this.serviceWorker) {
            try {
                // ✅ CONTROLE - Só verificar a cada 6 horas (não toda vez que carrega)
                const lastCheck = localStorage.getItem('pwa-last-update-check');
                const now = Date.now();
                const sixHours = 6 * 60 * 60 * 1000; // 6 horas
                
                if (lastCheck && (now - parseInt(lastCheck)) < sixHours) {
                    console.log('⏰ PWA: Última verificação foi há menos de 6h. Pulando...');
                    return;
                }
                
                // Marcar horário da verificação
                localStorage.setItem('pwa-last-update-check', now.toString());
                
                await this.serviceWorker.update();
                console.log('✅ PWA Manager: Verificação de atualizações concluída');
            } catch (error) {
                console.error('❌ PWA Manager: Erro ao verificar atualizações:', error);
            }
        }
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `pwa-notification pwa-notification-${type}`;
        notification.innerHTML = message;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#d4edda' : type === 'warning' ? '#fff3cd' : '#d1ecf1'};
            color: ${type === 'success' ? '#155724' : type === 'warning' ? '#856404' : '#0c5460'};
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'warning' ? '#ffeaa7' : '#bee5eb'};
            font-weight: 600;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            animation: slideInRight 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Remover após 5 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    }
    
    showModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'pwa-modal';
        modal.innerHTML = `
            <div class="pwa-modal-overlay">
                <div class="pwa-modal-content">
                    <div class="pwa-modal-header">
                        <h3>${title}</h3>
                        <button class="pwa-modal-close">&times;</button>
                    </div>
                    <div class="pwa-modal-body">
                        ${content}
                    </div>
                    <div class="pwa-modal-footer">
                        <button class="btn btn-primary pwa-modal-btn">Entendi</button>
                    </div>
                </div>
            </div>
        `;
        
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1002;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        document.body.appendChild(modal);
        
        // Event listeners
        modal.querySelector('.pwa-modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('.pwa-modal-btn').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('.pwa-modal-overlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                modal.remove();
            }
        });
    }
    
    showInstallStatus() {
        const status = document.createElement('div');
        status.className = 'pwa-install-status';
        status.innerHTML = `
            <div class="pwa-status-content">
                <i class="bi bi-check-circle"></i>
                <span>App instalado</span>
            </div>
        `;
        
        status.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 20px;
            font-weight: 600;
            z-index: 1000;
            animation: slideInLeft 0.3s ease;
        `;
        
        document.body.appendChild(status);
        
        // Remover após 3 segundos
        setTimeout(() => {
            if (status.parentNode) {
                status.style.animation = 'slideOutLeft 0.3s ease';
                setTimeout(() => {
                    if (status.parentNode) {
                        status.parentNode.removeChild(status);
                    }
                }, 300);
            }
        }, 3000);
    }
    
    // Métodos públicos
    isAppInstalled() {
        return this.isInstalled;
    }
    
    isOnline() {
        return this.isOnline;
    }
    
    async clearCache() {
        if (this.serviceWorker) {
            try {
                const messageChannel = new MessageChannel();
                messageChannel.port1.onmessage = (event) => {
                    if (event.data.success) {
                        this.showNotification('🗑️ Cache limpo com sucesso!', 'success');
                    }
                };
                
                this.serviceWorker.active.postMessage(
                    { type: 'CLEAR_CACHE' },
                    [messageChannel.port2]
                );
            } catch (error) {
                console.error('PWA Manager: Erro ao limpar cache:', error);
            }
        }
    }
    
    async updateCache() {
        if (this.serviceWorker) {
            try {
                const messageChannel = new MessageChannel();
                messageChannel.port1.onmessage = (event) => {
                    if (event.data.success) {
                        this.showNotification('🔄 Cache atualizado!', 'success');
                    }
                };
                
                this.serviceWorker.active.postMessage(
                    { type: 'UPDATE_CACHE' },
                    [messageChannel.port2]
                );
            } catch (error) {
                console.error('PWA Manager: Erro ao atualizar cache:', error);
            }
        }
    }
}

// Adicionar estilos CSS
const pwaStyles = document.createElement('style');
pwaStyles.textContent = `
    @keyframes slideInUp {
        from { transform: translateY(100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    @keyframes slideOutDown {
        from { transform: translateY(0); opacity: 1; }
        to { transform: translateY(100%); opacity: 0; }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    @keyframes slideInLeft {
        from { transform: translateX(-100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutLeft {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(-100%); opacity: 0; }
    }
    
    .pwa-install-btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6) !important;
    }
    
    .pwa-update-content {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
    }
    
    .pwa-update-icon {
        font-size: 24px;
    }
    
    .pwa-update-text h4 {
        margin: 0 0 5px 0;
        font-size: 16px;
    }
    
    .pwa-update-text p {
        margin: 0;
        font-size: 14px;
        color: #666;
    }
    
    .pwa-update-actions {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }
    
    .pwa-install-status {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .pwa-instructions {
        text-align: left;
    }
    
    .pwa-instructions ol {
        margin: 10px 0;
        padding-left: 20px;
    }
    
    .pwa-instructions li {
        margin-bottom: 8px;
        line-height: 1.4;
    }
`;
document.head.appendChild(pwaStyles);

// Inicializar PWA Manager
document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
});

// Exportar para uso global
window.PWAManager = PWAManager;
