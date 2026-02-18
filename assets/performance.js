/* =========================================== */
/* OTIMIZAÇÕES DE PERFORMANCE GLOBAIS */
/* =========================================== */

class PerformanceOptimizer {
    constructor() {
        this.isLowEndDevice = this.detectLowEndDevice();
        this.init();
    }

    init() {
        this.optimizeImages();
        this.optimizeAnimations();
        this.optimizeScroll();
        this.optimizeFonts();
        this.optimizeResources();
    }

    detectLowEndDevice() {
        return navigator.hardwareConcurrency <= 2 || 
               navigator.deviceMemory <= 4 ||
               /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    optimizeImages() {
        // Lazy loading para imagens
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

        // Otimizar imagens existentes
        const allImages = document.querySelectorAll('img');
        allImages.forEach(img => {
            if (!img.loading) {
                img.loading = 'lazy';
            }
        });
    }

    optimizeAnimations() {
        if (this.isLowEndDevice) {
            // Reduzir animações em dispositivos de baixa performance
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

    optimizeScroll() {
        let ticking = false;
        
        const updateScroll = () => {
            // Otimizações de scroll aqui
            ticking = false;
        };

        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(updateScroll);
                ticking = true;
            }
        });
    }

    optimizeFonts() {
        // Preload de fontes críticas
        const fontPreloads = [
            'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap',
            'https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap'
        ];

        fontPreloads.forEach(font => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = font;
            link.as = 'style';
            document.head.appendChild(link);
        });
    }

    optimizeResources() {
        // Preload de recursos críticos
        const criticalResources = [
            'assets/animations.css',
            'assets/performance.css',
            'assets/modern.css'
        ];

        criticalResources.forEach(resource => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = resource;
            link.as = 'style';
            document.head.appendChild(link);
        });
    }

    // Método para otimizar elementos específicos
    optimizeElement(element) {
        if (element) {
            element.style.willChange = 'transform';
            element.style.backfaceVisibility = 'hidden';
            element.style.perspective = '1000px';
        }
    }

    // Método para limpar otimizações
    cleanupElement(element) {
        if (element) {
            element.style.willChange = 'auto';
            element.style.backfaceVisibility = 'visible';
            element.style.perspective = 'none';
        }
    }
}

// Inicializar otimizador de performance
const performanceOptimizer = new PerformanceOptimizer();

// Funções utilitárias globais
window.optimizeElement = (selector) => {
    const elements = document.querySelectorAll(selector);
    elements.forEach(el => performanceOptimizer.optimizeElement(el));
};

window.cleanupElement = (selector) => {
    const elements = document.querySelectorAll(selector);
    elements.forEach(el => performanceOptimizer.cleanupElement(el));
};

// Otimizações específicas para mobile
if (window.innerWidth <= 768) {
    // Reduzir complexidade de animações no mobile
    document.documentElement.style.setProperty('--animation-duration-fast', '0.1s');
    document.documentElement.style.setProperty('--animation-duration-normal', '0.2s');
    document.documentElement.style.setProperty('--animation-duration-slow', '0.3s');
}

// Otimizações de performance para scroll
let scrollTimeout;
window.addEventListener('scroll', () => {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        // Limpar will-change após scroll
        const elements = document.querySelectorAll('[style*="will-change"]');
        elements.forEach(el => {
            if (el.style.willChange === 'transform') {
                el.style.willChange = 'auto';
            }
        });
    }, 150);
});

// Otimizações de performance para resize
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        // Recalcular otimizações após resize
        performanceOptimizer.init();
    }, 250);
});

// Exportar para uso em módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PerformanceOptimizer;
}
