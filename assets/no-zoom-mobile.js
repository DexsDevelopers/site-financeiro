/* =========================================== */
/* PREVENÇÃO TOTAL DE ZOOM NO MOBILE - JAVASCRIPT */
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
    
    // Aplicar prevenção de zoom apenas em mobile
    if (isMobile()) {
        
        // Prevenir zoom com gestos
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
            return false;
        }, { passive: false });
        
        document.addEventListener('gesturechange', function(e) {
            e.preventDefault();
            return false;
        }, { passive: false });
        
        document.addEventListener('gestureend', function(e) {
            e.preventDefault();
            return false;
        }, { passive: false });
        
        // Prevenir zoom com double-tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(e) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
                return false;
            }
            lastTouchEnd = now;
        }, { passive: false });
        
        // Prevenir zoom com pinch
        document.addEventListener('touchmove', function(e) {
            if (e.scale && e.scale !== 1) {
                e.preventDefault();
                return false;
            }
        }, { passive: false });
        
        // Prevenir zoom com wheel + Ctrl
        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                return false;
            }
        }, { passive: false });
        
        // Prevenir zoom com keyboard
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && 
                (e.key === '+' || e.key === '-' || e.key === '0' || e.key === '=')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Prevenir zoom com context menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Forçar viewport fixo
        function setFixedViewport() {
            const viewport = document.querySelector('meta[name="viewport"]');
            if (viewport) {
                viewport.setAttribute('content', 
                    'width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, shrink-to-fit=no');
            }
        }
        
        // Aplicar viewport fixo imediatamente
        setFixedViewport();
        
        // Reaplicar viewport fixo quando necessário
        document.addEventListener('DOMContentLoaded', setFixedViewport);
        window.addEventListener('load', setFixedViewport);
        window.addEventListener('resize', setFixedViewport);
        
        // Prevenir zoom em inputs quando recebem foco
        function preventInputZoom() {
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    setFixedViewport();
                    // Forçar tamanho de fonte para evitar zoom automático
                    this.style.fontSize = '16px';
                });
                
                input.addEventListener('blur', function() {
                    setFixedViewport();
                });
            });
        }
        
        // Aplicar prevenção em inputs existentes e futuros
        document.addEventListener('DOMContentLoaded', preventInputZoom);
        
        // Observar novos inputs adicionados dinamicamente
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            const inputs = node.querySelectorAll ? node.querySelectorAll('input, select, textarea') : [];
                            inputs.forEach(input => {
                                input.addEventListener('focus', function() {
                                    setFixedViewport();
                                    this.style.fontSize = '16px';
                                });
                            });
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Prevenir zoom em imagens
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            img.addEventListener('touchstart', function(e) {
                e.preventDefault();
                return false;
            }, { passive: false });
            
            img.addEventListener('touchend', function(e) {
                e.preventDefault();
                return false;
            }, { passive: false });
        });
        
        // Prevenir zoom em links
        const links = document.querySelectorAll('a');
        links.forEach(link => {
            link.addEventListener('touchstart', function(e) {
                // Permitir clique em links, mas prevenir zoom
                if (e.touches.length > 1) {
                    e.preventDefault();
                    return false;
                }
            }, { passive: false });
        });
        
        // Prevenir zoom em botões
        const buttons = document.querySelectorAll('button, .btn');
        buttons.forEach(button => {
            button.addEventListener('touchstart', function(e) {
                if (e.touches.length > 1) {
                    e.preventDefault();
                    return false;
                }
            }, { passive: false });
        });
        
        // Forçar tamanho de fonte em todos os elementos
        function forceFontSize() {
            const elements = document.querySelectorAll('*');
            elements.forEach(el => {
                if (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA' && el.tagName !== 'SELECT') {
                    el.style.webkitTextSizeAdjust = '100%';
                    el.style.msTextSizeAdjust = '100%';
                    el.style.textSizeAdjust = '100%';
                }
            });
        }
        
        // Aplicar forçar tamanho de fonte
        document.addEventListener('DOMContentLoaded', forceFontSize);
        window.addEventListener('load', forceFontSize);
        
        // Prevenir zoom com orientação
        window.addEventListener('orientationchange', function() {
            setTimeout(function() {
                setFixedViewport();
                forceFontSize();
            }, 100);
        });
        
        // Prevenir zoom com resize
        window.addEventListener('resize', function() {
            setFixedViewport();
            forceFontSize();
        });
        
        // Prevenir zoom com scroll
        let isScrolling = false;
        window.addEventListener('scroll', function() {
            if (!isScrolling) {
                isScrolling = true;
                setFixedViewport();
                setTimeout(function() {
                    isScrolling = false;
                }, 100);
            }
        });
        
        // Prevenir zoom com touch (apenas para gestos de zoom)
        document.addEventListener('touchstart', function(e) {
            if (e.touches.length > 1) {
                e.preventDefault();
                return false;
            }
        }, { passive: false });
        
        // Permitir scroll normal, apenas prevenir zoom
        document.addEventListener('touchmove', function(e) {
            if (e.touches.length > 1) {
                e.preventDefault();
                return false;
            }
            // Permitir scroll normal com um dedo
        }, { passive: true });
        
        // Prevenir zoom com touchend
        document.addEventListener('touchend', function(e) {
            if (e.changedTouches.length > 1) {
                e.preventDefault();
                return false;
            }
        }, { passive: false });
        
        console.log('Prevenção de zoom no mobile ativada');
    }

    // =========================================== */
    // CORREÇÕES DE SCROLL NO MOBILE */
    // =========================================== */
    
    if (isMobile()) {
        // Corrigir scroll suave
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar smooth scroll para elementos específicos
            const scrollElements = document.querySelectorAll('.main-content, .container, .card, .modal-content');
            
            scrollElements.forEach(element => {
                element.style.webkitOverflowScrolling = 'touch';
                element.style.overflowX = 'hidden';
            });

            // Corrigir scroll em modais
            const modals = document.querySelectorAll('.modal-body');
            modals.forEach(modal => {
                modal.style.webkitOverflowScrolling = 'touch';
                modal.style.overflowX = 'hidden';
                modal.style.maxHeight = '70vh';
                modal.style.overflowY = 'auto';
            });

            // Corrigir scroll em tabelas
            const tables = document.querySelectorAll('.table-responsive');
            tables.forEach(table => {
                table.style.webkitOverflowScrolling = 'touch';
                table.style.overflowX = 'auto';
            });

            // Corrigir scroll em formulários
            const formElements = document.querySelectorAll('.form-control, .form-select, textarea');
            formElements.forEach(element => {
                element.style.webkitOverflowScrolling = 'touch';
            });

            // Corrigir scroll em hero sections
            const heroSections = document.querySelectorAll('.productivity-hero, .timer-hero, .tasks-hero');
            heroSections.forEach(hero => {
                hero.style.webkitOverflowScrolling = 'touch';
                hero.style.overflowX = 'hidden';
            });

            // Corrigir scroll em feature cards
            const featureCards = document.querySelectorAll('.feature-card');
            featureCards.forEach(card => {
                card.style.webkitOverflowScrolling = 'touch';
                card.style.overflowX = 'hidden';
            });

            // Corrigir scroll em task cards
            const taskCards = document.querySelectorAll('.task-card');
            taskCards.forEach(card => {
                card.style.webkitOverflowScrolling = 'touch';
                card.style.overflowX = 'hidden';
            });

            // Corrigir scroll em sidebar
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.style.webkitOverflowScrolling = 'touch';
                sidebar.style.overflowX = 'hidden';
            }

            // Corrigir scroll em main content
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.style.webkitOverflowScrolling = 'touch';
                mainContent.style.overflowX = 'hidden';
                mainContent.style.height = '100vh';
                mainContent.style.overflowY = 'auto';
            }

            console.log('Correções de scroll no mobile aplicadas');
        });

        // Corrigir scroll em elementos dinâmicos
        const scrollObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            const elements = node.querySelectorAll ? node.querySelectorAll('.card, .modal-content, .feature-card, .task-card') : [];
                            elements.forEach(element => {
                                element.style.webkitOverflowScrolling = 'touch';
                                element.style.overflowX = 'hidden';
                            });
                        }
                    });
                }
            });
        });

        scrollObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})();
