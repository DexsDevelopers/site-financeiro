<?php
// /admin/footer_admin.php (Versão Responsiva e Profissional)
?>
    </div> <!-- Fecha a div .admin-main-content do header_admin.php -->
</div> <!-- Fecha a div .admin-wrapper do header_admin.php -->

<!-- COMPONENTE TOAST (Notificação) para o painel de admin -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="adminToast" class="toast admin-toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-bell-fill me-2"></i>
            <strong class="me-auto" id="adminToastTitle">Notificação</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Fechar"></button>
        </div>
        <div class="toast-body" id="adminToastBody">
            Mensagem da notificação.
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="z-index: 9999; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
    <div class="d-flex align-items-center justify-content-center h-100">
        <div class="text-center text-white">
            <div class="spinner-border text-danger mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <h5>Processando...</h5>
            <p class="text-muted">Aguarde um momento</p>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Estilos adicionais para SweetAlert2 -->
<style>
.swal-admin-popup {
    background: var(--admin-card-bg) !important;
    border: 1px solid var(--admin-border-color) !important;
    border-radius: var(--admin-border-radius) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
}

.swal-admin-popup .swal2-title {
    color: var(--admin-text-color) !important;
}

.swal-admin-popup .swal2-content {
    color: var(--admin-text-secondary) !important;
}

.swal-admin-popup .swal2-confirm {
    background: var(--admin-accent-color) !important;
    border: none !important;
    border-radius: var(--admin-border-radius) !important;
    font-weight: 600 !important;
    transition: var(--admin-transition) !important;
}

.swal-admin-popup .swal2-confirm:hover {
    background: var(--admin-accent-hover) !important;
    transform: translateY(-2px) !important;
}

.swal-admin-popup .swal2-cancel {
    background: #6c757d !important;
    border: none !important;
    border-radius: var(--admin-border-radius) !important;
    font-weight: 600 !important;
    transition: var(--admin-transition) !important;
}

.swal-admin-popup .swal2-cancel:hover {
    background: #5a6268 !important;
    transform: translateY(-2px) !important;
}

/* Avatar styles */
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 1rem;
}

/* Badge styles */
.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
}

/* Code styles */
code {
    background: rgba(220, 53, 69, 0.1);
    color: var(--admin-accent-color);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
}

/* Alert styles */
.alert {
    border: none;
    border-radius: var(--admin-border-radius);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.alert-info {
    background: rgba(13, 202, 240, 0.1);
    color: #0dcaf0;
    border-left: 4px solid #0dcaf0;
}

.alert-success {
    background: rgba(25, 135, 84, 0.1);
    color: #198754;
    border-left: 4px solid #198754;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
    border-left: 4px solid #ffc107;
}

/* Form text styles */
.form-text {
    color: var(--admin-text-secondary) !important;
    font-size: 0.8rem;
}

/* Input group styles */
.input-group .btn {
    border-color: var(--admin-border-color);
    background: rgba(255, 255, 255, 0.05);
    color: var(--admin-text-secondary);
    transition: var(--admin-transition);
}

.input-group .btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--admin-text-color);
    border-color: var(--admin-accent-color);
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .toast-container {
        bottom: 1rem;
        left: 1rem;
        right: 1rem;
        padding: 0;
    }
    
    .toast {
        width: 100%;
        margin: 0;
    }
    
    .avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 0.875rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
}

