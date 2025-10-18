// ===== CONFIGURAÇÃO INICIAL =====
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar atalhos de teclado e animações
    initializeKeyboardShortcuts();
    initializeAnimations();
});

// ===== MODAIS =====
function abrirModalTarefa() {
    const modal = document.getElementById('modalTarefa');
    modal.classList.add('active');
    setTimeout(() => {
        document.querySelector('#formNovaTarefa input[name="descricao"]').focus();
    }, 100);
}

function fecharModalTarefa() {
    document.getElementById('modalTarefa').classList.remove('active');
    document.getElementById('formNovaTarefa').reset();
}

function abrirModalRotina() {
    document.getElementById('modalRotina').classList.add('active');
    document.querySelector('#formNovaRotina input[name="nome"]').focus();
}

function fecharModalRotina() {
    document.getElementById('modalRotina').classList.remove('active');
    document.getElementById('formNovaRotina').reset();
}

function abrirModalSubtarefa(tarefaId) {
    window.tarefaIdAtual = tarefaId;
    document.getElementById('modalSubtarefa').classList.add('active');
    document.querySelector('#formNovaSubtarefa input[name="descricao"]').focus();
}

function fecharModalSubtarefa() {
    document.getElementById('modalSubtarefa').classList.remove('active');
    document.getElementById('formNovaSubtarefa').reset();
    window.tarefaIdAtual = null;
}

