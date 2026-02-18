<?php
// templates/footer.php (Versão Corrigida com Popup Obrigatório apenas 1 vez)
?>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-bell-fill me-2"></i>
            <strong class="me-auto" id="toastTitle">Notificação</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastBody">
            Mensagem da notificação.
        </div>
    </div>
</div>

<!-- Dependências JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/countup.js@2.8.0/dist/countUp.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Onboarding (TourLite + passos por página) -->
<script src="assets/js/tour-lite.js" defer></script>
<script src="assets/js/onboarding-steps.js" defer></script>
<script src="assets/js/onboarding.js" defer></script>

<script>
    // Função global para mostrar notificações "Toast"
    function showToast(title, message, isError = false) {
        const toastLiveExample = document.getElementById('liveToast');
        if (!toastLiveExample) return;
        const toastInstance = bootstrap.Toast.getOrCreateInstance(toastLiveExample);
        const toastTitle = document.getElementById('toastTitle');
        const toastBody = document.getElementById('toastBody');
        toastTitle.textContent = title;
        toastBody.textContent = message;
        toastLiveExample.querySelector('.toast-header').classList.toggle('bg-danger', isError);
        toastLiveExample.querySelector('.toast-header').classList.toggle('text-white', isError);
        toastInstance.show();
    }

    // Função auxiliar para escapar HTML no JavaScript e prevenir XSS
    function escapeHTML(str) {
        const p = document.createElement('p');
        p.appendChild(document.createTextNode(str));
        return p.innerHTML;
    }
    
    // CORREÇÃO GLOBAL DEFINITIVA: Modais Bootstrap
    window._activeTourInstance = null;
    
    // Função para remover TODOS os elementos que podem bloquear modais
    function removeAllBlockingElements() {
        // Parar o tour se estiver ativo
        if (window._activeTourInstance && window._activeTourInstance.active) {
            try { window._activeTourInstance.stop(); } catch(e) {}
        }
        
        // Lista de seletores de elementos que podem bloquear
        const blockingSelectors = [
            '.tourlite-overlay',
            '.tourlite-hole', 
            '.tourlite-tooltip',
            '.modal-overlay:not(.modal)',
            '.pwa-modal-overlay',
            '#onboarding-help-fab'
        ];
        
        // Remover ou esconder todos os elementos bloqueantes
        blockingSelectors.forEach(selector => {
            document.querySelectorAll(selector).forEach(el => {
                el.style.display = 'none';
                el.style.visibility = 'hidden';
                el.style.pointerEvents = 'none';
                el.style.zIndex = '-9999';
            });
        });
        
        // Remover qualquer elemento com z-index maior que 1060 que não seja modal
        document.querySelectorAll('*').forEach(el => {
            const style = window.getComputedStyle(el);
            const zIndex = parseInt(style.zIndex);
            if (zIndex > 1060 && 
                !el.classList.contains('modal') && 
                !el.classList.contains('modal-backdrop') &&
                !el.classList.contains('modal-dialog') &&
                !el.classList.contains('modal-content') &&
                !el.closest('.modal')) {
                el.style.zIndex = '0';
                el.style.pointerEvents = 'none';
            }
        });
    }
    
    // Quando qualquer modal Bootstrap for abrir
    document.addEventListener('show.bs.modal', function(event) {
        const modal = event.target;
        
        // Remover elementos bloqueantes
        removeAllBlockingElements();
        
        // Mover modal para o final do body para garantir que está no topo
        document.body.appendChild(modal);
        
        // Forçar z-index correto
        modal.style.zIndex = '1050';
        modal.style.position = 'fixed';
        
        // Garantir pointer-events no modal-content
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.pointerEvents = 'auto';
            modalContent.style.position = 'relative';
            modalContent.style.zIndex = '1052';
        }
        
        // Garantir que dialog permite cliques no content
        const modalDialog = modal.querySelector('.modal-dialog');
        if (modalDialog) {
            modalDialog.style.pointerEvents = 'none';
            modalDialog.style.zIndex = '1051';
        }
    });
    
    // Após modal abrir completamente
    document.addEventListener('shown.bs.modal', function(event) {
        const modal = event.target;
        
        // Remover elementos bloqueantes novamente
        removeAllBlockingElements();
        
        // Garantir backdrop atrás do modal
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.style.zIndex = '1040';
            // Mover backdrop para antes do modal no DOM
            modal.parentNode.insertBefore(backdrop, modal);
        }
        
        // Garantir todos os inputs são clicáveis
        modal.querySelectorAll('input, select, textarea, button, .btn, .form-control, .form-select').forEach(el => {
            el.style.pointerEvents = 'auto';
            el.style.position = 'relative';
            el.style.zIndex = '10';
        });
        
        // Foco no primeiro input
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            setTimeout(() => {
                firstInput.style.pointerEvents = 'auto';
                firstInput.focus();
            }, 100);
        }
    });
    
    // Esconder FAB de ajuda quando modal está aberto
    document.addEventListener('show.bs.modal', function() {
        const fab = document.getElementById('onboarding-help-fab');
        if (fab) fab.style.display = 'none';
    });
    
    document.addEventListener('hidden.bs.modal', function() {
        const fab = document.getElementById('onboarding-help-fab');
        if (fab) fab.style.display = 'flex';
    });
