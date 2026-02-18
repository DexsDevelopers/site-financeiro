/* =========================================== */
/* CORREÇÃO DE SCROLL NO MOBILE - JAVASCRIPT */
/* =========================================== */

(function() {
    'use strict';
    
    // Detectar se é dispositivo móvel
    function isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
               window.innerWidth <= 768 ||
               ('ontouchstart' in window) ||
               (navigator.maxTouchPoints > 0);
    }
    
    if (isMobile()) {
        console.log('Aplicando correções de scroll no mobile...');
        
        // Função para aplicar scroll suave
        function applyScrollFix() {
            // Aplicar scroll suave em todos os elementos
            const elements = document.querySelectorAll('*');
            elements.forEach(element => {
                element.style.webkitOverflowScrolling = 'touch';
                element.style.touchAction = 'pan-y';
            });
            
            // Aplicar scroll específico em elementos importantes
            const importantElements = document.querySelectorAll(
                'html, body, .main-content, .container, .card, .modal-content, .productivity-hero, .timer-hero, .tasks-hero, .feature-card, .task-card, .sidebar'
            );
            
            importantElements.forEach(element => {
                element.style.webkitOverflowScrolling = 'touch';
                element.style.touchAction = 'pan-y';
                element.style.overflowX = 'hidden';
            });
            
            // Corrigir modais
            const modals = document.querySelectorAll('.modal-body');
            modals.forEach(modal => {
                modal.style.webkitOverflowScrolling = 'touch';
                modal.style.touchAction = 'pan-y';
                modal.style.overflowX = 'hidden';
                modal.style.maxHeight = '70vh';
                modal.style.overflowY = 'auto';
            });
            
            // Corrigir tabelas
            const tables = document.querySelectorAll('.table-responsive');
            tables.forEach(table => {
                table.style.webkitOverflowScrolling = 'touch';
                table.style.touchAction = 'pan-x pan-y';
                table.style.overflowX = 'auto';
                table.style.overflowY = 'hidden';
            });
            
            // Corrigir formulários
            const formElements = document.querySelectorAll('.form-control, .form-select, textarea');
            formElements.forEach(element => {
                element.style.webkitOverflowScrolling = 'touch';
                element.style.touchAction = 'pan-y';
            });
            
            console.log('Correções de scroll aplicadas');
        }
        
        // Aplicar correções quando o DOM estiver pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyScrollFix);
        } else {
            applyScrollFix();
        }
        
        // Aplicar correções quando a página carregar
        window.addEventListener('load', applyScrollFix);
        
        // Aplicar correções quando a orientação mudar
        window.addEventListener('orientationchange', function() {
            setTimeout(applyScrollFix, 100);
        });
        
        // Aplicar correções quando a tela redimensionar
        window.addEventListener('resize', function() {
            setTimeout(applyScrollFix, 100);
        });
        
        // Observar mudanças no DOM para aplicar correções em elementos novos
        const observer = new MutationObserver(function(mutations) {
            let shouldApply = false;
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    shouldApply = true;
                }
            });
            
            if (shouldApply) {
                setTimeout(applyScrollFix, 50);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Remover event listeners problemáticos que podem estar causando o travamento
        function removeProblematicListeners() {
            // Remover listeners de touch que podem estar bloqueando o scroll
            const elements = document.querySelectorAll('*');
            elements.forEach(element => {
                // Clonar o elemento para remover todos os event listeners
                const newElement = element.cloneNode(true);
                element.parentNode.replaceChild(newElement, element);
            });
        }
        
        // Aplicar remoção de listeners problemáticos após um delay
        setTimeout(removeProblematicListeners, 1000);
        
        // Forçar scroll suave
        function forceSmoothScroll() {
            document.documentElement.style.scrollBehavior = 'smooth';
            document.body.style.scrollBehavior = 'smooth';
        }
        
        forceSmoothScroll();
        
        // Aplicar correções periodicamente para garantir que funcionem
        setInterval(applyScrollFix, 5000);
        
        console.log('Correções de scroll no mobile configuradas');
    }
})();

