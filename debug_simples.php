<?php
// Debug simples sem necessidade de login
echo "✅ Página carregando!<br>";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Teste de banco de dados
try {
    require_once 'includes/db_connect.php';
    echo "✅ Conexão com banco: OK<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    echo "Total de usuários: " . $result['total'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erro de banco: " . $e->getMessage() . "<br>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Simples</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        #console {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            background-color: #000;
            color: #00ff00;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>🔍 Debug Simples - Teste de Event Listeners</h2>
        
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Teste de Botões</h5>
                <p class="card-text">Clique nos botões abaixo para testar os event listeners:</p>
                
                <!-- Teste 1: Botão com classe btn-complete -->
                <button class="btn btn-success btn-complete" data-id="123">
                    <i class="bi bi-check-circle"></i> Concluir (Event Delegation)
                </button>
                
                <!-- Teste 2: Botão com onclick -->
                <button class="btn btn-primary" onclick="testarOnclick()">
                    <i class="bi bi-check-circle"></i> Concluir (Onclick)
                </button>
                
                <!-- Teste 3: Botão com ID específico -->
                <button class="btn btn-warning" id="btn-teste" data-task-id="456">
                    <i class="bi bi-check-circle"></i> Concluir (ID Específico)
                </button>
            </div>
        </div>
        
        <div class="mt-4">
            <h4>Console de Debug:</h4>
            <div id="console" style="height: 300px; overflow-y: auto;"></div>
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
            }
        });

        // Método 2: Função onclick direta
        function testarOnclick() {
            log('✅ Função onclick funcionando!', 'success');
        }

        // Método 3: Event listener específico por ID
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 DOM carregado', 'info');
            
            const btn = document.getElementById('btn-teste');
            if (btn) {
                btn.addEventListener('click', function() {
                    const taskId = this.dataset.taskId;
                    log(`✅ Event listener específico funcionando! Task ID: ${taskId}`, 'success');
                });
                log('✅ Event listener específico configurado', 'success');
            } else {
                log('❌ Botão não encontrado para event listener específico', 'error');
            }
            
            // Verificar se os elementos existem
            const botoes = document.querySelectorAll('.btn-complete');
            log(`🔍 Encontrados ${botoes.length} botões com classe .btn-complete`, 'info');
            
            botoes.forEach((btn, index) => {
                log(`🔘 Botão ${index + 1}: data-id="${btn.dataset.id}"`, 'info');
            });
        });
    </script>
</body>
</html>
