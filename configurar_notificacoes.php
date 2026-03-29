<?php
require_once 'templates/header.php';
?>

<style>
.notif-page { max-width: 780px; margin: 0 auto; }

/* STATUS CARD */
.status-card {
    background: var(--card-background);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1.75rem;
    margin-bottom: 1.5rem;
}
.status-card h2 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1.25rem; }

.status-row {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
}
.status-row:last-child { border-bottom: none; }
.status-dot {
    width: 12px; height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
    background: #6c757d;
}
.status-dot.ok   { background: #00b894; box-shadow: 0 0 6px rgba(0,184,148,.5); }
.status-dot.warn { background: #ffc107; box-shadow: 0 0 6px rgba(255,193,7,.5); }
.status-dot.bad  { background: #e50914; box-shadow: 0 0 6px rgba(229,9,20,.5); }
.status-label { flex: 1; font-size: .93rem; color: var(--text-secondary); }
.status-value { font-size: .85rem; font-weight: 600; color: var(--text-light); }

/* STEPS */
.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.step-card {
    background: var(--card-background);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1.4rem 1.2rem;
    text-align: center;
    position: relative;
    transition: border-color .2s;
}
.step-card.done  { border-color: #00b894; }
.step-card.active { border-color: #0984e3; }
.step-num {
    position: absolute;
    top: -12px; left: 50%;
    transform: translateX(-50%);
    background: var(--accent-red);
    color: #fff;
    border-radius: 50%;
    width: 26px; height: 26px;
    font-size: .75rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.step-card.done .step-num  { background: #00b894; }
.step-card.active .step-num { background: #0984e3; }
.step-icon { font-size: 2rem; margin: .5rem 0 .6rem; }
.step-title { font-weight: 700; margin-bottom: .3rem; font-size: .95rem; }
.step-desc  { font-size: .8rem; color: var(--text-secondary); line-height: 1.5; }

/* ACTION BUTTONS */
.action-card {
    background: var(--card-background);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.action-card .action-info { flex: 1; min-width: 200px; }
.action-card .action-info h3 { font-size: 1rem; font-weight: 700; margin-bottom: .25rem; }
.action-card .action-info p  { font-size: .85rem; color: var(--text-secondary); margin: 0; }
.btn-activate {
    padding: .65rem 1.4rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: .9rem;
    border: none;
    cursor: pointer;
    transition: all .2s;
    display: flex; align-items: center; gap: .5rem;
    white-space: nowrap;
}
.btn-activate.subscribe   { background: linear-gradient(135deg,#0984e3,#064a8c); color:#fff; }
.btn-activate.unsubscribe { background: rgba(229,9,20,.15); color:#e50914; border:1px solid rgba(229,9,20,.3); }
.btn-activate:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,.3); }
.btn-activate:disabled { opacity:.6; cursor:not-allowed; transform:none; }

/* TEST */
.test-card {
    background: var(--card-background);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.test-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: .75rem; }
.btn-test {
    background: rgba(0,184,148,.12);
    color: #00b894;
    border: 1px solid rgba(0,184,148,.3);
    border-radius: 50px;
    padding: .6rem 1.4rem;
    font-weight: 600;
    font-size: .9rem;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex; align-items: center; gap: .5rem;
}
.btn-test:hover { background: rgba(0,184,148,.22); }
.btn-test:disabled { opacity:.6; cursor:not-allowed; }

/* HISTORY */
.history-card {
    background: var(--card-background);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.history-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
.notif-item {
    display: flex;
    gap: .9rem;
    padding: .75rem;
    border-radius: 8px;
    background: rgba(255,255,255,.03);
    margin-bottom: .5rem;
    align-items: flex-start;
}
.notif-item .notif-icon { font-size: 1.2rem; flex-shrink:0; margin-top:2px; }
.notif-item .notif-body { flex:1; min-width:0; }
.notif-item .notif-title { font-weight: 600; font-size:.9rem; }
.notif-item .notif-msg { font-size:.8rem; color:var(--text-secondary); margin-top:.15rem; }
.notif-item .notif-time { font-size:.75rem; color:var(--text-secondary); flex-shrink:0; margin-top:3px; }
.notif-item.unread { border-left: 3px solid #0984e3; }

/* FAQ */
.faq-card {
    background: var(--card-background);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1.5rem;
}
.faq-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
.faq-item { padding: .75rem 0; border-bottom: 1px solid var(--border-color); }
.faq-item:last-child { border-bottom: none; }
.faq-q { font-weight: 600; font-size: .9rem; margin-bottom: .35rem; color: var(--text-light); }
.faq-a { font-size: .83rem; color: var(--text-secondary); line-height: 1.6; }
</style>

<div class="notif-page">

    <!-- TÍTULO -->
    <div class="mb-4">
        <h1 class="h4 fw-bold"><i class="bi bi-bell-fill me-2" style="color:#0984e3;"></i>Notificações Push</h1>
        <p class="text-white-50 mb-0">Configure e teste as notificações nativas do aplicativo no seu dispositivo.</p>
    </div>

    <!-- STATUS -->
    <div class="status-card">
        <h2><i class="bi bi-activity me-2"></i>Status do Dispositivo</h2>
        <div class="status-row">
            <div class="status-dot" id="dot-support"></div>
            <div class="status-label">Suporte do navegador</div>
            <div class="status-value" id="val-support">Verificando...</div>
        </div>
        <div class="status-row">
            <div class="status-dot" id="dot-sw"></div>
            <div class="status-label">Service Worker registrado</div>
            <div class="status-value" id="val-sw">Verificando...</div>
        </div>
        <div class="status-row">
            <div class="status-dot" id="dot-perm"></div>
            <div class="status-label">Permissão de notificações</div>
            <div class="status-value" id="val-perm">Verificando...</div>
        </div>
        <div class="status-row">
            <div class="status-dot" id="dot-sub"></div>
            <div class="status-label">Inscrito para receber push</div>
            <div class="status-value" id="val-sub">Verificando...</div>
        </div>
    </div>

    <!-- PASSO A PASSO -->
    <div class="steps-grid" id="steps-grid">
        <div class="step-card" id="step1">
            <div class="step-num">1</div>
            <div class="step-icon">🌐</div>
            <div class="step-title">Navegador compatível</div>
            <div class="step-desc">Chrome, Edge, Firefox ou Safari 16.4+ no iPhone (como app instalado).</div>
        </div>
        <div class="step-card" id="step2">
            <div class="step-num">2</div>
            <div class="step-icon">🔔</div>
            <div class="step-title">Permitir notificações</div>
            <div class="step-desc">O navegador vai pedir permissão. Clique em <b>Permitir</b> para continuar.</div>
        </div>
        <div class="step-card" id="step3">
            <div class="step-num">3</div>
            <div class="step-icon">✅</div>
            <div class="step-title">Ativar neste dispositivo</div>
            <div class="step-desc">Clique no botão abaixo para registrar este dispositivo e receber alertas.</div>
        </div>
    </div>

    <!-- ATIVAR / DESATIVAR -->
    <div class="action-card">
        <div class="action-info">
            <h3 id="action-title">Ativar notificações</h3>
            <p id="action-desc">Receba alertas de tarefas, gastos e resumos financeiros diretamente neste dispositivo.</p>
        </div>
        <button class="btn-activate subscribe" id="btn-main-action" onclick="handleMainAction()">
            <i class="bi bi-bell-fill" id="btn-main-icon"></i>
            <span id="btn-main-label">Ativar agora</span>
        </button>
    </div>

    <!-- TESTAR -->
    <div class="test-card" id="test-section" style="display:none;">
        <h3><i class="bi bi-send me-2 text-success"></i>Testar notificações</h3>
        <p class="small text-white-50 mb-3">Envie uma notificação de teste para confirmar que tudo está funcionando. Ela chegará em segundos.</p>
        <button class="btn-test" id="btn-test" onclick="sendTestPush()">
            <i class="bi bi-send-fill"></i> Enviar teste agora
        </button>
        <span id="test-result" class="ms-3 small" style="display:none;"></span>
    </div>

    <!-- HISTÓRICO -->
    <div class="history-card">
        <h3><i class="bi bi-clock-history me-2"></i>Notificações recentes</h3>
        <div id="history-list">
            <div class="text-center py-3 text-white-50 small"><span class="spinner-border spinner-border-sm me-2"></span>Carregando...</div>
        </div>
    </div>

    <!-- FAQ -->
    <div class="faq-card">
        <h3><i class="bi bi-question-circle me-2"></i>Problemas comuns</h3>

        <div class="faq-item">
            <div class="faq-q">🍎 Tenho iPhone e não recebo notificações.</div>
            <div class="faq-a">No iPhone, as notificações push só funcionam se o app estiver <b>instalado na tela inicial</b>. Toque em <b>Compartilhar → Adicionar à Tela de Início</b>, abra o app instalado e ative aqui novamente.</div>
        </div>

        <div class="faq-item">
            <div class="faq-q">🚫 A permissão foi bloqueada. O que fazer?</div>
            <div class="faq-a">Clique no ícone de cadeado na barra de endereço do navegador → <b>Configurações do site</b> → mude <b>Notificações</b> para <b>Permitir</b> e recarregue a página.</div>
        </div>

        <div class="faq-item">
            <div class="faq-q">📱 Ativei mas não recebi o teste.</div>
            <div class="faq-a">Verifique se as notificações não estão silenciadas no sistema operacional. No Android: <b>Configurações → Apps → Navegador → Notificações</b>. No iOS: <b>Ajustes → Notificações → App</b>.</div>
        </div>

        <div class="faq-item">
            <div class="faq-q">🔄 Ativei em outro dispositivo. Preciso fazer de novo?</div>
            <div class="faq-a">Sim. Cada navegador/dispositivo precisa ser ativado separadamente. Você pode ter vários dispositivos cadastrados ao mesmo tempo.</div>
        </div>
    </div>

</div>

<script>
const PUSH_TEST = window.PUSH_TEST_PATH || 'api_push_test.php';
const NOTIF_API = window.NOTIF_API_PATH || 'api_notificacoes.php';

// ─── STATUS ──────────────────────────────────────────────────────────────────
function setDot(id, state, text) {
    const dot = document.getElementById('dot-' + id);
    const val = document.getElementById('val-' + id);
    if (dot) { dot.className = 'status-dot ' + state; }
    if (val)  { val.textContent = text; }
}

function setStep(num, state) {
    const el = document.getElementById('step' + num);
    if (el) { el.className = 'step-card ' + state; }
}

async function checkStatus() {
    // Suporte
    const hasSupport = ('serviceWorker' in navigator) && ('PushManager' in window);
    setDot('support', hasSupport ? 'ok' : 'bad', hasSupport ? 'Suportado' : 'Não suportado');
    setStep(1, hasSupport ? 'done' : '');

    if (!hasSupport) {
        setDot('sw',   'bad', 'N/A');
        setDot('perm', 'bad', 'N/A');
        setDot('sub',  'bad', 'N/A');
        setStep(2, ''); setStep(3, '');
        setActionState('unsupported');
        return;
    }

    // Service Worker
    let sw = null;
    try {
        sw = await navigator.serviceWorker.ready;
        setDot('sw', 'ok', 'Registrado');
    } catch (e) {
        setDot('sw', 'bad', 'Erro');
    }

    // Permissão
    const perm = Notification.permission;
    if (perm === 'granted') { setDot('perm', 'ok', 'Permitida');  setStep(2, 'done'); }
    else if (perm === 'denied') { setDot('perm', 'bad', 'Bloqueada'); setStep(2, ''); }
    else { setDot('perm', 'warn', 'Aguardando'); setStep(2, 'active'); }

    // Subscription
    if (sw) {
        try {
            const sub = await sw.pushManager.getSubscription();
            if (sub) {
                setDot('sub', 'ok', 'Inscrito ✓');
                setStep(3, 'done');
                setActionState('subscribed');
            } else {
                setDot('sub', perm === 'denied' ? 'bad' : 'warn', 'Não inscrito');
                setStep(3, perm === 'granted' ? 'active' : '');
                setActionState(perm === 'denied' ? 'denied' : 'default');
            }
        } catch (e) {
            setDot('sub', 'bad', 'Erro');
            setActionState('default');
        }
    }
}

// ─── BOTÃO PRINCIPAL ─────────────────────────────────────────────────────────
function setActionState(state) {
    const btn   = document.getElementById('btn-main-action');
    const icon  = document.getElementById('btn-main-icon');
    const label = document.getElementById('btn-main-label');
    const title = document.getElementById('action-title');
    const desc  = document.getElementById('action-desc');
    const testSection = document.getElementById('test-section');

    switch (state) {
        case 'subscribed':
            btn.className = 'btn-activate unsubscribe';
            icon.className = 'bi bi-bell-slash-fill';
            label.textContent = 'Desativar notificações';
            title.textContent = '🔔 Notificações ativas neste dispositivo';
            desc.textContent = 'Você está inscrito e receberá alertas. Clique para desativar.';
            testSection.style.display = 'block';
            break;
        case 'denied':
            btn.className = 'btn-activate';
            btn.style.background = 'rgba(255,193,7,.15)';
            btn.style.color = '#ffc107';
            btn.style.border = '1px solid rgba(255,193,7,.3)';
            icon.className = 'bi bi-slash-circle';
            label.textContent = 'Permissão bloqueada';
            title.textContent = '⚠️ Permissão bloqueada pelo navegador';
            desc.textContent = 'Clique no cadeado na barra de endereço → Notificações → Permitir. Depois recarregue.';
            testSection.style.display = 'none';
            btn.disabled = true;
            break;
        case 'unsupported':
            btn.className = 'btn-activate';
            btn.style.background = 'rgba(108,117,125,.15)';
            btn.style.color = '#adb5bd';
            icon.className = 'bi bi-exclamation-circle';
            label.textContent = 'Não suportado';
            title.textContent = '❌ Navegador não suportado';
            desc.textContent = 'Use Chrome, Edge ou Firefox. No iPhone, instale o app primeiro.';
            testSection.style.display = 'none';
            btn.disabled = true;
            break;
        default:
            btn.className = 'btn-activate subscribe';
            icon.className = 'bi bi-bell-fill';
            label.textContent = 'Ativar agora';
            title.textContent = 'Ativar notificações neste dispositivo';
            desc.textContent = 'Receba alertas de tarefas, gastos e resumos financeiros diretamente aqui.';
            testSection.style.display = 'none';
    }
}

async function handleMainAction() {
    if (!window.AppPush) { showToast('Erro', 'Módulo de notificações não carregado.', true); return; }
    const btn = document.getElementById('btn-main-action');
    btn.disabled = true;
    document.getElementById('btn-main-label').textContent = 'Aguarde...';
    await window.AppPush.toggle();
    await new Promise(r => setTimeout(r, 800));
    await checkStatus();
    loadHistory();
    btn.disabled = false;
}

// ─── TESTE ────────────────────────────────────────────────────────────────────
async function sendTestPush() {
    const btn = document.getElementById('btn-test');
    const result = document.getElementById('test-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
    result.style.display = 'none';

    try {
        const res = await fetch(PUSH_TEST);
        const data = await res.json();
        result.style.display = 'inline';
        if (data.success) {
            result.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>Notificação enviada! Verifique seu dispositivo.';
        } else {
            result.innerHTML = '<i class="bi bi-exclamation-circle-fill text-warning me-1"></i>' + (data.message || 'Falha ao enviar.');
        }
    } catch (e) {
        result.style.display = 'inline';
        result.innerHTML = '<i class="bi bi-x-circle-fill text-danger me-1"></i>Erro de conexão.';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill"></i> Enviar teste novamente';
}

// ─── HISTÓRICO ───────────────────────────────────────────────────────────────
async function loadHistory() {
    const list = document.getElementById('history-list');
    try {
        const res = await fetch(NOTIF_API + '?action=list&limit=8');
        const data = await res.json();

        if (!data.success || !data.notifications || data.notifications.length === 0) {
            list.innerHTML = '<div class="text-center py-3 text-white-50 small"><i class="bi bi-inbox me-2"></i>Nenhuma notificação ainda.</div>';
            return;
        }

        const icons = { info: '🔵', warning: '🟡', danger: '🔴', tarefa: '✅', financeiro: '💰' };

        list.innerHTML = data.notifications.map(n => {
            const icon = icons[n.tipo] || '🔔';
            const time = new Date(n.created_at).toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
            const unreadClass = n.lida == 0 ? 'unread' : '';
            return `<div class="notif-item ${unreadClass}">
                <div class="notif-icon">${icon}</div>
                <div class="notif-body">
                    <div class="notif-title">${n.titulo}</div>
                    <div class="notif-msg">${n.mensagem}</div>
                </div>
                <div class="notif-time">${time}</div>
            </div>`;
        }).join('');

        // Marcar como lidas
        fetch(NOTIF_API, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'mark_read' })
        });

    } catch (e) {
        list.innerHTML = '<div class="text-center py-3 text-white-50 small">Erro ao carregar histórico.</div>';
    }
}

// ─── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    checkStatus();
    loadHistory();
});
</script>

<?php require_once 'templates/footer.php'; ?>
