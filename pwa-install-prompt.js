/**
 * PWA Install Prompt
 * Gerencia o prompt de instalação do Progressive Web App
 */

class PWAInstallPrompt {
    constructor() {
        this.deferredPrompt = null;
        this.installButton = null;
        this.isInstalled = false;
        this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                           window.navigator.standalone === true;
        
        this.init();
    }
    
    init() {
        // Verificar se já está instalado
        if (this.isStandalone) {
            this.isInstalled = true;
            return;
        }
        
        // Aguardar evento beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWA: beforeinstallprompt disparado');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
        });
        
        // Verificar se foi instalado
        window.addEventListener('appinstalled', () => {
            console.log('PWA: App instalado com sucesso');
            this.isInstalled = true;
            this.hideInstallButton();
            this.showInstallSuccessMessage();
        });
        
        // Verificar se está em modo standalone
        window.addEventListener('load', () => {
            if (this.isStandalone) {
                this.isInstalled = true;
            }
        });
        
        // Criar botão de instalação
        this.createInstallButton();
    }
    
    createInstallButton() {
        // Verificar se o botão já existe
        if (document.getElementById('pwa-install-button')) {
            return;
        }
        
        const button = document.createElement('button');
        button.id = 'pwa-install-button';
        button.className = 'pwa-install-btn';
        button.innerHTML = `
            <i class="bi bi-download"></i>
            <span>Instalar App</span>
        `;
        
        // Estilos do botão
        button.style.cssText = `
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
            display: none;
            align-items: center;
            gap: 8px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        `;
        
        // Hover effect
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'translateY(-2px)';
            button.style.boxShadow = '0 6px 20px rgba(102, 126, 234, 0.6)';
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'translateY(0)';
            button.style.boxShadow = '0 4px 15px rgba(102, 126, 234, 0.4)';
        });
        
        // Click handler
        button.addEventListener('click', () => {
            this.installApp();
        });
        
        document.body.appendChild(button);
        this.installButton = button;
    }
    
    showInstallButton() {
        if (this.installButton && !this.isInstalled) {
            this.installButton.style.display = 'flex';
            
            // Animação de entrada
            setTimeout(() => {
                this.installButton.style.opacity = '1';
                this.installButton.style.transform = 'translateY(0)';
            }, 100);
        }
    }
    
    hideInstallButton() {
        if (this.installButton) {
            this.installButton.style.opacity = '0';
            this.installButton.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                this.installButton.style.display = 'none';
            }, 300);
        }
    }
    
    async installApp() {
        if (!this.deferredPrompt) {
            this.showInstallInstructions();
            return;
        }
        
        try {
            // Mostrar prompt de instalação
            this.deferredPrompt.prompt();
            
            // Aguardar resposta do usuário
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log('PWA: Resultado do prompt:', outcome);
            
            if (outcome === 'accepted') {
                console.log('PWA: Usuário aceitou a instalação');
                this.showInstallSuccessMessage();
            } else {
                console.log('PWA: Usuário rejeitou a instalação');
                this.showInstallInstructions();
            }
            
            // Limpar prompt
            this.deferredPrompt = null;
            this.hideInstallButton();
            
        } catch (error) {
            console.error('PWA: Erro ao instalar app:', error);
            this.showInstallInstructions();
        }
    }
    
    showInstallSuccessMessage() {
        this.showNotification('🎉 App instalado com sucesso!', 'success');
    }
    
    showInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);
        
        let message = '';
        
        if (isIOS) {
            message = `
                <div style="text-align: left;">
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
                <div style="text-align: left;">
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
                <div style="text-align: left;">
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
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `pwa-notification pwa-notification-${type}`;
        notification.innerHTML = message;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#d4edda' : '#d1ecf1'};
            color: ${type === 'success' ? '#155724' : '#0c5460'};
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid ${type === 'success' ? '#c3e6cb' : '#bee5eb'};
            font-weight: 600;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease;
        `;
        
        // Adicionar animação CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(notification);
        
        // Remover após 5 segundos
        setTimeout(() => {
            notification.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
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
                        <button class="pwa-modal-btn pwa-modal-btn-primary">Entendi</button>
                    </div>
                </div>
            </div>
        `;
        
        // Estilos do modal
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
        
        const style = document.createElement('style');
        style.textContent = `
            .pwa-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .pwa-modal-content {
                background: white;
                border-radius: 15px;
                max-width: 500px;
                width: 100%;
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
            
            @keyframes modalSlideIn {
                from { transform: scale(0.9); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
        
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
    
    // Método público para verificar se está instalado
    isAppInstalled() {
        return this.isInstalled;
    }
    
    // Método público para forçar exibição do botão
    forceShowInstallButton() {
        if (!this.isInstalled) {
            this.showInstallButton();
        }
    }
}

// Inicializar automaticamente
document.addEventListener('DOMContentLoaded', () => {
    window.pwaInstallPrompt = new PWAInstallPrompt();
});

// Exportar para uso global
window.PWAInstallPrompt = PWAInstallPrompt;
