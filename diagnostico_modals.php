<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Modals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #0d1117;
            color: #f0f6fc;
            padding: 2rem;
        }
        .test-section {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .result {
            padding: 0.5rem;
            margin: 0.5rem 0;
            border-radius: 4px;
        }
        .success { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .error { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .info { background: rgba(13, 202, 240, 0.2); color: #0dcaf0; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">🔍 Diagnóstico de Modals - Tarefas.php</h1>
        
        <div class="test-section">
            <h3>1. Verificação do Bootstrap</h3>
            <div id="bootstrap-check"></div>
        </div>
        
        <div class="test-section">
            <h3>2. Modais Existentes na Página</h3>
            <div id="modals-check"></div>
        </div>
        
        <div class="test-section">
            <h3>3. Teste de Abertura de Modal</h3>
            <button class="btn btn-primary" onclick="testarModal()">🧪 Testar Modal de Teste</button>
            <div id="modal-test-result" class="mt-3"></div>
        </div>
        
        <div class="test-section">
            <h3>4. Console de Erros</h3>
            <div id="console-errors"></div>
        </div>
    </div>
    
    <!-- Modal de Teste -->
    <div class="modal fade" id="modalTeste" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #161b22; border: 1px solid #30363d;">
                <div class="modal-header" style="border-bottom: 1px solid #30363d;">
                    <h5 class="modal-title" style="color: #f0f6fc;">Modal de Teste</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p style="color: #8b949e;">Se você está vendo isso, o modal está funcionando! ✅</p>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #30363d;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Capturar erros do console
        const consoleErrors = [];
        const originalError = console.error;
        console.error = function(...args) {
            consoleErrors.push(args.join(' '));
            originalError.apply(console, args);
        };
        
        window.addEventListener('DOMContentLoaded', function() {
            // 1. Verificar Bootstrap
            const bootstrapCheck = document.getElementById('bootstrap-check');
            if (typeof bootstrap !== 'undefined') {
                bootstrapCheck.innerHTML = '<div class="result success">✅ Bootstrap carregado: v' + (bootstrap.Modal ? '5.x' : 'versão desconhecida') + '</div>';
                
                if (typeof bootstrap.Modal !== 'undefined') {
                    bootstrapCheck.innerHTML += '<div class="result success">✅ bootstrap.Modal disponível</div>';
                } else {
                    bootstrapCheck.innerHTML += '<div class="result error">❌ bootstrap.Modal NÃO disponível</div>';
                }
            } else {
                bootstrapCheck.innerHTML = '<div class="result error">❌ Bootstrap NÃO carregado</div>';
            }
            
            // 2. Verificar modais na página
            const modalsCheck = document.getElementById('modals-check');
            const modals = document.querySelectorAll('.modal');
            
            if (modals.length > 0) {
                modalsCheck.innerHTML = `<div class="result success">✅ ${modals.length} modal(is) encontrado(s)</div>`;
                modals.forEach((modal, index) => {
                    const id = modal.id || 'sem-id';
                    const hasBackdrop = modal.querySelector('.modal-dialog') !== null;
                    const hasContent = modal.querySelector('.modal-content') !== null;
                    
                    modalsCheck.innerHTML += `
                        <div class="result info">
                            📋 Modal ${index + 1}: <strong>${id}</strong><br>
                            - Dialog: ${hasBackdrop ? '✅' : '❌'}<br>
                            - Content: ${hasContent ? '✅' : '❌'}
                        </div>
                    `;
                });
            } else {
                modalsCheck.innerHTML = '<div class="result error">❌ Nenhum modal encontrado</div>';
            }
            
            // 4. Mostrar erros do console
            setTimeout(() => {
                const consoleErrorsDiv = document.getElementById('console-errors');
                if (consoleErrors.length > 0) {
                    consoleErrorsDiv.innerHTML = '<div class="result error">❌ Erros encontrados:</div>';
                    consoleErrors.forEach(err => {
                        consoleErrorsDiv.innerHTML += `<div class="result error">${err}</div>`;
                    });
                } else {
                    consoleErrorsDiv.innerHTML = '<div class="result success">✅ Nenhum erro no console</div>';
                }
            }, 1000);
        });
        
        // Função de teste
        function testarModal() {
            const resultDiv = document.getElementById('modal-test-result');
            
            try {
                const modalElement = document.getElementById('modalTeste');
                
                if (!modalElement) {
                    resultDiv.innerHTML = '<div class="result error">❌ Elemento do modal não encontrado</div>';
                    return;
                }
                
                if (typeof bootstrap === 'undefined') {
                    resultDiv.innerHTML = '<div class="result error">❌ Bootstrap não está carregado</div>';
                    return;
                }
                
                if (typeof bootstrap.Modal === 'undefined') {
                    resultDiv.innerHTML = '<div class="result error">❌ bootstrap.Modal não está disponível</div>';
                    return;
                }
                
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                
                resultDiv.innerHTML = '<div class="result success">✅ Modal aberto com sucesso!</div>';
            } catch (error) {
                resultDiv.innerHTML = `<div class="result error">❌ Erro ao abrir modal: ${error.message}</div>`;
                console.error('Erro completo:', error);
            }
        }
        
        // Tornar função global
        window.testarModal = testarModal;
    </script>
</body>
</html>