</script>

<?php
// Verifica se é necessário exibir o popup
$popupNecessario = true;

if (
    (!empty($_SESSION['notificacao_vista']) && $_SESSION['notificacao_vista'] == 1) ||
    (!empty($_SESSION['user']['notificacao_vista']) && $_SESSION['user']['notificacao_vista'] == 1)
) {
    $popupNecessario = false;
}
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($popupNecessario): ?>
            // Pop-up obrigatório SweetAlert2 para cadastro do WhatsApp
            setTimeout(() => {
                Swal.fire({
                    title: '<i class="bi bi-whatsapp text-success me-2"></i>Receba Novidades!',
                    html: `
                        <p class="text-start">Para continuar, cadastre seu WhatsApp e receba novidades e lembretes importantes. Nós não enviamos spam!</p>
                        <input id="swal-input-telefone" type="tel" class="form-control" placeholder="(DDD) 9XXXX-XXXX" required>
                    `,
                    icon: 'question',
                    confirmButtonText: 'Salvar e Receber',
                    showCancelButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        const telefone = document.getElementById('swal-input-telefone').value.trim();
                        if (!telefone || telefone.length < 10) {
                            Swal.showValidationMessage('Por favor, insira um número válido com DDD.');
                            return false;
                        }
                        return fetch('salvar_telefone.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ telefone: telefone }),
                            credentials: 'same-origin'
                        })
                        .then(async response => {
                            const data = await response.json().catch(() => null);
                            if (!response.ok || !data || data.success === false) {
                                throw new Error(data?.error || 'Erro inesperado ao salvar.');
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(error.message);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value && result.value.success) {
                        Swal.fire({
                            title: 'Sucesso!',
                            text: result.value.message || 'Seu número foi salvo. Fique de olho nas novidades!',
                            icon: 'success',
                            confirmButtonText: 'Ótimo!'
                        });
                    }
                });
            }, 1000);
        <?php endif; ?>
    });
</script>

<!-- OneSignal Push (inicializa apenas se houver SW OneSignal registrado) -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
    (function(){
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(regs){
                var hasOneSignal = regs.some(function(r){
                    try { return r.active && r.active.scriptURL && r.active.scriptURL.indexOf('OneSignalSDKWorker.js') !== -1; } catch(e){ return false; }
                });
                if (hasOneSignal) {
                    OneSignalDeferred.push(async function(OneSignal) {
                        try { await OneSignal.init({ appId: "8b948d38-c99d-402b-a456-e99e66fcc60f" }); } catch(e) { console.log('OneSignal init falhou:', e); }
                    });
                } else {
                    console.log('OneSignal desativado: sem SW registrado.');
                }
            }).catch(function(){ console.log('OneSignal desativado.'); });
        }
    })();
</script>

