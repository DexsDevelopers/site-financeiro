/**
 * MELHORIAS V2 - Sistema de Tarefas
 * 
 * Implementação de:
 * 1. Dark/Light Toggle (Melhoria #9)
 * 2. Drag & Drop para Ordenar (Melhoria #5)
 * 3. Suporte a Categorias/Tags (Melhoria #6)
 * 4. Subtarefas Melhoradas (Nova)
 */

// ===== 1. DARK/LIGHT MODE TOGGLE =====
function initThemeToggle() {
    const htmlRoot = document.getElementById('htmlRoot');
    const savedTheme = localStorage.getItem('tarefas-theme') || 'dark';
    
    // Aplicar tema salvo
    htmlRoot.setAttribute('data-theme', savedTheme);
    updateThemeButton(savedTheme);
}

function toggleTheme() {
    const htmlRoot = document.getElementById('htmlRoot');
    const currentTheme = htmlRoot.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    htmlRoot.setAttribute('data-theme', newTheme);
    localStorage.setItem('tarefas-theme', newTheme);
    updateThemeButton(newTheme);
}

function updateThemeButton(theme) {
    const btn = document.querySelector('.btn-theme');
    if (btn) {
        if (theme === 'light') {
            btn.innerHTML = '<i class="bi bi-sun-fill"></i>';
            btn.title = 'Modo Escuro';
        } else {
            btn.innerHTML = '<i class="bi bi-moon-fill"></i>';
            btn.title = 'Modo Claro';
        }
    }
}

// ===== 2. DRAG & DROP PARA REORDENAR TAREFAS =====
let draggedItem = null;

function initDragAndDrop() {
    document.addEventListener('dragstart', (e) => {
        if (e.target.classList.contains('item') && !e.target.classList.contains('rotina')) {
            draggedItem = e.target;
            e.target.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    document.addEventListener('dragend', (e) => {
        if (draggedItem) {
            draggedItem.style.opacity = '1';
        }
    });

    document.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        const item = e.target.closest('.item');
        if (item && item !== draggedItem && !item.classList.contains('rotina')) {
            item.style.borderTop = '3px solid #dc3545';
        }
    });

    document.addEventListener('dragleave', (e) => {
        const item = e.target.closest('.item');
        if (item) {
            item.style.borderTop = '';
        }
    });

    document.addEventListener('drop', (e) => {
        e.preventDefault();
        
        const dropTarget = e.target.closest('.item');
        if (dropTarget && draggedItem && dropTarget !== draggedItem && !dropTarget.classList.contains('rotina')) {
            dropTarget.style.borderTop = '';
            
            // Reordenar visualmente
            const container = dropTarget.parentNode;
            const draggedIndex = Array.from(container.children).indexOf(draggedItem);
            const targetIndex = Array.from(container.children).indexOf(dropTarget);
            
            if (draggedIndex < targetIndex) {
                dropTarget.insertAdjacentElement('afterend', draggedItem);
            } else {
                dropTarget.insertAdjacentElement('beforebegin', draggedItem);
            }
            
            // Salvar nova ordem no backend
            salvarOrdenTarefas();
        }
    });
}

function salvarOrdenTarefas() {
    const tarefas = Array.from(document.querySelectorAll('[data-task-id]'))
        .filter(item => !item.classList.contains('rotina'))
        .map((item, index) => ({
            id: item.dataset.taskId,
            ordem: index + 1
        }));

    fetch('salvar_ordem_tarefas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tarefas })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Ordem de tarefas salva');
        }
    })
    .catch(e => console.error('Erro ao salvar ordem:', e));
}

// ===== 3. SUPORTE A CATEGORIAS/TAGS =====
function initCategorias() {
    const categoriaButton = document.querySelector('.btn-categoria');
    if (categoriaButton) {
        categoriaButton.addEventListener('click', abrirModalCategorias);
    }
}

function abrirModalCategorias() {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.id = 'modalCategorias';
    modal.innerHTML = `
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-tags"></i> Gerenciar Categorias</h2>
                <button class="modal-close" onclick="document.getElementById('modalCategorias').remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nova Categoria</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="categoriaNome" class="form-input" placeholder="Nome da categoria" style="flex: 1;">
                        <input type="color" id="categoriaCor" class="form-input" value="#6bcf7f" style="width: 60px; cursor: pointer;">
                        <button class="btn-submit" onclick="criarCategoria()" style="padding: 8px 16px;">
                            <i class="bi bi-plus"></i> Criar
                        </button>
                    </div>
                </div>
                <div id="listaCategorias" style="margin-top: 20px; max-height: 300px; overflow-y: auto;">
                    <!-- Categorias serão carregadas aqui -->
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    carregarCategorias();
}

function carregarCategorias() {
    fetch('obter_categorias.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const lista = document.getElementById('listaCategorias');
                lista.innerHTML = data.categorias.map(cat => `
                    <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 8px;">
                        <div style="width: 20px; height: 20px; background: ${cat.cor}; border-radius: 4px;"></div>
                        <span style="flex: 1;">${cat.nome}</span>
                        <button class="btn-icon" onclick="deletarCategoria(${cat.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `).join('');
            }
        });
}

