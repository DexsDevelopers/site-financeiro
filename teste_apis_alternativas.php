<?php
// teste_apis_alternativas.php - Teste das APIs alternativas sem redirecionamento

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🧪 TESTE DAS APIs ALTERNATIVAS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para testar API alternativa
function testarAPIAlternativa($nome, $arquivo) {
    echo "<h3>🔍 Teste: $nome</h3>";
    echo "Arquivo: $arquivo<br>";
    
    if (!file_exists($arquivo)) {
        echo "❌ Arquivo não encontrado<br>";
        return false;
    }
    
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
                
                if (isset($data['success']) && $data['success'] === true) {
                    echo "✅ API funcionando corretamente<br>";
                    return true;
                } else {
                    echo "❌ API retornou erro: " . ($data['message'] ?? 'Erro desconhecido') . "<br>";
                }
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
    
    return false;
}

// Testar cada API alternativa
echo "<h2>📊 TESTE DAS APIs ALTERNATIVAS</h2>";

$apis = [
    'api_tarefas_hoje.php' => 'Tarefas de Hoje (Alternativa)',
    'api_distribuicao_prioridade.php' => 'Distribuição por Prioridade (Alternativa)',
    'api_produtividade_7_dias.php' => 'Produtividade 7 Dias (Alternativa)'
];

$resultados = [];

foreach ($apis as $arquivo => $nome) {
    $resultados[$arquivo] = testarAPIAlternativa($nome, $arquivo);
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

// Verificar se as tabelas existem
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if ($stmt->fetch()) {
        echo "✅ Tabela 'tarefas' existe<br>";
        
        // Verificar se há dados
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $total = $stmt->fetch()['total'];
        echo "📊 Total de tarefas: $total<br>";
        
        if ($total == 0) {
            echo "⚠️ Nenhuma tarefa encontrada. Isso pode causar problemas nas APIs.<br>";
        }
    } else {
        echo "❌ Tabela 'tarefas' não existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar banco: " . $e->getMessage() . "<br>";
}

// Verificar configuração PHP
echo "<h3>⚙️ CONFIGURAÇÃO PHP</h3>";
echo "Versão PHP: " . phpversion() . "<br>";
echo "JSON support: " . (function_exists('json_encode') ? 'Sim' : 'Não') . "<br>";
echo "PDO support: " . (class_exists('PDO') ? 'Sim' : 'Não') . "<br>";
echo "Session support: " . (function_exists('session_start') ? 'Sim' : 'Não') . "<br>";

if ($sucessos === $total) {
    echo "<br><h3>🎉 TODAS AS APIs ALTERNATIVAS ESTÃO FUNCIONANDO!</h3>";
    echo "<p>As APIs alternativas funcionam corretamente. Agora você pode:</p>";
    echo "<ul>";
    echo "<li>Usar as APIs alternativas no lugar das originais</li>";
    echo "<li>Atualizar o JavaScript para usar as novas URLs</li>";
    echo "<li>Testar o modal de estatísticas</li>";
    echo "</ul>";
    echo "<a href='tarefas.php' class='btn btn-success'>Voltar para Tarefas</a>";
} else {
    echo "<br><h3>⚠️ AINDA HÁ PROBLEMAS</h3>";
    echo "Algumas APIs alternativas ainda não estão funcionando. Verifique:<br>";
    echo "<ul>";
    echo "<li>Se há tarefas no banco de dados</li>";
    echo "<li>Se o usuário está logado corretamente</li>";
    echo "<li>Se há erros nos logs do servidor</li>";
    echo "<li>Se as permissões dos arquivos estão corretas</li>";
    echo "</ul>";
}
?>