function abrirModalEditarRotina(rotinaId) {
    fetch(`obter_rotina_fixa.php?id=${rotinaId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const rotina = data.rotina;
                document.getElementById('modalEditarRotina').classList.add('active');
                document.querySelector('#formEditarRotina input[name="nome"]').value = rotina.nome;
                const horario = rotina.horario_sugerido ? rotina.horario_sugerido.substring(0, 5) : '';
                document.querySelector('#formEditarRotina input[name="horario"]').value = horario;
                document.querySelector('#formEditarRotina textarea[name="descricao"]').value = rotina.descricao || '';
                document.getElementById('formEditarRotina').dataset.rotinaId = rotinaId;
            }
        })
        .catch(e => Toast.info('Erro ao carregar rotina'));
}

function fecharModalEditarRotina() {
    document.getElementById('modalEditarRotina').classList.remove('active');
    document.getElementById('formEditarRotina').reset();
}

// Fechar modal ao clicar no overlay
document.addEventListener('DOMContentLoaded', () => {
    ['modalTarefa', 'modalRotina', 'modalSubtarefa', 'modalEditarRotina'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        }
    });

    // Submissão de Nova Tarefa
    const formTarefa = document.getElementById('formNovaTarefa');
    if (formTarefa) {
        formTarefa.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(formTarefa);
            const descricao = formData.get('descricao')?.trim();
            
            if (!descricao) {
                Toast.warning('Descrição é obrigatória');
                return;
            }

            const btn = formTarefa.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"><span></span><span></span><span></span></span>';

            fetch('adicionar_tarefa.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.success(' Tarefa adicionada!');
                    location.reload();
                } else {
                    Toast.error(' ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Salvar';
                }
            })
            .catch(e => {
                Toast.error('Erro ao salvar. Tente novamente.');
                btn.disabled = false;
                btn.textContent = 'Salvar';
            });
        });
    }

    // Submissão de Nova Rotina
    const formRotina = document.getElementById('formNovaRotina');
    if (formRotina) {
        formRotina.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(formRotina);
            const nome = formData.get('nome')?.trim();
            
            if (!nome) {
                Toast.warning('Nome é obrigatório');
                return;
            }

            const btn = formRotina.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-save"></i> <span class="spinner"><span></span><span></span><span></span></span>';

            fetch('adicionar_rotina_fixa.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.success(' Rotina criada!');
                    location.reload();
                } else {
                    Toast.error(' ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save"></i> Criar';
                }
            })
            .catch(e => {
                Toast.error('Erro ao salvar. Tente novamente.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save"></i> Criar';
            });
        });
    }

    // Submissão de Nova Subtarefa
    const formSubtarefa = document.getElementById('formNovaSubtarefa');
    if (formSubtarefa) {
        formSubtarefa.addEventListener('submit', (e) => {
            e.preventDefault();
            const descricao = document.querySelector('#formNovaSubtarefa input[name="descricao"]').value?.trim();
            
            if (!descricao) {
                Toast.warning('Descrição é obrigatória');
                return;
            }

            const formData = new FormData();
            formData.append('id_tarefa_principal', window.tarefaIdAtual);
            formData.append('descricao', descricao);

            const btn = formSubtarefa.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-save"></i> <span class="spinner"><span></span><span></span><span></span></span>';

            fetch('adicionar_subtarefa.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.success(' Subtarefa adicionada!');
                    location.reload();
                } else {
                    Toast.error(' ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save"></i> Adicionar';
                }
            })
            .catch(e => {
                Toast.error('Erro ao salvar. Tente novamente.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save"></i> Adicionar';
            });
        });
    }

    // Submissão de Editar Rotina
    const formEditarRotina = document.getElementById('formEditarRotina');
    if (formEditarRotina) {
        formEditarRotina.addEventListener('submit', (e) => {
            e.preventDefault();
            const rotinaId = formEditarRotina.dataset.rotinaId;
            const formData = new FormData(formEditarRotina);
            const nome = formData.get('nome')?.trim();
            
            if (!nome) {
                Toast.warning('Nome é obrigatório');
                return;
            }

            const btn = formEditarRotina.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-save"></i> <span class="spinner"><span></span><span></span><span></span></span>';

            fetch('atualizar_rotina_fixa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: rotinaId,
                    nome: formData.get('nome'),
                    horario: formData.get('horario'),
                    descricao: formData.get('descricao')
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.success(' Rotina atualizada!');
                    location.reload();
                } else {
                    Toast.error(' ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save"></i> Salvar';
                }
            })
            .catch(e => {
                Toast.error('Erro ao salvar. Tente novamente.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save"></i> Salvar';
            });
        });
    }

    // Busca e Filtros
    const searchInput = document.getElementById('searchInput');
    const filterPriority = document.getElementById('filterPriority');
    
    if (searchInput && filterPriority) {
        const aplicarFiltros = () => {
            const termo = searchInput.value.toLowerCase();
            const prioridade = filterPriority.value;
            
            document.querySelectorAll('[data-task-id]').forEach(item => {
                const texto = item.textContent.toLowerCase();
                const prioridadeItem = item.querySelector('.badge')?.textContent.trim();
                
                const mostra = texto.includes(termo) && (!prioridade || prioridadeItem === prioridade);
                item.style.display = mostra ? 'flex' : 'none';
            });
        };

        searchInput.addEventListener('input', aplicarFiltros);
        filterPriority.addEventListener('change', aplicarFiltros);
    }
});

// ===== AÇÕES DE TAREFAS =====
function completarTarefa(id) {
    fetch('concluir_tarefa_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

function editarTarefa(id) {
    fetch(`obter_tarefa.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const tarefa = data.tarefa;
                const modal = document.createElement('div');
                modal.className = 'modal-overlay active';
                modal.id = 'modalEditarTarefa_' + id;
                modal.innerHTML = `
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2><i class="bi bi-pencil"></i> Editar Tarefa</h2>
                            <button class="modal-close" onclick="document.getElementById('modalEditarTarefa_${id}').remove()">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <form id="formEditarTarefa_${id}">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>Descrição</label>
                                    <input type="text" name="descricao" class="form-input" value="${htmlEscape(tarefa.descricao)}" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Prioridade</label>
                                        <select name="prioridade" class="form-input">
                                            <option value="Baixa" ${tarefa.prioridade === 'Baixa' ? 'selected' : ''}>Baixa</option>
                                            <option value="Média" ${tarefa.prioridade === 'Média' ? 'selected' : ''}>Média</option>
                                            <option value="Alta" ${tarefa.prioridade === 'Alta' ? 'selected' : ''}>Alta</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Data Limite</label>
                                        <input type="date" name="data_limite" class="form-input" value="${tarefa.data_limite || ''}">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-cancel" onclick="document.getElementById('modalEditarTarefa_${id}').remove()">Cancelar</button>
                                <button type="submit" class="btn-submit"><i class="bi bi-save"></i> Salvar</button>
                            </div>
                        </form>
                    </div>
                `;
                document.body.appendChild(modal);
                
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.remove();
                });

                const form = document.getElementById('formEditarTarefa_' + id);
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const btn = form.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    
                    fetch('atualizar_tarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: id,
                            descricao: form.querySelector('input[name="descricao"]').value,
                            prioridade: form.querySelector('select[name="prioridade"]').value,
                            data_limite: form.querySelector('input[name="data_limite"]').value
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Toast.success(' Tarefa atualizada!');
                            location.reload();
                        }
                    });
                });
            }
        });
}

