<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Completo - Tarefas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #0A0A0A;
            color: #fff;
            padding: 2rem;
        }
        .test-section {
            background: #151515;
            border: 1px solid #2A2A2A;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .status-ok { color: #10B981; }
        .status-error { color: #DC143C; }
        .console-output {
            background: #000;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
        .log-item {
            padding: 4px 0;
            border-bottom: 1px solid #222;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><i class="bi bi-bug"></i> Diagnóstico Completo - Botões e Modais</h1>

        <!-- Teste 1: Bootstrap JS -->
        <div class="test-section">
            <h3>1️⃣ Bootstrap JS</h3>
            <p id="bootstrap-status">Verificando...</p>
        </div>

        <!-- Teste 2: Modais -->
        <div class="test-section">
            <h3>2️⃣ Teste de Modal</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTeste">
                <i class="bi bi-window"></i> Abrir Modal de Teste
            </button>
            <p id="modal-status" class="mt-2">Status: Aguardando teste...</p>
        </div>

        <!-- Teste 3: Event Listeners -->
        <div class="test-section">
            <h3>3️⃣ Event Listeners</h3>
            <button class="btn btn-success" id="btnTesteClick">
                <i class="bi bi-hand-index"></i> Testar Click Simples
            </button>
            <button class="btn btn-warning" onclick="testarOnclick()">
                <i class="bi bi-hand-index"></i> Testar Onclick
            </button>
            <p id="listener-status" class="mt-2">Aguardando cliques...</p>
        </div>

        <!-- Teste 4: Fetch API -->
        <div class="test-section">
            <h3>4️⃣ Fetch API</h3>
            <button class="btn btn-info" id="btnTesteFetch">
                <i class="bi bi-cloud-arrow-down"></i> Testar Fetch
            </button>
            <p id="fetch-status" class="mt-2">Aguardando teste...</p>
        </div>

        <!-- Teste 5: Console de Erros -->
        <div class="test-section">
            <h3>5️⃣ Console JavaScript</h3>
            <div id="console-output" class="console-output">
                <div class="log-item">Console iniciado...</div>
            </div>
        </div>

        <!-- Teste 6: Funções Globais -->
        <div class="test-section">
            <h3>6️⃣ Funções Globais Disponíveis</h3>
            <div id="global-functions"></div>
        </div>
    </div>

    <!-- Modal de Teste -->
    <div class="modal fade" id="modalTeste" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">✅ Modal Funcionando!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Se você está vendo isso, o Bootstrap está funcionando corretamente!</p>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Função para adicionar log no console visual
        function addLog(message, type = 'info') {
            const consoleOutput = document.getElementById('console-output');
            const logItem = document.createElement('div');
            logItem.className = 'log-item';
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#DC143C' : type === 'success' ? '#10B981' : '#3B82F6';
            logItem.innerHTML = `<span style="color: ${color};">[${timestamp}] ${message}</span>`;
            consoleOutput.appendChild(logItem);
            consoleOutput.scrollTop = consoleOutput.scrollHeight;
        }

        // Capturar erros JavaScript
        window.addEventListener('error', function(e) {
            addLog(`❌ ERRO: ${e.message} em ${e.filename}:${e.lineno}`, 'error');
        });

        // Capturar erros de console
        const originalConsoleError = console.error;
        console.error = function(...args) {
            addLog(`❌ Console Error: ${args.join(' ')}`, 'error');
            originalConsoleError.apply(console, args);
        };

        const originalConsoleLog = console.log;
        console.log = function(...args) {
            addLog(`ℹ️ Console Log: ${args.join(' ')}`, 'info');
            originalConsoleLog.apply(console, args);
        };

        // Teste 1: Bootstrap JS
        document.addEventListener('DOMContentLoaded', function() {
            addLog('✅ DOMContentLoaded disparado', 'success');
            
            const bootstrapStatus = document.getElementById('bootstrap-status');
            if (typeof bootstrap !== 'undefined') {
                bootstrapStatus.innerHTML = '<span class="status-ok">✅ Bootstrap JS carregado com sucesso!</span>';
                addLog('✅ Bootstrap disponível globalmente', 'success');
            } else {
                bootstrapStatus.innerHTML = '<span class="status-error">❌ Bootstrap JS NÃO carregado!</span>';
                addLog('❌ Bootstrap NÃO disponível', 'error');
            }

            // Teste 2: Modal Events
            const modalTeste = document.getElementById('modalTeste');
            if (modalTeste) {
                modalTeste.addEventListener('shown.bs.modal', function() {
                    document.getElementById('modal-status').innerHTML = '<span class="status-ok">✅ Modal aberto com sucesso!</span>';
                    addLog('✅ Modal aberto (evento shown.bs.modal)', 'success');
                });
            }

            // Teste 3: Event Listener
            const btnTesteClick = document.getElementById('btnTesteClick');
            if (btnTesteClick) {
                btnTesteClick.addEventListener('click', function() {
                    document.getElementById('listener-status').innerHTML = '<span class="status-ok">✅ Event Listener funcionando!</span>';
                    addLog('✅ Click detectado via addEventListener', 'success');
                });
            }

            // Teste 4: Fetch
            const btnTesteFetch = document.getElementById('btnTesteFetch');
            if (btnTesteFetch) {
                btnTesteFetch.addEventListener('click', function() {
                    addLog('🔄 Iniciando teste de Fetch...', 'info');
                    fetch('diagnostico_completo_tarefas.php')
                        .then(response => {
                            addLog(`✅ Fetch OK: Status ${response.status}`, 'success');
                            document.getElementById('fetch-status').innerHTML = '<span class="status-ok">✅ Fetch funcionando!</span>';
                        })
                        .catch(error => {
                            addLog(`❌ Fetch Error: ${error.message}`, 'error');
                            document.getElementById('fetch-status').innerHTML = '<span class="status-error">❌ Fetch falhou!</span>';
                        });
                });
            }

            // Teste 6: Funções Globais
            const globalFunctionsDiv = document.getElementById('global-functions');
            const functionsToCheck = ['mostrarEstatisticas', 'toggleRotina', 'adicionarRotinaFixa', 'editarRotina', 'excluirRotina'];
            let functionsHTML = '<ul>';
            functionsToCheck.forEach(funcName => {
                const exists = typeof window[funcName] === 'function';
                const status = exists ? '<span class="status-ok">✅</span>' : '<span class="status-error">❌</span>';
                functionsHTML += `<li>${status} <code>${funcName}</code></li>`;
                addLog(`${exists ? '✅' : '❌'} Função ${funcName}: ${exists ? 'Disponível' : 'NÃO encontrada'}`, exists ? 'success' : 'error');
            });
            functionsHTML += '</ul>';
            globalFunctionsDiv.innerHTML = functionsHTML;
        });

        // Função onclick para teste
        function testarOnclick() {
            document.getElementById('listener-status').innerHTML = '<span class="status-ok">✅ Onclick funcionando!</span>';
            addLog('✅ Click detectado via onclick', 'success');
        }

        addLog('📋 Diagnóstico iniciado...', 'info');
    </script>
</body>
</html>

