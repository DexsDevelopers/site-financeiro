/**
 * Sistema de Notificações Toast
 * Fornece feedback visual elegante para as ações do usuário
 */

class ToastManager {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Criar container se não existir
        if (!document.querySelector('.toast-container')) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.toast-container');
        }
    }

    /**
     * Mostra uma notificação toast
     * @param {string} message - Mensagem principal
     * @param {string} type - Tipo: success, error, warning, info
     * @param {string} title - Título opcional
     * @param {number} duration - Duração em ms (padrão: 3000)
     */
    show(message, type = 'info', title = '', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Ícones por tipo
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        // Títulos padrão
        const defaultTitles = {
            success: 'Sucesso!',
            error: 'Erro!',
            warning: 'Atenção!',
            info: 'Informação'
        };

        const finalTitle = title || defaultTitles[type];
        
        toast.innerHTML = `
            <div class="toast-icon">${icons[type]}</div>
            <div class="toast-content">
                <div class="toast-title">${finalTitle}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="Toast.remove(this.parentElement)">×</button>
            <div class="toast-progress"></div>
        `;

        // Adicionar ao container
        this.container.appendChild(toast);

        // Auto-remover após duração
        setTimeout(() => {
            this.remove(toast);
        }, duration);

        return toast;
    }

    /**
     * Remove um toast específico
     */
    remove(toast) {
        if (!toast || !toast.parentElement) return;
        
        toast.classList.add('removing');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }

    /**
     * Métodos de conveniência
     */
    success(message, title = '') {
        return this.show(message, 'success', title);
    }

    error(message, title = '') {
        return this.show(message, 'error', title);
    }

    warning(message, title = '') {
        return this.show(message, 'warning', title);
    }

    info(message, title = '') {
        return this.show(message, 'info', title);
    }

    /**
     * Limpa todos os toasts
     */
    clear() {
        const toasts = this.container.querySelectorAll('.toast');
        toasts.forEach(toast => this.remove(toast));
    }
}

// Criar instância global
const Toast = new ToastManager();

// Exportar para uso em outros scripts
window.Toast = Toast;
