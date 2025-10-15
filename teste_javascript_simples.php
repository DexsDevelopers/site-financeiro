<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste JavaScript Simples</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Teste JavaScript Simples</h2>
        
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Teste de Botões</h5>
                <p class="card-text">Clique nos botões para testar:</p>
                
                <button class="btn btn-success btn-complete" data-id="123">
                    <i class="bi bi-check-circle"></i> Concluir Tarefa
                </button>
                
                <button class="btn btn-primary" onclick="testarFuncao()">
                    <i class="bi bi-play"></i> Testar Função
                </button>
            </div>
        </div>
        
        <div class="mt-4">
            <h4>Console de Debug:</h4>
            <div id="console" class="bg-dark text-light p-3" style="height: 200px; overflow-y: auto; font-family: monospace;"></div>
        </div>
    </div>

    <script>
        function log(message) {
            const console = document.getElementById('console');
            const time = new Date().toLocaleTimeString();
            console.innerHTML += `<div style="color: #00ff00">[${time}] ${message}</div>`;
            console.scrollTop = console.scrollHeight;
        }

        // Teste de função simples
        function testarFuncao() {
            log('✅ Função testarFuncao() executada com sucesso!');
        }

        // Teste de event delegation
        document.addEventListener('click', function(e) {
            log('Clique detectado em: ' + e.target.tagName + ' ' + e.target.className);
            
            if (e.target.closest('.btn-complete')) {
                const btn = e.target.closest('.btn-complete');
                const taskId = btn.dataset.id;
                log('✅ Botão de concluir clicado! Task ID: ' + taskId);
                
                // Simular requisição
                fetch('atualizar_status_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: taskId, status: 'concluida' })
                })
                .then(response => {
                    log('Response status: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    log('Response data: ' + JSON.stringify(data));
                    if (data.success) {
                        log('✅ Tarefa concluída com sucesso!');
                    } else {
                        log('❌ Erro: ' + data.message);
                    }
                })
                .catch(error => {
                    log('❌ Erro de rede: ' + error.message);
                });
            }
        });

        // Debug inicial
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 DOM carregado');
            log('🔍 Testando JavaScript...');
            
            // Verificar se as funções existem
            if (typeof testarFuncao === 'function') {
                log('✅ Função testarFuncao() definida');
            } else {
                log('❌ Função testarFuncao() não encontrada');
            }
        });
    </script>
</body>
</html>
