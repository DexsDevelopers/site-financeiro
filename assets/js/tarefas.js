/**
 * Arquivo: assets/js/tarefas.js
 * Descrição: Lógica JavaScript para o sistema de tarefas
 * Modular com namespacing para evitar conflitos
 */

const TarefasApp = {
    // ========== MODAIS DE TAREFAS ==========
    modal: {
        abrirTarefa() {
            document.getElementById('modalTarefa')?.classList.add('active');
        },
        fecharTarefa() {
            const modal = document.getElementById('modalTarefa');
            modal?.classList.remove('active');
            document.getElementById('formNovaTarefa')?.reset();
        },
        abrirSubtarefa(tarefaId) {
            window.tarefaIdAtual = tarefaId;
            document.getElementById('modalSubtarefa')?.classList.add('active');
        },
        fecharSubtarefa() {
            const modal = document.getElementById('modalSubtarefa');
            modal?.classList.remove('active');
            document.getElementById('formNovaSubtarefa')?.reset();
            window.tarefaIdAtual = null;
        },
        abrirRotina() {
            document.getElementById('modalRotina')?.classList.add('active');
        },
        fecharRotina() {
            const modal = document.getElementById('modalRotina');
            modal?.classList.remove('active');
            document.getElementById('formNovaRotina')?.reset();
        }
    },

    // ========== EVENTOS GLOBAIS ==========
    inicializarEventos() {
        // Modal Tarefa
        const modalTarefa = document.getElementById('modalTarefa');
        modalTarefa?.addEventListener('click', (e) => {
            if (e.target === modalTarefa) this.modal.fecharTarefa();
        });

        // Modal Subtarefa
        const modalSubtarefa = document.getElementById('modalSubtarefa');
        modalSubtarefa?.addEventListener('click', (e) => {
            if (e.target === modalSubtarefa) this.modal.fecharSubtarefa();
        });

        // Modal Rotina
        const modalRotina = document.getElementById('modalRotina');
        modalRotina?.addEventListener('click', (e) => {
            if (e.target === modalRotina) this.modal.fecharRotina();
        });

        // Form Nova Tarefa
        document.getElementById('formNovaTarefa')?.addEventListener('submit', (e) => {
            this.tarefa.adicionarNova(e);
        });

        // Form Nova Subtarefa
        document.getElementById('formNovaSubtarefa')?.addEventListener('submit', (e) => {
            this.subtarefa.adicionarNova(e);
        });

        // Form Nova Rotina
        document.getElementById('formNovaRotina')?.addEventListener('submit', (e) => {
            this.rotina.adicionarNova(e);
        });
    },

    // ========== TAREFAS ==========
    tarefa: {
        async adicionarNova(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            const btnOriginal = btn.textContent;
            btn.textContent = 'Salvando...';

            try {
                const response = await fetch('adicionar_tarefa.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    alert('Tarefa adicionada!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = btnOriginal;
                }
            } catch (error) {
                alert('Erro ao salvar');
                btn.disabled = false;
                btn.textContent = btnOriginal;
            }
        },

        async completar(id) {
            try {
                const response = await fetch('concluir_tarefa_ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });

                const data = await response.json();
                if (data.success) {
                    const item = document.querySelector(`[data-task-id="${id}"]`);
                    item.style.opacity = '0.6';
                    setTimeout(() => item.remove(), 300);
                }
            } catch (error) {
                console.error('Erro ao completar tarefa:', error);
            }
        },

        async editar(id) {
            try {
                const response = await fetch(`obter_tarefa.php?id=${id}`);
                const data = await response.json();

                if (data.success) {
                    const tarefa = data.tarefa;
                    const modalEdit = document.createElement('div');
                    modalEdit.id = 'modalEdit_' + id;
                    modalEdit.className = 'modal-overlay';
                    modalEdit.classList.add('active');
                    modalEdit.innerHTML = `
                        <div class="modal-box">
                            <div class="modal-header">
                                <h2><i class="bi bi-pencil"></i> Editar Tarefa</h2>
                                <button class="modal-close" onclick="document.getElementById('modalEdit_${id}').remove()">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <form id="formEditarTarefa_${id}">
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label>Descrição</label>
                                        <input type="text" class="form-input" name="descricao" value="${TarefasApp.utils.htmlEscape(tarefa.descricao)}" required>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Prioridade</label>
                                            <select class="form-input" name="prioridade">
                                                <option value="Baixa" ${tarefa.prioridade === 'Baixa' ? 'selected' : ''}>Baixa</option>
                                                <option value="Média" ${tarefa.prioridade === 'Média' ? 'selected' : ''}>Média</option>
                                                <option value="Alta" ${tarefa.prioridade === 'Alta' ? 'selected' : ''}>Alta</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Data Limite</label>
                                            <input type="date" class="form-input" name="data_limite" value="${tarefa.data_limite || ''}">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn-cancel" onclick="document.getElementById('modalEdit_${id}').remove()">Cancelar</button>
                                    <button type="submit" class="btn-submit">
                                        <i class="bi bi-save"></i> Salvar Alterações
                                    </button>
                                </div>
                            </form>
                        </div>
                    `;
                    document.body.appendChild(modalEdit);

                    document.getElementById('formEditarTarefa_' + id).addEventListener('submit', (e) => {
                        this.salvarEdicao(e, id);
                    });
                }
            } catch (error) {
                console.error('Erro ao editar tarefa:', error);
            }
        },

        async salvarEdicao(event, id) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            const btnOriginal = btn.textContent;
            btn.textContent = 'Salvando...';

            try {
                const response = await fetch('atualizar_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: id,
                        descricao: formData.get('descricao'),
                        prioridade: formData.get('prioridade'),
                        data_limite: formData.get('data_limite')
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('Tarefa atualizada!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = btnOriginal;
                }
            } catch (error) {
                alert('Erro ao salvar');
                btn.disabled = false;
                btn.textContent = btnOriginal;
            }
        },

        deletar(id) {
            const modalConfirm = document.createElement('div');
            modalConfirm.id = 'modalConfirm_' + id;
            modalConfirm.className = 'modal-overlay active';
            modalConfirm.innerHTML = `
                <div class="modal-box">
                    <div class="modal-header">
                        <h2><i class="bi bi-exclamation-triangle"></i> Confirmar Exclusão</h2>
                        <button class="modal-close" onclick="document.getElementById('modalConfirm_${id}').remove()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p style="color: var(--text); font-size: 14px;">Tem certeza que deseja excluir esta tarefa? Esta ação não pode ser desfeita.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="document.getElementById('modalConfirm_${id}').remove()">Cancelar</button>
                        <button type="button" class="btn-submit" onclick="TarefasApp.tarefa.confirmarDeletacao(${id})">
                            <i class="bi bi-trash"></i> Deletar
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modalConfirm);
        },

        async confirmarDeletacao(id) {
            try {
                const response = await fetch('excluir_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });

                const data = await response.json();
                if (data.success) {
                    const item = document.querySelector(`[data-task-id="${id}"]`);
                    item.style.opacity = '0.6';
                    setTimeout(() => item.remove(), 300);
                    alert('Tarefa excluída com sucesso!');
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                console.error('Erro ao deletar:', error);
            } finally {
                const modal = document.getElementById('modalConfirm_' + id);
                if (modal) modal.remove();
            }
        }
    },

    // ========== ROTINAS ==========
    rotina: {
        async completar(controleId) {
            try {
                const response = await fetch('processar_rotina_diaria.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `controle_id=${controleId}&status=concluido`
                });

                const data = await response.json();
                if (data.success) {
                    // Encontrar o item de rotina e atualizar visualmente
                    const rotinaItem = document.querySelector(`[data-rotina-id="${controleId}"]`);
                    if (rotinaItem) {
                        const checkbox = rotinaItem.querySelector('.item-checkbox');
                        const statusSpan = rotinaItem.querySelector('.item-status');
                        
                        // Alternar status
                        if (data.status_novo === 'concluido') {
                            rotinaItem.classList.add('concluido');
                            checkbox.checked = true;
                            if (statusSpan) statusSpan.textContent = '✓ Concluído';
                        } else {
                            rotinaItem.classList.remove('concluido');
                            checkbox.checked = false;
                            if (statusSpan) statusSpan.textContent = '⟲ Pendente';
                        }
                    }
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                console.error('Erro ao completar rotina:', error);
                alert('Erro ao atualizar rotina');
            }
        },

        editar(id) {
            window.location.href = `editar_rotina_fixa.php?id=${id}`;
        },

        deletar(id) {
            if (confirm('Deletar esta rotina?')) {
                window.location.href = `excluir_rotina_fixa.php?id=${id}`;
            }
        },

        async adicionarNova(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            const btnOriginal = btn.textContent;
            btn.textContent = 'Salvando...';

            try {
                const response = await fetch('adicionar_rotina_fixa.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    alert('Rotina criada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = btnOriginal;
                }
            } catch (error) {
                alert('Erro ao salvar');
                btn.disabled = false;
                btn.textContent = btnOriginal;
            }
        }
    },

    // ========== SUBTAREFAS ==========
    subtarefa: {
        alternarVisibilidade(header) {
            const list = header.closest('.subtasks').querySelector('.subtasks-list');
            const icon = header.querySelector('i');
            if (list.style.display === 'none') {
                list.style.display = 'flex';
                icon.className = 'bi bi-chevron-down';
            } else {
                list.style.display = 'none';
                icon.className = 'bi bi-chevron-right';
            }
        },

        async marcarConcluida(id) {
            const checkbox = document.querySelector(`[data-id="${id}"]`);
            const status = checkbox.checked ? 'concluida' : 'pendente';
            const label = checkbox.closest('.subtask-item').querySelector('.subtask-label');

            try {
                const response = await fetch('atualizar_subtarefa_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, status })
                });

                const data = await response.json();
                if (data.success) {
                    if (status === 'concluida') {
                        label.classList.add('completed');
                    } else {
                        label.classList.remove('completed');
                    }
                } else {
                    checkbox.checked = !checkbox.checked;
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                console.error('Erro ao marcar subtarefa:', error);
                checkbox.checked = !checkbox.checked;
            }
        },

        async deletar(subId, taskId) {
            if (confirm('Deletar esta subtarefa?')) {
                try {
                    const response = await fetch('deletar_subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: subId })
                    });

                    const data = await response.json();
                    if (data.success) {
                        const item = document.querySelector(`[data-id="${subId}"]`).closest('.subtask-item');
                        item.style.opacity = '0.5';
                        setTimeout(() => item.remove(), 200);
                    } else {
                        alert('Erro: ' + data.message);
                    }
                } catch (error) {
                    console.error('Erro ao deletar subtarefa:', error);
                }
            }
        },

        async adicionarNova(event) {
            event.preventDefault();
            if (!window.tarefaIdAtual) {
                alert('Erro: ID da tarefa não identificado');
                return;
            }

            const form = event.target;
            const descricao = form.querySelector('input[name="descricao"]').value;
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            const btnOriginal = btn.textContent;
            btn.textContent = 'Salvando...';

            const formData = new FormData();
            formData.append('id_tarefa_principal', window.tarefaIdAtual);
            formData.append('descricao', descricao);

            try {
                const response = await fetch('adicionar_subtarefa.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    alert('Subtarefa adicionada!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = btnOriginal;
                }
            } catch (error) {
                alert('Erro ao salvar');
                btn.disabled = false;
                btn.textContent = btnOriginal;
            }
        }
    },

    // ========== UTILITÁRIOS ==========
    utils: {
        htmlEscape(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    },

    // ========== INICIALIZAÇÃO ==========
    init() {
        this.inicializarEventos();
    }
};

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    TarefasApp.init();
});