function criarCategoria() {
    const nome = document.getElementById('categoriaNome').value?.trim();
    const cor = document.getElementById('categoriaCor').value;

    if (!nome) {
        alert('⚠️ Nome da categoria é obrigatório');
        return;
    }

    fetch('criar_categoria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nome, cor })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('categoriaNome').value = '';
            carregarCategorias();
            alert('✅ Categoria criada!');
        }
    });
}

function deletarCategoria(id) {
    if (!confirm('Deletar esta categoria?')) return;

    fetch('deletar_categoria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            carregarCategorias();
        }
    });
}

// ===== 4. SUBTAREFAS MELHORADAS =====
function abrirAdicionarSubtarefa(tarefaId) {
    window.tarefaIdAtual = tarefaId;
    
    // Criar modal simples e direto
    const descricao = prompt('🆕 Descrição da Subtarefa:');
    
    if (!descricao || !descricao.trim()) {
        return; // Cancelou ou vazio
    }
    
    // Enviar para servidor
    const formData = new FormData();
    formData.append('id_tarefa_principal', tarefaId);
    formData.append('descricao', descricao.trim());
    
    fetch('adicionar_subtarefa.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Subtarefa adicionada!');
            location.reload();
        } else {
            alert('❌ Erro: ' + (data.message || 'Erro ao adicionar'));
        }
    })
    .catch(e => {
        alert('❌ Erro ao salvar: ' + e.message);
    });
}

function salvarSubtarefaRapido() {
    const descricao = document.getElementById('descricaoSubtarefa')?.value?.trim();
    
    if (!descricao) {
        alert('⚠️ Descrição é obrigatória');
        return;
    }
    
    if (!window.tarefaIdAtual) {
        alert('❌ Erro: tarefa não identificada');
        return;
    }
    
    const formData = new FormData();
    formData.append('id_tarefa_principal', window.tarefaIdAtual);
    formData.append('descricao', descricao);
    
    fetch('adicionar_subtarefa.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Subtarefa adicionada!');
            location.reload();
        } else {
            alert('❌ Erro: ' + (data.message || 'Erro ao adicionar'));
        }
    })
    .catch(e => alert('❌ Erro ao salvar: ' + e.message));
}

function toggleSubtarefasVisibilidade(element) {
    const container = element.closest('.subtasks-container');
    const content = container?.querySelector('.subtasks-content');
    const icon = element.querySelector('i');
    
    if (!content) return;
    
    // Toggle display
    if (content.style.display === 'none') {
        content.style.display = 'flex';
        icon.className = 'bi bi-chevron-down';
    } else {
        content.style.display = 'none';
        icon.className = 'bi bi-chevron-right';
    }
}

function marcarSubtarefaConcluida(id) {
    const checkbox = document.querySelector(`[data-sub-id="${id}"]`);
    if (!checkbox) return;
    
    const row = checkbox.closest('.subtask-row');
    const label = row?.querySelector('.subtask-text');
    const status = checkbox.checked ? 'concluida' : 'pendente';
    
    // Feedback visual imediato
    if (row) {
        row.style.transition = 'all 0.3s ease';
        row.style.opacity = '0.5';
    }
    
    fetch('atualizar_subtarefa_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (row) {
                row.style.opacity = '1';
                if (status === 'concluida') {
                    row.classList.add('completed');
                    Toast.success('Subtarefa concluída!');
                } else {
                    row.classList.remove('completed');
                    Toast.info('Subtarefa reaberta');
                }
            }
            // Atualizar contador
            updateSubtaskCounter(row?.closest('.subtasks-container'));
        } else {
            if (row) row.style.opacity = '1';
            checkbox.checked = !checkbox.checked;
            Toast.error('Erro ao atualizar subtarefa');
        }
    })
    .catch(err => {
        if (row) row.style.opacity = '1';
        checkbox.checked = !checkbox.checked;
        Toast.error('Erro de conexão');
        console.error(err);
    });
}

