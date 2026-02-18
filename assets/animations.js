/* =========================================== */
/* SISTEMA DE ANIMAÇÕES OTIMIZADO */
/* =========================================== */

class AnimationManager {
    constructor() {
        this.observers = new Map();
        this.animationQueue = [];
        this.isProcessing = false;
        this.performanceMode = this.detectPerformanceMode();
        this.init();
    }

    init() {
        this.setupIntersectionObserver();
        this.setupScrollAnimations();
        this.setupHoverAnimations();
        this.setupBatchAnimations();
        this.optimizeAnimations();
    }

    detectPerformanceMode() {
        // Detectar se o dispositivo tem baixa performance
        const isLowEnd = navigator.hardwareConcurrency <= 2 || 
                        navigator.deviceMemory <= 4 ||
                        /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        return isLowEnd ? 'low' : 'high';
    }

    setupIntersectionObserver() {
        if (!('IntersectionObserver' in window)) {
            // Fallback para navegadores antigos
            this.animateAllElements();
            return;
        }

        const options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateElement(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        }, options);

        // Observar elementos com classes de animação
        document.addEventListener('DOMContentLoaded', () => {
            const animatedElements = document.querySelectorAll('[class*="animate-"], .scroll-reveal');
            animatedElements.forEach(el => {
                this.observer.observe(el);
            });
        });
    }

    setupScrollAnimations() {
        let ticking = false;
        
        const updateScrollAnimations = () => {
            const elements = document.querySelectorAll('.scroll-reveal');
            elements.forEach(el => {
                const rect = el.getBoundingClientRect();
                const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
                
                if (isVisible && !el.classList.contains('revealed')) {
                    el.classList.add('revealed');
                }
            });
            ticking = false;
        };

        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(updateScrollAnimations);
                ticking = true;
            }
        });
    }

    setupHoverAnimations() {
        // Animações de hover otimizadas
        document.addEventListener('mouseover', (e) => {
            const element = e.target.closest('.hover-scale, .hover-glow, .hover-bounce, .card-hover');
            if (element && this.performanceMode === 'high') {
                element.style.willChange = 'transform, box-shadow';
            }
        });

        document.addEventListener('mouseout', (e) => {
            const element = e.target.closest('.hover-scale, .hover-glow, .hover-bounce, .card-hover');
            if (element) {
                element.style.willChange = 'auto';
            }
        });
    }

    setupBatchAnimations() {
        // Animar elementos em lotes para melhor performance
        const batchSize = this.performanceMode === 'low' ? 3 : 5;
        let currentBatch = 0;

        const processBatch = () => {
            const start = currentBatch * batchSize;
            const end = start + batchSize;
            const elements = Array.from(document.querySelectorAll('.animate-batch'));
            
            for (let i = start; i < Math.min(end, elements.length); i++) {
                if (elements[i]) {
                    this.animateElement(elements[i]);
                }
            }
            
            currentBatch++;
            
            if (end < elements.length) {
                setTimeout(processBatch, 50);
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(processBatch, 100);
        });
    }

    animateElement(element) {
        if (!element || element.classList.contains('animated')) return;

        const animationClass = this.getAnimationClass(element);
        if (animationClass) {
            element.classList.add(animationClass);
            element.classList.add('animated');
            
            // Remover will-change após animação
            setTimeout(() => {
                element.style.willChange = 'auto';
            }, 300);
        }
    }

    getAnimationClass(element) {
        const classes = element.className.split(' ');
        
        for (const className of classes) {
            if (className.startsWith('animate-')) {
                return className;
            }
        }
        
        return null;
    }

    animateAllElements() {
        // Fallback para navegadores sem IntersectionObserver
        const elements = document.querySelectorAll('[class*="animate-"]');
        elements.forEach((el, index) => {
            setTimeout(() => {
                this.animateElement(el);
            }, index * 100);
        });
    }

    optimizeAnimations() {
        // Reduzir animações em dispositivos de baixa performance
        if (this.performanceMode === 'low') {
            document.documentElement.style.setProperty('--animation-duration-fast', '0.1s');
            document.documentElement.style.setProperty('--animation-duration-normal', '0.2s');
            document.documentElement.style.setProperty('--animation-duration-slow', '0.3s');
        }

        // Desabilitar animações se o usuário prefere movimento reduzido
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.style.setProperty('--animation-duration-fast', '0.01ms');
            document.documentElement.style.setProperty('--animation-duration-normal', '0.01ms');
            document.documentElement.style.setProperty('--animation-duration-slow', '0.01ms');
        }
    }

    // Método para animar elementos programaticamente
    animate(selector, animationClass, delay = 0) {
        setTimeout(() => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(el => {
                el.classList.add(animationClass);
                el.classList.add('animated');
            });
        }, delay);
    }

    // Método para animar elementos em sequência
    animateSequence(selectors, animationClass, stagger = 100) {
        selectors.forEach((selector, index) => {
            setTimeout(() => {
                this.animate(selector, animationClass);
            }, index * stagger);
        });
    }

    // Método para animar contadores
    animateCounter(element, target, duration = 2000) {
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    }

    // Método para animar progresso
    animateProgress(element, target, duration = 1000) {
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.style.width = current + '%';
        }, 16);
    }

    // Método para animar elementos com scroll
    animateOnScroll(selector, animationClass, offset = 0) {
        const elements = document.querySelectorAll(selector);
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add(animationClass);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: `${offset}px`
        });

        elements.forEach(el => observer.observe(el));
    }

    // Método para limpar animações
    clearAnimations() {
        const elements = document.querySelectorAll('[class*="animate-"]');
        elements.forEach(el => {
            el.classList.remove('animated');
            el.style.animation = 'none';
        });
    }

    // Método para pausar animações
    pauseAnimations() {
        document.documentElement.style.setProperty('--animation-play-state', 'paused');
    }

    // Método para retomar animações
    resumeAnimations() {
        document.documentElement.style.setProperty('--animation-play-state', 'running');
    }
}

// Inicializar o gerenciador de animações
const animationManager = new AnimationManager();

// Funções utilitárias globais
window.animateElement = (selector, animationClass, delay = 0) => {
    animationManager.animate(selector, animationClass, delay);
};

window.animateSequence = (selectors, animationClass, stagger = 100) => {
    animationManager.animateSequence(selectors, animationClass, stagger);
};

window.animateCounter = (element, target, duration = 2000) => {
    animationManager.animateCounter(element, target, duration);
};

window.animateProgress = (element, target, duration = 1000) => {
    animationManager.animateProgress(element, target, duration);
};

window.animateOnScroll = (selector, animationClass, offset = 0) => {
    animationManager.animateOnScroll(selector, animationClass, offset);
};

// Otimizações de performance
document.addEventListener('DOMContentLoaded', () => {
    // Lazy loading de imagens
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));

    // Preload de recursos críticos
    const criticalResources = [
        'assets/animations.css',
        'assets/modern.css'
    ];

    criticalResources.forEach(resource => {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.href = resource;
        link.as = 'style';
        document.head.appendChild(link);
    });
});

// Exportar para uso em módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AnimationManager;
}
