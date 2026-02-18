// Script para garantir que conteúdo não esteja oculto
(function() {
    'use strict';
    
    function ensureContentVisible() {
        // Garantir que main-content está visível
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.style.display = 'block';
            mainContent.style.visibility = 'visible';
            mainContent.style.opacity = '1';
            mainContent.style.zIndex = '1';
        }
        
        // Garantir que cards estão visíveis
        const cards = document.querySelectorAll('.main-content .card, .main-content .card-glass');
        cards.forEach(card => {
            card.style.display = 'block';
            card.style.visibility = 'visible';
            card.style.opacity = '1';
        });
        
        // Remover overlays bloqueantes
        const overlays = document.querySelectorAll('.tourlite-overlay:not(.active), .pwa-modal-overlay:not(.show)');
        overlays.forEach(overlay => {
            if (!overlay.classList.contains('active') && !overlay.classList.contains('show')) {
                overlay.style.display = 'none';
            }
        });
        
        // Garantir que container-fluid está visível
        const containers = document.querySelectorAll('.main-content .container-fluid, .main-content main');
        containers.forEach(container => {
            container.style.display = 'block';
            container.style.visibility = 'visible';
            container.style.opacity = '1';
        });
    }
    
    // Executar imediatamente
    ensureContentVisible();
    
    // Executar após DOM carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureContentVisible);
    } else {
        ensureContentVisible();
    }
    
    // Executar após window load
    window.addEventListener('load', ensureContentVisible);
    
    // Executar periodicamente para garantir (proteção contra scripts que ocultam)
    setInterval(ensureContentVisible, 2000);
})();

