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
    
    // Criar modal dinâmico
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.id = 'modalSubtarefaRapida';
    modal.innerHTML = `
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-plus-circle"></i> Adicionar Subtarefa</h2>
                <button class="modal-close" onclick="document.getElementById('modalSubtarefaRapida').remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Descrição da Subtarefa</label>
                    <input type="text" id="descricaoSubtarefa" class="form-input" placeholder="O que é preciso fazer?" autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalSubtarefaRapida').remove()">
                    Cancelar
                </button>
                <button type="button" class="btn-submit" onclick="salvarSubtarefaRapido()">
                    <i class="bi bi-save"></i> Adicionar
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Fechar ao clicar no overlay
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
    
    // Enviar com Enter
    document.getElementById('descricaoSubtarefa').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') salvarSubtarefaRapido();
    });
}

function salvarSubtarefaRapido() {
    const descricao = document.getElementById('descricaoSubtarefa').value?.trim();
    
    if (!descricao) {
        alert('⚠️ Descrição é obrigatória');
        return;
    }
    
    if (!window.tarefaIdAtual) {
        alert('❌ Erro: tarefa não identificada');
        return;
    }
    
    fetch('adicionar_subtarefa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_tarefa_principal=${window.tarefaIdAtual}&descricao=${encodeURIComponent(descricao)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Subtarefa adicionada!');
            location.reload();
        } else {
            alert('❌ Erro: ' + data.message);
        }
    })
    .catch(e => alert('❌ Erro ao salvar: ' + e.message));
}

function toggleSubtarefasVisibilidade(element) {
    const container = element.closest('.subtasks-container');
    const content = container.querySelector('.subtasks-content');
    const icon = element.querySelector('i');
    const toolbar = element.closest('.subtasks-toolbar');
    
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
    const label = checkbox.closest('.subtask-item').querySelector('.subtask-label');
    const status = checkbox.checked ? 'concluida' : 'pendente';
    
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

function deletarSubtarefaRapido(id) {
    if (!confirm('Deletar esta subtarefa?')) return;
    
    fetch('deletar_subtarefa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const item = document.querySelector(`[data-sub-id="${id}"]`).closest('.subtask-item');
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 300);
        }
    });
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initDragAndDrop();
    initCategorias();
});