function deletarTarefa(id) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.id = 'modalConfirmar_' + id;
    modal.innerHTML = `
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-exclamation-triangle"></i> Confirmar Exclusão</h2>
                <button class="modal-close" onclick="document.getElementById('modalConfirmar_${id}').remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja deletar esta tarefa?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalConfirmar_${id}').remove()">Cancelar</button>
                <button type="button" class="btn-submit" style="background: #dc3545;" onclick="confirmarDeletarTarefa(${id})">
                    <i class="bi bi-trash"></i> Deletar
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function confirmarDeletarTarefa(id) {
    fetch('excluir_tarefa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Toast.success(' Tarefa deletada!');
            location.reload();
        }
    });
}

// ===== AÇÕES DE ROTINAS =====
function completarRotina(controleId) {
    fetch('processar_rotina_diaria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `controle_id=${controleId}&status=concluido`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

function deletarRotina(id) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.id = 'modalConfirmarRotina_' + id;
    modal.innerHTML = `
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-exclamation-triangle"></i> Confirmar Exclusão</h2>
                <button class="modal-close" onclick="document.getElementById('modalConfirmarRotina_${id}').remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja deletar esta rotina?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalConfirmarRotina_${id}').remove()">Cancelar</button>
                <button type="button" class="btn-submit" style="background: #dc3545;" onclick="confirmarDeletarRotina(${id})">
                    <i class="bi bi-trash"></i> Deletar
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function confirmarDeletarRotina(id) {
    fetch('excluir_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Toast.success(' Rotina deletada!');
            location.reload();
        }
    });
}

// ===== SUBTAREFAS =====
function toggleSubtasks(header) {
    const subtasks = header.closest('.subtasks');
    const list = subtasks.querySelector('.subtasks-list');
    const icon = header.querySelector('i');
    
    if (list.style.display === 'none' || !list.style.display) {
        list.style.display = 'flex';
        icon.className = 'bi bi-chevron-down';
    } else {
        list.style.display = 'none';
        icon.className = 'bi bi-chevron-right';
    }
}

function toggleSubtarefa(id) {
    const checkbox = document.querySelector(`[data-id="${id}"]`);
    const status = checkbox.checked ? 'concluida' : 'pendente';
    const label = checkbox.closest('.subtask-item').querySelector('.subtask-label');

    fetch('atualizar_subtarefa_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (status === 'concluida') {
                label.classList.add('completed');
            } else {
                label.classList.remove('completed');
            }
        }
    });
}

function deletarSubtarefa(id) {
    if (confirm('Deletar esta subtarefa?')) {
        fetch('deletar_subtarefa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelector(`[data-id="${id}"]`).closest('.subtask-item').remove();
            }
        });
    }
}

// ===== ATALHOS DE TECLADO =====
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Não executar atalhos se estiver digitando em input/textarea
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        // Alt + N: Nova Tarefa
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            abrirModalTarefa();
        }
        
        // Alt + R: Nova Rotina
        if (e.altKey && e.key === 'r') {
            e.preventDefault();
            abrirModalRotina();
        }
        
        // ESC: Fechar modais
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
        
        // Ctrl + /: Buscar
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            document.getElementById('searchInput')?.focus();
        }
    });
}

// ===== ANIMAÇÕES E EFEITOS =====
function initializeAnimations() {
    // Adicionar efeito de conclusão aos checkboxes
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const item = this.closest('.item');
            if (this.checked) {
                item.classList.add('completing');
                setTimeout(() => {
                    item.classList.add('concluido');
                    item.classList.remove('completing');
                }, 500);
            } else {
                item.classList.remove('concluido');
            }
        });
    });
    
    // Animação de hover nos botões
    document.querySelectorAll('.btn, .btn-action').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Lazy loading para itens
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.item').forEach(item => {
        observer.observe(item);
    });
}

// ===== UTILIDADES =====
function htmlEscape(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
