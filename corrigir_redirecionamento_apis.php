<?php
// corrigir_redirecionamento_apis.php - Corrigir problemas de redirecionamento das APIs

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔧 CORREÇÃO DE REDIRECIONAMENTO DAS APIs</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para testar API com diferentes métodos
function testarAPICorrigida($nome, $arquivo) {
    echo "<h3>🔍 Teste: $nome</h3>";
    echo "Arquivo: $arquivo<br>";
    
    // Método 1: Teste direto do arquivo
    echo "<h4>Método 1: Teste Direto</h4>";
    
    if (file_exists($arquivo)) {
        echo "✅ Arquivo existe<br>";
        
        // Capturar output
        ob_start();
        
        try {
            // Simular execução direta
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'];
            $_SERVER['REQUEST_URI'] = '/' . $arquivo;
            
            include $arquivo;
            $output = ob_get_clean();
            
            if (!empty($output)) {
                echo "✅ Output capturado (tamanho: " . strlen($output) . " bytes)<br>";
                
                // Verificar se é JSON válido
                $data = json_decode($output, true);
                if ($data !== null) {
                    echo "✅ JSON válido<br>";
                    echo "Dados: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "<br>";
                    return true;
                } else {
                    echo "❌ JSON inválido: " . json_last_error_msg() . "<br>";
                    echo "Output: " . htmlspecialchars($output) . "<br>";
                }
            } else {
                echo "❌ Nenhum output capturado<br>";
            }
        } catch (Exception $e) {
            ob_end_clean();
            echo "❌ Erro: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Arquivo não encontrado<br>";
    }
    
    // Método 2: Teste via cURL
    echo "<h4>Método 2: Teste via cURL</h4>";
    
    if (function_exists('curl_init')) {
        $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/" . $arquivo;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ Erro cURL: $error<br>";
        } else {
            echo "✅ Resposta cURL (HTTP $httpCode)<br>";
            echo "Tamanho: " . strlen($response) . " bytes<br>";
            
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if ($data !== null) {
                    echo "✅ JSON válido via cURL<br>";
                    return true;
                } else {
                    echo "❌ JSON inválido via cURL: " . json_last_error_msg() . "<br>";
                }
            } else {
                echo "❌ HTTP $httpCode<br>";
            }
        }
    } else {
        echo "❌ cURL não disponível<br>";
    }
    
    // Método 3: Teste via file_get_contents com contexto
    echo "<h4>Método 3: Teste via file_get_contents</h4>";
    
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/" . $arquivo;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 (compatible; API-Test)'
            ],
            'timeout' => 30,
            'follow_location' => true,
            'max_redirects' => 5
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ Erro ao acessar via file_get_contents<br>";
    } else {
        echo "✅ Resposta recebida (tamanho: " . strlen($response) . " bytes)<br>";
        
        $data = json_decode($response, true);
        if ($data !== null) {
            echo "✅ JSON válido via file_get_contents<br>";
            return true;
        } else {
            echo "❌ JSON inválido via file_get_contents: " . json_last_error_msg() . "<br>";
            echo "Resposta: " . htmlspecialchars(substr($response, 0, 200)) . "...<br>";
        }
    }
    
    return false;
}

// Testar cada API
echo "<h2>📊 TESTE DAS APIs COM DIFERENTES MÉTODOS</h2>";

$apis = [
    'buscar_tarefas_hoje.php' => 'Tarefas de Hoje',
    'buscar_distribuicao_prioridade.php' => 'Distribuição por Prioridade',
    'buscar_produtividade_7_dias.php' => 'Produtividade 7 Dias'
];

$resultados = [];

foreach ($apis as $arquivo => $nome) {
    $resultados[$arquivo] = testarAPICorrigida($nome, $arquivo);
    echo "<hr>";
}

// Resumo
echo "<h2>📋 RESUMO DOS RESULTADOS</h2>";
$sucessos = 0;
$total = count($resultados);

foreach ($resultados as $arquivo => $sucesso) {
    if ($sucesso) {
        echo "✅ $arquivo: FUNCIONANDO<br>";
        $sucessos++;
    } else {
        echo "❌ $arquivo: COM PROBLEMAS<br>";
    }
}

echo "<br><strong>Total: $sucessos/$total APIs funcionando</strong><br>";

// Verificações adicionais
echo "<h3>🔍 VERIFICAÇÕES ADICIONAIS</h3>";

// Verificar configuração do servidor
echo "<h4>Configuração do Servidor:</h4>";
echo "Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Host: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// Verificar se há .htaccess
if (file_exists('.htaccess')) {
    echo "⚠️ Arquivo .htaccess encontrado - pode estar causando redirecionamentos<br>";
    echo "Conteúdo do .htaccess:<br>";
    echo "<pre>" . htmlspecialchars(file_get_contents('.htaccess')) . "</pre>";
} else {
    echo "✅ Nenhum arquivo .htaccess encontrado<br>";
}

// Verificar permissões dos arquivos
echo "<h4>Permissões dos Arquivos:</h4>";
foreach ($apis as $arquivo => $nome) {
    if (file_exists($arquivo)) {
        $perms = fileperms($arquivo);
        echo "✅ $arquivo: " . substr(sprintf('%o', $perms), -4) . "<br>";
    } else {
        echo "❌ $arquivo: Arquivo não encontrado<br>";
    }
}

if ($sucessos === $total) {
    echo "<br><h3>🎉 TODAS AS APIs ESTÃO FUNCIONANDO!</h3>";
    echo "<a href='tarefas.php' class='btn btn-success'>Voltar para Tarefas</a>";
} else {
    echo "<br><h3>⚠️ AINDA HÁ PROBLEMAS</h3>";
    echo "Execute as correções necessárias e teste novamente.<br>";
    echo "<br><h4>Possíveis soluções:</h4>";
    echo "<ul>";
    echo "<li>Verifique se há regras de redirecionamento no .htaccess</li>";
    echo "<li>Verifique se o servidor está configurado corretamente</li>";
    echo "<li>Teste as APIs diretamente no navegador</li>";
    echo "<li>Verifique os logs de erro do servidor</li>";
    echo "</ul>";
}
?>
