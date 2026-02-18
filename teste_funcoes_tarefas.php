<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Funções - Tarefas</title>
    <style>
        body {
            background: #000;
            color: #fff;
            font-family: monospace;
            padding: 2rem;
        }
        .btn {
            background: #DC143C;
            color: white;
            border: none;
            padding: 1rem 2rem;
            margin: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #FF0000;
        }
        .result {
            background: #151515;
            border: 1px solid #333;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 8px;
        }
        .success { color: #10B981; }
        .error { color: #DC143C; }
    </style>
</head>
<body>
    <h1>🧪 Teste Direto das Funções</h1>
    
    <div class="result">
        <h3>Status dos Scripts:</h3>
        <div id="status"></div>
    </div>

    <div class="result">
        <h3>Testes de Funções:</h3>
        <button class="btn" onclick="testarFuncao('mostrarEstatisticas')">Testar mostrarEstatisticas()</button>
        <button class="btn" onclick="testarFuncao('adicionarRotinaFixa')">Testar adicionarRotinaFixa()</button>
        <button class="btn" onclick="testarFuncao('editarRotina')">Testar editarRotina()</button>
        <button class="btn" onclick="testarFuncao('excluirRotina')">Testar excluirRotina()</button>
        <button class="btn" onclick="testarFuncao('toggleRotina')">Testar toggleRotina()</button>
    </div>

    <div class="result">
        <h3>Resultados:</h3>
        <div id="resultados"></div>
    </div>

    <!-- Carregar tarefas.php inline para ter as funções -->
    <script>
        function testarFuncao(nomeFuncao) {
            const resultados = document.getElementById('resultados');
            const timestamp = new Date().toLocaleTimeString();
            
            if (typeof window[nomeFuncao] === 'function') {
                resultados.innerHTML += `<div class="success">[${timestamp}] ✅ ${nomeFuncao} está disponível e é uma função!</div>`;
                try {
                    // Não chamar a função, apenas verificar que existe
                    resultados.innerHTML += `<div class="success">[${timestamp}] ✅ Tipo: ${typeof window[nomeFuncao]}</div>`;
                } catch(e) {
                    resultados.innerHTML += `<div class="error">[${timestamp}] ❌ Erro ao acessar: ${e.message}</div>`;
                }
            } else {
                resultados.innerHTML += `<div class="error">[${timestamp}] ❌ ${nomeFuncao} NÃO está disponível! (tipo: ${typeof window[nomeFuncao]})</div>`;
            }
        }

        // Verificar status ao carregar
        window.addEventListener('DOMContentLoaded', function() {
            const status = document.getElementById('status');
            
            // Verificar se tarefas.php foi carregado
            fetch('tarefas.php')
                .then(response => response.text())
                .then(html => {
                    const hasWindowFunctions = html.includes('window.mostrarEstatisticas');
                    
                    if (hasWindowFunctions) {
                        status.innerHTML = '<div class="success">✅ tarefas.php contém as funções window.*</div>';
                    } else {
                        status.innerHTML = '<div class="error">❌ tarefas.php NÃO contém as funções window.*</div>';
                    }
                    
                    // Verificar funções no escopo global atual
                    const funcoes = ['mostrarEstatisticas', 'toggleRotina', 'adicionarRotinaFixa', 'editarRotina', 'excluirRotina'];
                    funcoes.forEach(func => {
                        const exists = typeof window[func] === 'function';
                        const icon = exists ? '✅' : '❌';
                        const className = exists ? 'success' : 'error';
                        status.innerHTML += `<div class="${className}">${icon} window.${func}: ${exists ? 'Disponível' : 'NÃO disponível'}</div>`;
                    });
                })
                .catch(error => {
                    status.innerHTML = '<div class="error">❌ Erro ao carregar tarefas.php: ' + error.message + '</div>';
                });
        });
    </script>
</body>
</html>

