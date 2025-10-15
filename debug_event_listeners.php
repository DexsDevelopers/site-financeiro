<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

// Buscar uma tarefa para teste
$stmt = $pdo->prepare("SELECT id, titulo FROM tarefas WHERE id_usuario = ? AND status = 'pendente' LIMIT 1");
$stmt->execute([$userId]);
$tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tarefa) {
    die('Nenhuma tarefa pendente encontrada');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Event Listeners</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Debug Event Listeners</h2>
        
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($tarefa['titulo']); ?></h5>
                <p class="card-text">ID: <?php echo $tarefa['id']; ?></p>
                
                <!-- Teste 1: Botão com classe btn-complete -->
                <button class="btn btn-success btn-complete" data-id="<?php echo $tarefa['id']; ?>">
                    <i class="bi bi-check-circle"></i> Concluir (Método 1)
                </button>
                
                <!-- Teste 2: Botão com onclick -->
                <button class="btn btn-primary" onclick="concluirTarefa(<?php echo $tarefa['id']; ?>)">
                    <i class="bi bi-check-circle"></i> Concluir (Método 2)
                </button>
                
                <!-- Teste 3: Botão com ID específico -->
                <button class="btn btn-warning" id="btn-concluir-<?php echo $tarefa['id']; ?>" data-task-id="<?php echo $tarefa['id']; ?>">
                    <i class="bi bi-check-circle"></i> Concluir (Método 3)
                </button>
            </div>
        </div>
        
        <div class="mt-4">
            <h4>Console de Debug:</h4>
            <div id="console" class="bg-dark text-light p-3" style="height: 300px; overflow-y: auto; font-family: monospace;"></div>
        </div>
    </div>

    <script>
        function log(message, type = 'info') {
            const console = document.getElementById('console');
            const time = new Date().toLocaleTimeString();
            const colors = {
                'info': '#00ff00',
                'error': '#ff0000',
                'warning': '#ffff00',
                'success': '#00ffff'
            };
            console.innerHTML += `<div style="color: ${colors[type]}">[${time}] ${message}</div>`;
            console.scrollTop = console.scrollHeight;
        }

        // Método 1: Event delegation para .btn-complete
        document.addEventListener('click', function(e) {
            log('Clique detectado em: ' + e.target.tagName + ' ' + e.target.className, 'info');
            
            if (e.target.closest('.btn-complete')) {
                const btn = e.target.closest('.btn-complete');
                const taskId = btn.dataset.id;
                log(`✅ Event delegation funcionando! Task ID: ${taskId}`, 'success');
                
                // Testar requisição
                testarRequisicao(taskId);
            }
        });

        // Método 2: Função onclick direta
        function concluirTarefa(taskId) {
            log(`✅ Função onclick funcionando! Task ID: ${taskId}`, 'success');
            testarRequisicao(taskId);
        }

        // Método 3: Event listener específico por ID
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('btn-concluir-<?php echo $tarefa['id']; ?>');
            if (btn) {
                btn.addEventListener('click', function() {
                    const taskId = this.dataset.taskId;
                    log(`✅ Event listener específico funcionando! Task ID: ${taskId}`, 'success');
                    testarRequisicao(taskId);
                });
            } else {
                log('❌ Botão não encontrado para event listener específico', 'error');
            }
        });

        function testarRequisicao(taskId) {
            log(`🔄 Testando requisição para task ID: ${taskId}`, 'info');
            
            fetch('atualizar_status_tarefa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: taskId, status: 'concluida' })
            })
            .then(response => {
                log(`📡 Response status: ${response.status}`, 'info');
                return response.json();
            })
            .then(data => {
                log(`📦 Response data: ${JSON.stringify(data)}`, 'info');
                if (data.success) {
                    log('✅ Tarefa concluída com sucesso!', 'success');
                } else {
                    log(`❌ Erro: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                log(`❌ Erro de rede: ${error.message}`, 'error');
            });
        }

        // Debug inicial
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 DOM carregado', 'info');
            
            // Verificar se os elementos existem
            const botoes = document.querySelectorAll('.btn-complete');
            log(`🔍 Encontrados ${botoes.length} botões com classe .btn-complete`, 'info');
            
            botoes.forEach((btn, index) => {
                log(`🔘 Botão ${index + 1}: data-id="${btn.dataset.id}"`, 'info');
            });
            
            // Verificar se o botão específico existe
            const btnEspecifico = document.getElementById('btn-concluir-<?php echo $tarefa['id']; ?>');
            if (btnEspecifico) {
                log('✅ Botão específico encontrado', 'success');
            } else {
                log('❌ Botão específico não encontrado', 'error');
            }
        });
    </script>
</body>
</html>
