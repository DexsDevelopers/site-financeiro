// assets/js/onboarding.js
// Onboarding visual usando Driver.js
(function () {
    const ONBOARDING_KEY = 'onboarding-v1-shown';

    function getDriver() {
        const drv = window.driver || (window['driver.js'] && window['driver.js'].driver);
        if (!drv) return null;
        return drv;
    }

    function exists(selector) {
        return !!document.querySelector(selector);
    }

    function buildSteps() {
        const isMobile = window.innerWidth < 768;
        const steps = [];

        if (exists('.navbar-toggler-custom')) {
            steps.push({
                element: '.navbar-toggler-custom',
                popover: {
                    title: 'Menu lateral',
                    description: 'Abra/feche o menu em telas menores.',
                    side: isMobile ? 'bottom' : 'right',
                    align: 'start'
                }
            });
        }

        if (exists('#sidebarMenu')) {
            steps.push({
                element: '#sidebarMenu',
                popover: {
                    title: 'Navegação',
                    description: 'Use as seções e páginas para navegar pelo sistema.',
                    side: 'right',
                    align: 'center'
                }
            });
        }

        if (exists('a.nav-link[href="dashboard.php"]')) {
            steps.push({
                element: 'a.nav-link[href="dashboard.php"]',
                popover: {
                    title: 'Dashboard',
                    description: 'Resumo geral com cartões e métricas importantes.',
                    side: 'right',
                    align: 'start'
                }
            });
        }

        if (exists('.main-content')) {
            steps.push({
                element: '.main-content',
                popover: {
                    title: 'Conteúdo principal',
                    description: 'Aqui você interage com cards, listas e relatórios.',
                    side: isMobile ? 'top' : 'bottom',
                    align: 'center'
                }
            });
        }

        if (exists('.card')) {
            steps.push({
                element: '.card',
                popover: {
                    title: 'Cards',
                    description: 'Informações organizadas com ações rápidas e indicadores.',
                    side: isMobile ? 'top' : 'bottom',
                    align: 'center'
                }
            });
        }

        return steps;
    }

    function startTour(force = false) {
        const driver = getDriver();
        if (!driver) return;

        if (!force && localStorage.getItem(ONBOARDING_KEY) === '1') {
            return;
        }

        const steps = buildSteps();
        if (!steps.length) return;

        const drv = driver({
            showProgress: true,
            overlayColor: 'rgba(0,0,0,0.6)',
            stagePadding: 6,
            smoothScroll: true,
            allowClose: true,
            animate: true,
            keyboardControl: true,
            onCloseClick: () => {},
            popoverClass: 'driver-popover--dark'
        });

        drv.drive(steps);
        localStorage.setItem(ONBOARDING_KEY, '1');
    }

    function createHelpFab() {
        if (document.getElementById('onboarding-help-fab')) return;
        const btn = document.createElement('button');
        btn.id = 'onboarding-help-fab';
        btn.type = 'button';
        btn.innerHTML = '<i class="bi bi-question-lg"></i>';
        btn.title = 'Ajuda / Ver Tutorial';
        btn.style.position = 'fixed';
        btn.style.bottom = '20px';
        btn.style.right = '20px';
        btn.style.zIndex = '1002';
        btn.style.border = 'none';
        btn.style.borderRadius = '50%';
        btn.style.width = '46px';
        btn.style.height = '46px';
        btn.style.display = 'flex';
        btn.style.alignItems = 'center';
        btn.style.justifyContent = 'center';
        btn.style.cursor = 'pointer';
        btn.style.background = 'linear-gradient(135deg, var(--accent-red), #764ba2)';
        btn.style.color = '#fff';
        btn.style.boxShadow = '0 8px 24px rgba(0,0,0,0.25)';
        btn.style.transition = 'transform .15s ease, box-shadow .15s ease';
        btn.onmouseenter = () => { btn.style.transform = 'translateY(-2px)'; btn.style.boxShadow = '0 10px 28px rgba(0,0,0,0.3)'; };
        btn.onmouseleave = () => { btn.style.transform = 'translateY(0)'; btn.style.boxShadow = '0 8px 24px rgba(0,0,0,0.25)'; };
        btn.addEventListener('click', () => startTour(true));
        document.body.appendChild(btn);
    }

    // Expor função global
    window.startOnboardingTour = () => startTour(true);

    document.addEventListener('DOMContentLoaded', function () {
        // Incluir Driver.js JS (se não estiver presente)
        if (!getDriver()) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/driver.js/dist/driver.min.js';
            script.defer = true;
            script.onload = () => {
                setTimeout(() => startTour(false), 800);
                createHelpFab();
            };
            document.head.appendChild(script);
        } else {
            setTimeout(() => startTour(false), 800);
            createHelpFab();
        }
    });
})();