function deletarSubtarefaRapido(id) {
    console.log('🗑️ Iniciando exclusão da subtarefa ID:', id);
    
    // Criar modal de confirmação customizado e moderno
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.style.zIndex = '1055'; // Compatível com Bootstrap
    modal.innerHTML = `
        <div class="modal-box modal-confirm" style="max-width: 420px; animation: bounceIn 0.3s ease;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <h2 style="display: flex; align-items: center; gap: 10px; margin: 0; color: white;">
                    <i class="bi bi-trash3-fill" style="font-size: 22px;"></i>
                    <span>Excluir Subtarefa</span>
                </h2>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()" style="color: white; opacity: 0.9;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 60px; height: 60px; margin: 0 auto 15px; background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(255,107,107,0.3);">
                        <i class="bi bi-exclamation-triangle" style="font-size: 28px; color: white;"></i>
                    </div>
                    <p style="font-size: 16px; font-weight: 600; margin-bottom: 8px; color: var(--text);">Tem certeza?</p>
                    <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5;">Esta ação não pode ser desfeita. A subtarefa será removida permanentemente.</p>
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px 25px; gap: 12px;">
                <button class="btn-cancel" onclick="this.closest('.modal-overlay').remove()" style="flex: 1; padding: 10px;">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
                <button class="btn-submit btn-danger" id="confirmDeleteSub_${id}" style="flex: 1; padding: 10px; background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%); border: none; box-shadow: 0 4px 12px rgba(255,107,107,0.3);">
                    <i class="bi bi-trash3"></i> Sim, Deletar
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Adicionar evento ao botão de confirmação com ID único
    const confirmBtn = document.getElementById(`confirmDeleteSub_${id}`);
    if (!confirmBtn) {
        console.error('❌ Botão de confirmação não encontrado');
        return;
    }
    
    confirmBtn.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('✅ Confirmação clicada para ID:', id);
        
        const btn = this;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<span class="loading"><span></span><span></span><span></span></span>';
        btn.disabled = true;
        btn.style.opacity = '0.7';
        
        console.log('📡 Enviando requisição DELETE para:', 'deletar_subtarefa.php');
        
        fetch('deletar_subtarefa.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: parseInt(id) })
        })
        .then(response => {
            console.log('📥 Resposta recebida, status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('📦 Dados recebidos:', data);
            
            if (data.success) {
                // Encontrar e remover a subtarefa com animação
                const checkbox = document.querySelector(`[data-sub-id="${id}"]`);
                console.log('🔍 Checkbox encontrado:', checkbox);
                
                const item = checkbox?.closest('.subtask-row');
                console.log('🔍 Item encontrado:', item);
                
                if (item) {
                    item.style.animation = 'slideOutRight 0.3s ease forwards';
                    setTimeout(() => {
                        const container = item.closest('.subtasks-container');
                        item.remove();
                        console.log('✅ Subtarefa removida do DOM');
                        
                        // Verificar se ainda há subtarefas
                        if (container) {
                            const remaining = container.querySelectorAll('.subtask-row').length;
                            console.log('📊 Subtarefas restantes:', remaining);
                            
                            if (remaining === 0) {
                                const taskId = container.closest('[data-task-id]')?.dataset.taskId;
                                container.innerHTML = `
                                    <button class="btn-add-subtask-empty" onclick="abrirModalSubtarefa(${taskId})" title="Adicionar Subtarefa">
                                        <i class="bi bi-plus-circle"></i>
                                        <span>Adicionar Subtarefa</span>
                                    </button>
                                `;
                                console.log('🔄 Container atualizado para estado vazio');
                            } else {
                                updateSubtaskCounter(container);
                                console.log('🔄 Contador atualizado');
                            }
                        }
                    }, 300);
                } else {
                    console.warn('⚠️ Elemento da subtarefa não encontrado no DOM');
                }
                
                modal.remove();
                Toast.success('Subtarefa deletada com sucesso!');
                console.log('✅ Exclusão concluída com sucesso');
            } else {
                console.error('❌ Erro do servidor:', data.message);
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.style.opacity = '1';
                Toast.error(data.message || 'Erro ao deletar subtarefa');
            }
        })
        .catch(err => {
            console.error('❌ Erro na requisição:', err);
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            btn.style.opacity = '1';
            Toast.error('Erro de conexão. Verifique sua internet.');
        });
    };
    
    // Fechar modal ao clicar fora
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Função auxiliar para atualizar contador de subtarefas
function updateSubtaskCounter(container) {
    if (!container) return;
    
    const counter = container.querySelector('.subtasks-count');
    if (!counter) return;
    
    const total = container.querySelectorAll('.subtask-row').length;
    const completed = container.querySelectorAll('.subtask-row.completed').length;
    counter.textContent = `(${completed}/${total})`;
    
    // Atualizar progress bar se existir
    const progressBar = container.querySelector('.subtask-progress');
    if (progressBar) {
        const percentage = total > 0 ? (completed / total * 100) : 0;
        progressBar.style.width = `${percentage}%`;
    }
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initDragAndDrop();
    initCategorias();
});
