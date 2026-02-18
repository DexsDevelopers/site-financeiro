/**
 * PWA Integration
 * Integra todos os componentes do Progressive Web App
 */

class PWAIntegration {
    constructor() {
        this.components = {
            manager: null,
            installPrompt: null,
            offlineStorage: null
        };
        
        this.init();
    }
    
    async init() {
        console.log('PWA Integration: Inicializando...');
        
        // Aguardar carregamento do DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
        } else {
            this.initializeComponents();
        }
    }
    
    async initializeComponents() {
        try {
            // Inicializar PWA Manager
            if (window.PWAManager) {
                this.components.manager = new PWAManager();
                console.log('PWA Integration: PWA Manager inicializado');
            }
            
            // Inicializar Install Prompt
            if (window.PWAInstallPrompt) {
                this.components.installPrompt = new PWAInstallPrompt();
                console.log('PWA Integration: Install Prompt inicializado');
            }
            
            // Inicializar Offline Storage
            if (window.OfflineStorageManager) {
                this.components.offlineStorage = new OfflineStorageManager();
                console.log('PWA Integration: Offline Storage inicializado');
            }
            
            // Configurar integração entre componentes
            this.setupComponentIntegration();
            
            // Adicionar estilos globais
            this.addGlobalStyles();
            
            // Configurar eventos globais
            this.setupGlobalEvents();
            
            console.log('PWA Integration: Todos os componentes inicializados');
            
        } catch (error) {
            console.error('PWA Integration: Erro na inicialização:', error);
        }
    }
    
    setupComponentIntegration() {
        // Integrar PWA Manager com Offline Storage
        if (this.components.manager && this.components.offlineStorage) {
            // Sobrescrever método de sincronização do PWA Manager
            const originalSync = this.components.manager.syncOfflineData;
            this.components.manager.syncOfflineData = async () => {
                await this.components.offlineStorage.syncOfflineData();
                return originalSync.call(this.components.manager);
            };
        }
        
        // Integrar Install Prompt com PWA Manager
        if (this.components.installPrompt && this.components.manager) {
            // Sincronizar status de instalação
            this.components.installPrompt.isInstalled = this.components.manager.isInstalled;
        }
    }
    
    addGlobalStyles() {
        const styles = document.createElement('style');
        styles.textContent = `
            /* PWA Global Styles */
            .pwa-container {
                position: relative;
                min-height: 100vh;
            }
            
            .pwa-offline-indicator {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #ffc107;
                color: #856404;
                padding: 8px 15px;
                text-align: center;
                font-weight: 600;
                z-index: 1000;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
            }
            
            .pwa-offline-indicator.show {
                transform: translateY(0);
            }
            
            .pwa-sync-indicator {
                position: fixed;
                bottom: 80px;
                right: 20px;
                background: #17a2b8;
                color: white;
                padding: 10px 15px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 600;
                z-index: 1000;
                display: none;
                align-items: center;
                gap: 8px;
            }
            
            .pwa-sync-indicator.show {
                display: flex;
            }
            
            .pwa-sync-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid transparent;
                border-top: 2px solid white;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .pwa-status-bar {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 5px 15px;
                font-size: 12px;
                text-align: center;
                z-index: 999;
                display: none;
            }
            
            .pwa-status-bar.show {
                display: block;
            }
            
            /* PWA Install Button Styles */
            .pwa-install-btn {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 12px 20px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                transition: all 0.3s ease;
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 8px;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .pwa-install-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            }
            
            .pwa-install-btn:active {
                transform: translateY(0);
            }
            
            /* PWA Notification Styles */
            .pwa-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                font-weight: 600;
                z-index: 1001;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                animation: slideInRight 0.3s ease;
                max-width: 350px;
            }
            
            .pwa-notification-success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .pwa-notification-warning {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            
            .pwa-notification-info {
                background: #d1ecf1;
                color: #0c5460;
                border: 1px solid #bee5eb;
            }
            
            .pwa-notification-error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            /* PWA Modal Styles */
            .pwa-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1002;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(0, 0, 0, 0.5);
            }
            
            .pwa-modal-content {
                background: white;
                border-radius: 15px;
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                animation: modalSlideIn 0.3s ease;
            }
            
            .pwa-modal-header {
                padding: 20px 25px 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .pwa-modal-header h3 {
                margin: 0;
                color: #333;
                font-size: 1.3rem;
            }
            
            .pwa-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #999;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .pwa-modal-body {
                padding: 25px;
                color: #666;
                line-height: 1.6;
            }
            
            .pwa-modal-footer {
                padding: 0 25px 25px;
                text-align: right;
            }
            
            .pwa-modal-btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .pwa-modal-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }
            
            /* Animations */
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            @keyframes modalSlideIn {
                from { transform: scale(0.9); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
            
            @keyframes slideInUp {
                from { transform: translateY(100%); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            
            @keyframes slideOutDown {
                from { transform: translateY(0); opacity: 1; }
                to { transform: translateY(100%); opacity: 0; }
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .pwa-install-btn {
                    bottom: 15px;
                    right: 15px;
                    padding: 10px 16px;
                    font-size: 13px;
                }
                
                .pwa-notification {
                    top: 15px;
                    right: 15px;
                    left: 15px;
                    max-width: none;
                }
                
                .pwa-modal-content {
                    width: 95%;
                    margin: 20px;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    setupGlobalEvents() {
        // Evento de conexão
        window.addEventListener('online', () => {
            this.showOnlineIndicator();
            this.hideOfflineIndicator();
        });
        
        window.addEventListener('offline', () => {
            this.showOfflineIndicator();
            this.hideOnlineIndicator();
        });
        
        // Evento de sincronização
        if (this.components.offlineStorage) {
            // Monitorar sincronização
            setInterval(() => {
                this.checkSyncStatus();
            }, 30000); // Verificar a cada 30 segundos
        }
        
        // Evento de visibilidade da página
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.components.offlineStorage) {
                // Página voltou a ficar visível, verificar sincronização
                this.components.offlineStorage.syncOfflineData();
            }
        });
    }
    
    showOfflineIndicator() {
        let indicator = document.getElementById('pwa-offline-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'pwa-offline-indicator';
            indicator.className = 'pwa-offline-indicator';
            indicator.innerHTML = '📡 Você está offline - Algumas funcionalidades podem estar limitadas';
            document.body.appendChild(indicator);
        }
        indicator.classList.add('show');
    }
    
    hideOfflineIndicator() {
        const indicator = document.getElementById('pwa-offline-indicator');
        if (indicator) {
            indicator.classList.remove('show');
        }
    }
    
    showOnlineIndicator() {
        if (window.pwaManager) {
            window.pwaManager.showNotification('🌐 Conexão restaurada!', 'success');
        }
    }
    
    hideOnlineIndicator() {
        // Implementar se necessário
    }
    
    async checkSyncStatus() {
        if (this.components.offlineStorage && navigator.onLine) {
            try {
                const stats = await this.components.offlineStorage.getOfflineDataStats();
                if (stats.pendingSync > 0) {
                    this.showSyncIndicator(stats.pendingSync);
                } else {
                    this.hideSyncIndicator();
                }
            } catch (error) {
                console.error('PWA Integration: Erro ao verificar status de sincronização:', error);
            }
        }
    }
    
    showSyncIndicator(pendingCount) {
        let indicator = document.getElementById('pwa-sync-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'pwa-sync-indicator';
            indicator.className = 'pwa-sync-indicator';
            document.body.appendChild(indicator);
        }
        
        indicator.innerHTML = `
            <div class="pwa-sync-spinner"></div>
            <span>Sincronizando ${pendingCount} itens...</span>
        `;
        indicator.classList.add('show');
    }
    
    hideSyncIndicator() {
        const indicator = document.getElementById('pwa-sync-indicator');
        if (indicator) {
            indicator.classList.remove('show');
        }
    }
    
    // Métodos públicos
    getComponent(name) {
        return this.components[name];
    }
    
    isOnline() {
        return navigator.onLine;
    }
    
    isInstalled() {
        return this.components.manager ? this.components.manager.isInstalled() : false;
    }
    
    async getOfflineStats() {
        if (this.components.offlineStorage) {
            return await this.components.offlineStorage.getOfflineDataStats();
        }
        return null;
    }
    
    async clearOfflineData() {
        if (this.components.offlineStorage) {
            await this.components.offlineStorage.clearOfflineData();
        }
    }
    
    async forceSync() {
        if (this.components.offlineStorage) {
            await this.components.offlineStorage.syncOfflineData();
        }
    }
}

// Inicializar PWA Integration
const pwaIntegration = new PWAIntegration();

// Exportar para uso global
window.PWAIntegration = PWAIntegration;
window.pwaIntegration = pwaIntegration;
