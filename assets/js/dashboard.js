// Dashboard JavaScript - Otimizado
document.addEventListener('DOMContentLoaded', function() {
    // Variáveis globais
    let pomodoroActive = false;
    let currentTaskId = null;
    
    // Função para mostrar toast
    function showToast(title, message, isError = false) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${isError ? '#dc3545' : '#28a745'};
            color: white;
            padding: 1rem;
            border-radius: 8px;
            z-index: 9999;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        toast.innerHTML = `<strong>${title}</strong><br>${message}`;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    // Função para completar tarefa
    function completeTask(taskId) {
        fetch('atualizar_status_tarefa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, status: 'concluida' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Tarefa concluída!', data.message);
                
                // Remover tarefa da lista
                const card = document.querySelector(`[data-id="${taskId}"]`);
                if (card) {
                    card.style.transform = 'translateX(-100%)';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                }
                
                // Atualizar contadores
                updateDashboardCounters();
            } else {
                showToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro de rede!', 'Não foi possível conectar ao servidor.', true);
        });
    }
    
    // Função para atualizar contadores do dashboard
    function updateDashboardCounters() {
        fetch('get_task_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar contadores de produtividade
                const hojeElement = document.querySelector('.stat-number.text-success');
                if (hojeElement) {
                    hojeElement.textContent = data.hoje.concluidas;
                }
                
                const semanaElement = document.querySelector('.stat-number.text-info');
                if (semanaElement) {
                    semanaElement.textContent = data.semana.concluidas;
                }
                
                const pendentesElement = document.querySelector('.stat-number.text-warning');
                if (pendentesElement) {
                    pendentesElement.textContent = data.pendentes;
                }
                
                // Atualizar barra de progresso
                const progressBar = document.querySelector('.progress-bar');
                if (progressBar && data.hoje.total > 0) {
                    const progressPercent = (data.hoje.concluidas / data.hoje.total) * 100;
                    progressBar.style.width = progressPercent + '%';
                    
                    // Atualizar texto do progresso
                    const progressText = document.querySelector('small.text-muted');
                    if (progressText && progressText.textContent.includes('Progresso de hoje')) {
                        progressText.textContent = `Progresso de hoje: ${Math.round(progressPercent)}%`;
                    }
                }
            }
        })
        .catch(error => console.error('Erro ao atualizar contadores do dashboard:', error));
    }
    
    // Event listeners para checkboxes de tarefas
    document.querySelectorAll('.task-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const taskId = this.dataset.id;
            const status = this.checked ? 'concluida' : 'pendente';
            
            fetch('atualizar_status_tarefa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: taskId, status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardCounters();
                }
            })
            .catch(error => console.error('Erro:', error));
        });
    });
    
    // Event listeners para botões de timer
    document.querySelectorAll('.btn-start-timer').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.id;
            
            if (pomodoroActive && currentTaskId === taskId) {
                // Pausar timer
                pomodoroActive = false;
                currentTaskId = null;
                this.innerHTML = '<i class="bi bi-play-fill"></i>';
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-light');
                showToast('Timer Pausado', 'Timer da tarefa foi pausado.');
            } else {
                // Iniciar timer
                pomodoroActive = true;
                currentTaskId = taskId;
                this.innerHTML = '<i class="bi bi-pause-fill"></i>';
                this.classList.remove('btn-outline-light');
                this.classList.add('btn-success');
                showToast('Timer Iniciado!', 'Timer Pomodoro de 25 minutos iniciado para esta tarefa.');
                
                // Simular timer (25 minutos = 1500000ms, mas vamos usar 5 segundos para demo)
                setTimeout(() => {
                    if (pomodoroActive && currentTaskId === taskId) {
                        pomodoroActive = false;
                        currentTaskId = null;
                        this.innerHTML = '<i class="bi bi-play-fill"></i>';
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-light');
                        showToast('Timer Concluído!', 'Sessão Pomodoro finalizada! Hora de uma pausa.');
                        
                        // Vibração se disponível
                        if (navigator.vibrate) {
                            navigator.vibrate([200, 100, 200]);
                        }
                    }
                }, 5000); // 5 segundos para demo (use 1500000 para 25 minutos reais)
            }
        });
    });
});
