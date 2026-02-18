<?php
session_start();
require_once 'includes/db_connect.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

// Buscar algumas tarefas para teste
$stmt = $pdo->prepare("SELECT id, titulo, status FROM tarefas WHERE id_usuario = ? AND status = 'pendente' LIMIT 3");
$stmt->execute([$userId]);
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Botões Tarefas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        #log {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Teste de Botões de Tarefas</h2>
        <p>Usuário ID: <?php echo $userId; ?></p>
        
        <div class="row">
            <?php foreach ($tarefas as $tarefa): ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($tarefa['titulo']); ?></h5>
                        <p class="card-text">Status: <?php echo $tarefa['status']; ?></p>
                        <button class="btn btn-success btn-complete" data-id="<?php echo $tarefa['id']; ?>">
                            <i class="bi bi-check-circle"></i> Concluir
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="mt-4">
            <h4>Log de Eventos:</h4>
            <div id="log" class="bg-light p-3" style="height: 200px; overflow-y: auto;"></div>
        </div>
    </div>

    <script>
        function log(message) {
            const logDiv = document.getElementById('log');
            const time = new Date().toLocaleTimeString();
            logDiv.innerHTML += `[${time}] ${message}<br>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        // Teste 1: Event listener direto
        document.addEventListener('DOMContentLoaded', function() {
            log('DOM carregado');
            
            // Teste com querySelectorAll
            const botoes = document.querySelectorAll('.btn-complete');
            log(`Encontrados ${botoes.length} botões com .btn-complete`);
            
            botoes.forEach((btn, index) => {
                log(`Botão ${index + 1}: data-id="${btn.dataset.id}"`);
            });
        });

        // Teste 2: Event delegation
        document.addEventListener('click', function(e) {
            log('Clique detectado em: ' + e.target.tagName + ' ' + e.target.className);
            
            if (e.target.closest('.btn-complete')) {
                const btn = e.target.closest('.btn-complete');
                const taskId = btn.dataset.id;
                log(`Botão de concluir clicado! Task ID: ${taskId}`);
                
                // Simular requisição
                fetch('atualizar_status_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: taskId, status: 'concluida' })
                })
                .then(response => {
                    log(`Response status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    log(`Response data: ${JSON.stringify(data)}`);
                    if (data.success) {
                        log('✅ Tarefa concluída com sucesso!');
                        btn.textContent = 'Concluída!';
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-secondary');
                        btn.disabled = true;
                    } else {
                        log('❌ Erro: ' + data.message);
                    }
                })
                .catch(error => {
                    log('❌ Erro de rede: ' + error.message);
                });
            }
        });

        // Teste 3: Verificar se os elementos existem
        setTimeout(() => {
            const botoes = document.querySelectorAll('.btn-complete');
            log(`Após 1 segundo: ${botoes.length} botões encontrados`);
            
            botoes.forEach((btn, index) => {
                log(`Botão ${index + 1}: ${btn.outerHTML.substring(0, 100)}...`);
            });
        }, 1000);
    </script>
</body>
</html>
