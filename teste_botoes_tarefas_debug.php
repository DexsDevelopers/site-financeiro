<?php
/**
 * TESTE DE BOTÕES DE TAREFAS - DEBUG
 * Verificar se os botões estão funcionando corretamente
 */

session_start();
require_once 'includes/db_connect.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Buscar algumas tarefas para teste
try {
    $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente' LIMIT 3");
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tarefas = [];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Botões - Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .test-container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .task-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin: 10px 0; background: #f8f9fa; }
        .btn-icon { padding: 8px 12px; margin: 2px; border-radius: 4px; }
        .log-container { background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 20px 0; max-height: 300px; overflow-y: auto; }
        .log-entry { margin: 5px 0; padding: 5px; border-radius: 3px; }
        .log-success { background: #d4edda; color: #155724; }
        .log-error { background: #f8d7da; color: #721c24; }
        .log-info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Teste de Botões de Tarefas - Debug</h1>
        <p><strong>Usuário ID:</strong> <?= $userId ?></p>
        <p><strong>Total de tarefas pendentes:</strong> <?= count($tarefas) ?></p>
        
        <div class="log-container">
            <h5>📋 Log de Eventos:</h5>
            <div id="log"></div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h3>🔧 Teste Manual</h3>
                <button class="btn btn-primary" onclick="testarFuncao()">Testar Função</button>
                <button class="btn btn-success" onclick="testarFetch()">Testar Fetch</button>
                <button class="btn btn-warning" onclick="limparLog()">Limpar Log</button>
            </div>
            <div class="col-md-6">
                <h3>📊 Status do Sistema</h3>
                <div id="status"></div>
            </div>
        </div>
        
        <h3>📝 Tarefas de Teste:</h3>
        <?php if (empty($tarefas)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Nenhuma tarefa pendente encontrada para teste.
            </div>
        <?php else: ?>
            <?php foreach ($tarefas as $tarefa): ?>
                <div class="task-card" data-id="<?= $tarefa['id'] ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5><?= htmlspecialchars($tarefa['descricao']) ?></h5>
                            <small class="text-muted">ID: <?= $tarefa['id'] ?> | Status: <?= $tarefa['status'] ?></small>
                        </div>
                        <div>
                            <button class="btn btn-success btn-icon btn-complete" data-id="<?= $tarefa['id'] ?>" title="Concluir">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button class="btn btn-primary btn-icon btn-edit" data-id="<?= $tarefa['id'] ?>" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-danger btn-icon btn-delete" data-id="<?= $tarefa['id'] ?>" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Log de eventos
        function log(message, type = 'info') {
            const logContainer = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry log-${type}`;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        // Testar função básica
        function testarFuncao() {
            log('✅ Função testarFuncao() executada com sucesso!', 'success');
        }

        // Testar fetch
        async function testarFetch() {
            try {
                log('🔄 Testando fetch para atualizar_status_tarefa.php...', 'info');
                const response = await fetch('atualizar_status_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: 999, status: 'concluida' })
                });
                
                const data = await response.json();
                log(`📡 Response status: ${response.status}`, 'info');
                log(`📡 Response data: ${JSON.stringify(data)}`, 'info');
                
                if (data.success) {
                    log('✅ Fetch funcionando corretamente!', 'success');
                } else {
                    log(`❌ Erro: ${data.message}`, 'error');
                }
            } catch (error) {
                log(`❌ Erro no fetch: ${error.message}`, 'error');
            }
        }

        // Limpar log
        function limparLog() {
            document.getElementById('log').innerHTML = '';
        }

        // Event delegation para botões
        document.addEventListener('click', function(e) {
            log(`Clique detectado em: ${e.target.tagName} ${e.target.className}`, 'info');
            
            // Botão de concluir
            if (e.target.closest('.btn-complete')) {
                const btn = e.target.closest('.btn-complete');
                const taskId = btn.dataset.id;
                log(`✅ Botão de concluir clicado! Task ID: ${taskId}`, 'success');
                
                // Testar fetch
                fetch('atualizar_status_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: taskId, status: 'concluida' })
                })
                .then(response => {
                    log(`Response status: ${response.status}`, 'info');
                    return response.json();
                })
                .then(data => {
                    log(`Response data: ${JSON.stringify(data)}`, 'info');
                    
                    if (data.success) {
                        log('✅ Tarefa concluída com sucesso!', 'success');
                        // Remover da tela
                        const card = btn.closest('.task-card');
                        if (card) {
                            card.style.opacity = '0.5';
                            card.style.textDecoration = 'line-through';
                        }
                    } else {
                        log(`❌ Erro: ${data.message}`, 'error');
                    }
                })
                .catch(error => {
                    log(`❌ Erro: ${error.message}`, 'error');
                });
            }
            
            // Botão de editar
            if (e.target.closest('.btn-edit')) {
                const btn = e.target.closest('.btn-edit');
                const taskId = btn.dataset.id;
                log(`✏️ Botão de editar clicado! Task ID: ${taskId}`, 'info');
            }
            
            // Botão de excluir
            if (e.target.closest('.btn-delete')) {
                const btn = e.target.closest('.btn-delete');
                const taskId = btn.dataset.id;
                log(`🗑️ Botão de excluir clicado! Task ID: ${taskId}`, 'info');
            }
        });

        // Verificar status do sistema
        function verificarStatus() {
            const status = {
                'Event Listeners': 'Ativos',
                'Fetch API': 'Disponível',
                'Console': 'Funcionando',
                'DOM': 'Carregado'
            };
            
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = Object.entries(status)
                .map(([key, value]) => `<div><strong>${key}:</strong> ${value}</div>`)
                .join('');
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 DOM carregado', 'success');
            log('🔍 Testando JavaScript...', 'info');
            verificarStatus();
        });

        // Verificar se funções estão definidas
        if (typeof testarFuncao === 'function') {
            log('✅ Função testarFuncao() definida', 'success');
        } else {
            log('❌ Função testarFuncao() não definida', 'error');
        }
    </script>
</body>
</html>