<!-- PWA JavaScript -->
<script>
    // PWA Install Prompt
    let deferredPrompt;
    let installPromptShown = false;
    
    // Verificar se já foi mostrado o prompt
    if (localStorage.getItem('pwa-install-prompt-shown') === 'true') {
        installPromptShown = true;
    }
    
    // Detectar Safari
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    
    // Registrar service worker apropriado
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            // Usar service worker minimalista para Safari
            const swFile = (isSafari || isIOS) ? '/sw-minimal.js' : '/sw.js';
            
            navigator.serviceWorker.register(swFile)
                .then(registration => {
                    console.log('SW registrado: ', registration);
                    
                    // Verificar atualizações apenas para navegadores não-Safari
                    if (!isSafari && !isIOS) {
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // Nova versão disponível
                                    showUpdateAvailable();
                                }
                            });
                        });
                    }
                })
                .catch(registrationError => {
                    console.log('SW falhou: ', registrationError);
                    
                    // Para Safari, não tentar fallback para evitar mais erros
                    if (!isSafari && !isIOS) {
                        console.log('Tentando service worker minimalista...');
                        navigator.serviceWorker.register('/sw-minimal.js')
                            .then(registration => {
                                console.log('SW minimalista registrado: ', registration);
                            })
                            .catch(error => {
                                console.log('SW minimalista também falhou: ', error);
                            });
                    }
                });
        });
    }
    
    // Capturar evento beforeinstallprompt
    window.addEventListener('beforeinstallprompt', (e) => {
        console.log('beforeinstallprompt disparado');
        
        // Prevenir o prompt padrão
        e.preventDefault();
        
        // Salvar o evento para usar depois
        deferredPrompt = e;
        
        // Mostrar nosso prompt personalizado se ainda não foi mostrado
        if (!installPromptShown) {
            showInstallPrompt();
        }
    });
    
    // Mostrar prompt de instalação personalizado
    function showInstallPrompt() {
        const installPrompt = document.getElementById('installPrompt');
        if (installPrompt) {
            installPrompt.classList.add('show');
            installPromptShown = true;
            localStorage.setItem('pwa-install-prompt-shown', 'true');
        }
    }
    
    // Instalar PWA
    document.addEventListener('click', (e) => {
        if (e.target.id === 'installBtn') {
            if (deferredPrompt) {
                // Mostrar o prompt de instalação nativo
                deferredPrompt.prompt();
                
                // Aguardar a resposta do usuário
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('Usuário aceitou a instalação');
                        showToast('Sucesso!', 'App instalado com sucesso!');
                    } else {
                        console.log('Usuário rejeitou a instalação');
                    }
                    
                    // Limpar o prompt
                    deferredPrompt = null;
                    hideInstallPrompt();
                });
            } else {
                // Fallback para navegadores que não suportam beforeinstallprompt
                showInstallInstructions();
            }
        }
        
        if (e.target.id === 'dismissBtn') {
            hideInstallPrompt();
        }
    });
    
    // Esconder prompt de instalação
    function hideInstallPrompt() {
        const installPrompt = document.getElementById('installPrompt');
        if (installPrompt) {
            installPrompt.classList.remove('show');
        }
    }
    
    // Mostrar instruções de instalação manual
    function showInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);
        
        let instructions = '';
        
        if (isIOS) {
            instructions = `
                <div class="text-start">
                    <h6>Como instalar no iPhone/iPad:</h6>
                    <ol>
                        <li>Toque no botão de compartilhar <i class="bi bi-share"></i></li>
                        <li>Role para baixo e toque em "Adicionar à Tela Inicial"</li>
                        <li>Toque em "Adicionar" no canto superior direito</li>
                    </ol>
                </div>
            `;
        } else if (isAndroid) {
            instructions = `
                <div class="text-start">
                    <h6>Como instalar no Android:</h6>
                    <ol>
                        <li>Toque no menu do navegador (três pontos)</li>
                        <li>Selecione "Adicionar à tela inicial" ou "Instalar app"</li>
                        <li>Toque em "Adicionar" ou "Instalar"</li>
                    </ol>
                </div>
            `;
        } else {
            instructions = `
                <div class="text-start">
                    <h6>Como instalar:</h6>
                    <ol>
                        <li>Procure pelo ícone de instalação na barra de endereços</li>
                        <li>Ou use o menu do navegador para "Instalar app"</li>
                        <li>Siga as instruções na tela</li>
                    </ol>
                </div>
            `;
        }
        
        Swal.fire({
            title: 'Instalar App',
            html: instructions,
            icon: 'info',
            confirmButtonText: 'Entendi'
        });
    }
    
    // Mostrar notificação de atualização disponível (COM CONTROLE DE FREQUÊNCIA)
    function showUpdateAvailable() {
        // Verificar se já foi mostrado nas últimas 24 horas
        const lastShown = localStorage.getItem('pwa-update-last-shown');
        const now = Date.now();
        const oneDay = 24 * 60 * 60 * 1000; // 24 horas em milissegundos
        
        // Se foi mostrado há menos de 24 horas, não mostrar novamente
        if (lastShown && (now - parseInt(lastShown)) < oneDay) {
            console.log('Modal de atualização já foi mostrado nas últimas 24h. Ignorando...');
            return;
        }
        
        // Marcar como mostrado agora
        localStorage.setItem('pwa-update-last-shown', now.toString());
        
        Swal.fire({
            title: 'Atualização Disponível',
            text: 'Uma nova versão do app está disponível. Deseja atualizar?',
            icon: 'info',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Atualizar Agora',
            cancelButtonText: 'Lembrar Depois',
            denyButtonText: 'Não Mostrar Hoje',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            denyButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                // Atualizar agora
                window.location.reload();
            } else if (result.isDenied) {
                // Não mostrar por 24 horas
                localStorage.setItem('pwa-update-last-shown', now.toString());
                showToast('OK', 'Não mostraremos esta notificação novamente hoje.');
            } else {
                // Lembrar em 1 hora
                const oneHour = 60 * 60 * 1000;
                localStorage.setItem('pwa-update-last-shown', (now - oneDay + oneHour).toString());
            }
        });
    }
    
    // Detectar se está rodando como PWA
    function isPWA() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    }
    
    // Adicionar classe PWA ao body se estiver rodando como app
    if (isPWA()) {
        document.body.classList.add('pwa-mode');
    }
    
    // Detectar mudanças na conexão
    window.addEventListener('online', () => {
        showToast('Conexão Restaurada', 'Você está online novamente!');
    });
    
    window.addEventListener('offline', () => {
        showToast('Sem Conexão', 'Você está offline. Algumas funcionalidades podem estar limitadas.');
    });
    
    // Verificar se está offline
    if (!navigator.onLine) {
        showToast('Modo Offline', 'Você está offline. Algumas funcionalidades podem estar limitadas.');
    }
</script>

</body>
</html>