@media (max-width: 575.98px) {
    .avatar-sm {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }
    
    .badge {
        font-size: 0.65rem;
        padding: 0.2rem 0.4rem;
    }
    
    code {
        font-size: 0.75rem;
        padding: 0.2rem 0.4rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: light) {
    .alert-info {
        background: rgba(13, 202, 240, 0.1);
        color: #0a58ca;
    }
    
    .alert-success {
        background: rgba(25, 135, 84, 0.1);
        color: #0f5132;
    }
    
    .alert-warning {
        background: rgba(255, 193, 7, 0.1);
        color: #664d03;
    }
    
    code {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
}

/* Print styles */
@media print {
    .admin-sidebar,
    .admin-navbar-mobile,
    .btn,
    .toast-container,
    #loadingOverlay {
        display: none !important;
    }
    
    .admin-main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .admin-card {
        box-shadow: none !important;
        border: 1px solid #000 !important;
    }
}
</style>
    
<!-- SCRIPT GLOBAL PARA O PAINEL DE ADMIN -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // === SISTEMA DE TOAST NOTIFICATIONS ===
    const toastEl = document.getElementById('adminToast');
    if (toastEl) {
        const toastInstance = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 5000
        });
        
        // Função global para mostrar notificações
        window.showAdminToast = function(title, message, isError = false, duration = 5000) {
            const toastTitle = document.getElementById('adminToastTitle');
            const toastBody = document.getElementById('adminToastBody');
            const toastHeader = toastEl.querySelector('.toast-header');
            const toastIcon = toastHeader.querySelector('i');
            
            toastTitle.textContent = title;
            toastBody.textContent = message;
            
            // Reset classes
            toastHeader.classList.remove('bg-danger', 'bg-success', 'bg-info', 'bg-warning', 'text-white');
            toastIcon.classList.remove('bi-bell-fill', 'bi-check-circle-fill', 'bi-info-circle-fill', 'bi-exclamation-triangle-fill');
            
            if (isError) {
                toastHeader.classList.add('bg-danger', 'text-white');
                toastIcon.classList.add('bi-exclamation-triangle-fill');
            } else if (title.includes('Sucesso') || title.includes('sucesso')) {
                toastHeader.classList.add('bg-success', 'text-white');
                toastIcon.classList.add('bi-check-circle-fill');
            } else if (title.includes('Info') || title.includes('info')) {
                toastHeader.classList.add('bg-info', 'text-white');
                toastIcon.classList.add('bi-info-circle-fill');
            } else {
                toastIcon.classList.add('bi-bell-fill');
            }
            
            toastInstance._config.delay = duration;
            toastInstance.show();
        }
    }

    // === SISTEMA DE LOADING OVERLAY ===
    window.showAdminLoading = function(message = 'Processando...') {
        const overlay = document.getElementById('loadingOverlay');
        const loadingText = overlay.querySelector('h5');
        if (overlay && loadingText) {
            loadingText.textContent = message;
            overlay.classList.remove('d-none');
        }
    };

    window.hideAdminLoading = function() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.add('d-none');
        }
    };

    // === SISTEMA DE CONFIRMAÇÃO ===
    window.showAdminConfirm = function(title, message, confirmText = 'Confirmar', cancelText = 'Cancelar') {
        return Swal.fire({
            title: title,
            html: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `<i class="bi bi-check-circle-fill me-1"></i>${confirmText}`,
            cancelButtonText: `<i class="bi bi-x-circle-fill me-1"></i>${cancelText}`,
            background: '#1a1a1a',
            color: '#fff',
            customClass: {
                popup: 'swal-admin-popup'
            }
        });
    };

    // === SISTEMA DE ALERTAS ===
    window.showAdminAlert = function(title, message, type = 'info') {
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };

        return Swal.fire({
            title: title,
            html: `
                <div class="text-center">
                    <i class="bi ${icons[type]} text-${type === 'error' ? 'danger' : type}" style="font-size: 3rem;"></i>
                    <p class="mt-3">${message}</p>
                </div>
            `,
            icon: type,
            confirmButtonColor: type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#0d6efd',
            confirmButtonText: '<i class="bi bi-check-circle-fill me-1"></i>OK',
            background: '#1a1a1a',
            color: '#fff',
            customClass: {
                popup: 'swal-admin-popup'
            }
        });
    };

    // === SISTEMA DE DETECÇÃO DE DISPOSITIVO ===
    window.isMobile = function() {
        return window.innerWidth < 768;
    };

    window.isTablet = function() {
        return window.innerWidth >= 768 && window.innerWidth < 992;
    };

    window.isDesktop = function() {
        return window.innerWidth >= 992;
    };

    // === SISTEMA DE AJUSTE AUTOMÁTICO DE LAYOUT ===
    function adjustLayoutForDevice() {
        const body = document.body;
        const isMobileDevice = window.isMobile();
        const isTabletDevice = window.isTablet();
        
        // Remover classes anteriores
        body.classList.remove('mobile-layout', 'tablet-layout', 'desktop-layout');
        
        // Adicionar classe apropriada
        if (isMobileDevice) {
            body.classList.add('mobile-layout');
        } else if (isTabletDevice) {
            body.classList.add('tablet-layout');
        } else {
            body.classList.add('desktop-layout');
        }
    }

    // Executar no carregamento e redimensionamento
    adjustLayoutForDevice();
    window.addEventListener('resize', adjustLayoutForDevice);

    // === SISTEMA DE PERFORMANCE ===
    
    // Debounce para eventos de resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(adjustLayoutForDevice, 250);
    });

    // Lazy loading para imagens (se houver)
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    // === SISTEMA DE ACESSIBILIDADE ===
    
    // Navegação por teclado aprimorada
    document.addEventListener('keydown', function(event) {
        // ESC para fechar modais
        if (event.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
        }

        // Ctrl+Enter para submeter formulários
        if (event.ctrlKey && event.key === 'Enter') {
            const activeElement = document.activeElement;
            const form = activeElement.closest('form');
            if (form) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                }
            }
        }
    });

    // Foco visível para elementos interativos
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });

    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });

    // === SISTEMA DE ESTADO ===
    
    // Salvar estado do sidebar
    const sidebarState = localStorage.getItem('admin-sidebar-state');
    if (sidebarState === 'collapsed') {
        document.body.classList.add('sidebar-collapsed');
    }

    // === SISTEMA DE THEME ===
    
    // Detectar preferência de tema do sistema
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
        document.body.classList.add('light-theme');
    }

    // === SISTEMA DE NOTIFICAÇÕES ===
    
    // Verificar se há notificações pendentes
    if ('Notification' in window && Notification.permission === 'default') {
        // Solicitar permissão para notificações (opcional)
        // Notification.requestPermission();
    }

    // === SISTEMA DE OFFLINE ===
    
    // Detectar status de conexão
    window.addEventListener('online', function() {
        window.showAdminToast('Conexão Restaurada', 'Você está online novamente.', false, 3000);
    });

    window.addEventListener('offline', function() {
        window.showAdminToast('Sem Conexão', 'Verifique sua conexão com a internet.', true, 5000);
    });

    // === SISTEMA DE LOGS ===
    
    // Log de erros JavaScript
    window.addEventListener('error', function(event) {
        console.error('Erro JavaScript:', event.error);
        // Aqui você pode enviar o erro para um serviço de monitoramento
    });

    // === CONTROLE DO SIDEBAR MOBILE ===
    
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    const closeBtn = document.getElementById('closeSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        body.classList.remove('sidebar-open');
    }

    // Toggle sidebar
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    // Close sidebar
    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    // Close sidebar when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        if (window.innerWidth < 992) {
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                closeSidebar();
            }
        }
    });

    // Close sidebar on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });

    // === INICIALIZAÇÃO FINAL ===
    
    // Mostrar mensagem de boas-vindas (apenas na primeira visita)
    const hasVisited = sessionStorage.getItem('admin-visited');
    if (!hasVisited) {
        setTimeout(() => {
            window.showAdminToast('Bem-vindo!', 'Painel de administração carregado com sucesso.', false, 3000);
            sessionStorage.setItem('admin-visited', 'true');
        }, 1000);
    }

    // Adicionar classe de carregamento completo
    document.body.classList.add('admin-loaded');
});

// === FUNÇÕES UTILITÁRIAS GLOBAIS ===

// Função para formatar números
window.formatNumber = function(num) {
    return new Intl.NumberFormat('pt-BR').format(num);
};

// Função para formatar moeda
window.formatCurrency = function(amount) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(amount);
};

// Função para formatar data
window.formatDate = function(date) {
    return new Intl.DateTimeFormat('pt-BR').format(new Date(date));
};

// Função para formatar data e hora
window.formatDateTime = function(date) {
    return new Intl.DateTimeFormat('pt-BR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
};

// Função para copiar texto para clipboard
window.copyToClipboard = function(text) {
    if (navigator.clipboard) {
        return navigator.clipboard.writeText(text);
    } else {
        // Fallback para navegadores mais antigos
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        return Promise.resolve();
    }
};

// Função para gerar ID único
window.generateId = function() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
};

// Função para debounce
window.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// Função para throttle
window.throttle = function(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};
</script>

</body>
</html>