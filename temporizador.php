<?php
// temporizador.php (Versão Corrigida com Horas/Minutos e Bugs Resolvidos)

require_once 'templates/header.php';
?>

<style>
    .timer-container { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
    .timer-display { position: relative; width: 300px; height: 300px; margin: 2rem 0; }
    .timer-display svg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: rotate(-90deg); }
    .timer-progress-bg { fill: none; stroke: #333; stroke-width: 10; }
    .timer-progress { fill: none; stroke: var(--accent-red); stroke-width: 10; stroke-linecap: round; transition: stroke-dashoffset 1s linear, stroke 0.5s ease; }
    .timer-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: 'Roboto Mono', monospace; font-size: 4.5rem; font-weight: 700; }
    .timer-mode-selector .btn, .timer-controls .btn { min-width: 140px; }
    .cycles-display { color: var(--text-secondary); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Temporizador Pomodoro</h1>
    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalConfiguracoesTimer">
        <i class="bi bi-gear-fill"></i>
    </button>
</div>

<div class="card card-custom">
    <div class="card-body p-4 p-md-5">
        <div class="timer-container">
            <div class="timer-mode-selector btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary active" data-mode="pomodoro">Foco</button>
                <button type="button" class="btn btn-outline-secondary" data-mode="shortBreak">Pausa Curta</button>
                <button type="button" class="btn btn-outline-secondary" data-mode="longBreak">Pausa Longa</button>
            </div>

            <div class="timer-display">
                <svg viewBox="0 0 100 100">
                    <circle class="timer-progress-bg" cx="50" cy="50" r="45"></circle>
                    <circle class="timer-progress" cx="50" cy="50" r="45"></circle>
                </svg>
                <div id="timer-text" class="timer-text">25:00</div>
            </div>
            
            <div class="timer-controls">
                <button id="start-pause-btn" class="btn btn-danger btn-lg"><i class="bi bi-play-fill"></i> COMEÇAR</button>
            </div>
            <button id="reset-btn" class="btn btn-link text-muted mt-3"><i class="bi bi-arrow-counterclockwise"></i> Reiniciar</button>

            <div class="cycles-display mt-4">Ciclos de Foco Completos: <span id="cycles-count">0</span>/4</div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfiguracoesTimer" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configurar Tempos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formConfiguracoesTimer">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tempo de Foco (Pomodoro)</label>
                        <div class="input-group"><input type="number" id="config-pomodoro-h" class="form-control" min="0" placeholder="Horas"><span class="input-group-text">h</span><input type="number" id="config-pomodoro-m" class="form-control" min="0" max="59" placeholder="Minutos"><span class="input-group-text">min</span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tempo de Pausa Curta</label>
                        <div class="input-group"><input type="number" id="config-short-break-h" class="form-control" min="0" placeholder="Horas"><span class="input-group-text">h</span><input type="number" id="config-short-break-m" class="form-control" min="0" max="59" placeholder="Minutos"><span class="input-group-text">min</span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tempo de Pausa Longa</label>
                        <div class="input-group"><input type="number" id="config-long-break-h" class="form-control" min="0" placeholder="Horas"><span class="input-group-text">h</span><input type="number" id="config-long-break-m" class="form-control" min="0" max="59" placeholder="Minutos"><span class="input-group-text">min</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<audio id="notification-sound" src="https://www.soundjay.com/buttons/sounds/button-16.mp3" preload="auto"></audio>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- ELEMENTOS DO DOM ---
    const timerTextEl = document.getElementById('timer-text');
    const startPauseBtn = document.getElementById('start-pause-btn');
    const resetBtn = document.getElementById('reset-btn');
    const modeButtons = document.querySelectorAll('.timer-mode-selector button');
    const cyclesCountEl = document.getElementById('cycles-count');
    const notificationSound = document.getElementById('notification-sound');
    const progressCircle = document.querySelector('.timer-progress');
    const circleRadius = progressCircle.r.baseVal.value;
    const circleCircumference = 2 * Math.PI * circleRadius;
    
    // --- ELEMENTOS DA CONFIGURAÇÃO ---
    const formConfig = document.getElementById('formConfiguracoesTimer');
    const inputs = {
        pomodoro: { h: document.getElementById('config-pomodoro-h'), m: document.getElementById('config-pomodoro-m') },
        shortBreak: { h: document.getElementById('config-short-break-h'), m: document.getElementById('config-short-break-m') },
        longBreak: { h: document.getElementById('config-long-break-h'), m: document.getElementById('config-long-break-m') }
    };
    const configModal = new bootstrap.Modal(document.getElementById('modalConfiguracoesTimer'));

    // --- ESTADO DO TIMER ---
    let MODES = {
        pomodoro: { time: 25 * 60, color: 'var(--accent-red)' },
        shortBreak: { time: 5 * 60, color: '#00b894' },
        longBreak: { time: 15 * 60, color: '#0984e3' }
    };
    let currentMode = 'pomodoro';
    let timeLeft;
    let timerInterval = null;
    let cyclesCount = 0;
    
    // --- FUNÇÕES PRINCIPAIS ---
    function formatTime(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    function updateDisplay() {
        timerTextEl.textContent = formatTime(timeLeft);
        document.title = `${formatTime(timeLeft)} - ${currentMode === 'pomodoro' ? 'Foco' : 'Pausa'}`;
        
        const totalTime = MODES[currentMode].time;
        if (totalTime > 0) {
            const offset = circleCircumference - (timeLeft / totalTime) * circleCircumference;
            progressCircle.style.strokeDashoffset = offset;
        } else {
            progressCircle.style.strokeDashoffset = circleCircumference;
        }
    }

    function switchMode(newMode, resetCycles = false) {
        pauseTimer();
        currentMode = newMode;
        timeLeft = MODES[currentMode].time;
        if (resetCycles) {
            cyclesCount = 0;
            cyclesCountEl.textContent = '0';
        }
        
        modeButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.mode === newMode));
        startPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i> COMEÇAR';
        startPauseBtn.className = 'btn btn-danger btn-lg';
        progressCircle.style.stroke = MODES[currentMode].color;
        updateDisplay();
    }
    
    function startTimer() {
        if (timerInterval) return;
        timerInterval = setInterval(() => {
            if (timeLeft <= 0) {
                pauseTimer();
                notificationSound.play();
                if (currentMode === 'pomodoro') {
                    cyclesCount++;
                    cyclesCountEl.textContent = cyclesCount % 4;
                    if (cyclesCount > 0 && cyclesCount % 4 === 0) {
                        switchMode('longBreak');
                    } else {
                        switchMode('shortBreak');
                    }
                } else {
                    switchMode('pomodoro');
                }
                return; // Para a execução deste ciclo
            }
            timeLeft--;
            updateDisplay();
        }, 1000);
    }

    function pauseTimer() {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    
    // --- FUNÇÕES PARA CONFIGURAÇÃO ---
    function saveSettings() {
        let newSettings = {
            pomodoro: { color: MODES.pomodoro.color },
            shortBreak: { color: MODES.shortBreak.color },
            longBreak: { color: MODES.longBreak.color }
        };
        Object.keys(inputs).forEach(mode => {
            const h = parseInt(inputs[mode].h.value) || 0;
            const m = parseInt(inputs[mode].m.value) || 0;
            newSettings[mode].time = (h * 3600) + (m * 60);
        });
        localStorage.setItem('pomodoroSettings', JSON.stringify(newSettings));
        MODES = newSettings;
        configModal.hide();
        switchMode('pomodoro', true);
    }

    function applySettings() {
        const savedSettings = JSON.parse(localStorage.getItem('pomodoroSettings'));
        if (savedSettings && savedSettings.pomodoro && savedSettings.pomodoro.time !== undefined) {
             MODES = savedSettings;
        }
        
        // Atualiza os valores no formulário do modal
        Object.keys(inputs).forEach(mode => {
            const totalSeconds = MODES[mode].time;
            inputs[mode].h.value = Math.floor(totalSeconds / 3600);
            inputs[mode].m.value = Math.floor((totalSeconds % 3600) / 60);
        });
    }

    // --- EVENT LISTENERS ---
    startPauseBtn.addEventListener('click', () => {
        if (timerInterval) {
            pauseTimer();
            startPauseBtn.innerHTML = '<i class="bi bi-play-fill"></i> CONTINUAR';
            startPauseBtn.className = 'btn btn-danger btn-lg';
        } else {
            startTimer();
            startPauseBtn.innerHTML = '<i class="bi bi-pause-fill"></i> PAUSAR';
            startPauseBtn.className = 'btn btn-warning btn-lg';
        }
    });

    resetBtn.addEventListener('click', () => { if (confirm('Reiniciar o timer?')) { switchMode(currentMode); } });
    
    modeButtons.forEach(btn => { btn.addEventListener('click', () => switchMode(btn.dataset.mode)); });

    formConfig.addEventListener('submit', (e) => {
        e.preventDefault();
        saveSettings();
    });

    // --- INICIALIZAÇÃO ---
    progressCircle.style.strokeDasharray = circleCircumference;
    applySettings();
    switchMode('pomodoro', true);
});
</script>

<?php
require_once 'templates/footer.php';
?>