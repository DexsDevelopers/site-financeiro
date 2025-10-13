<?php
// teste_correcao_apis.php - Teste das APIs corrigidas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🧪 TESTE DAS APIs CORRIGIDAS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para testar API
function testarAPI($nome, $arquivo) {
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
        include $arquivo;
        $output = ob_get_clean();
        
        if (!empty($output)) {
            echo "✅ Output capturado (tamanho: " . strlen($output) . " bytes)<br>";
            
            // Verificar se é JSON válido
            $data = json_decode($output, true);
            if ($data !== null) {
                echo "✅ JSON válido<br>";
                
                if (isset($data['success']) && $data['success'] === true) {
                    echo "✅ API funcionando corretamente<br>";
                    
                    // Mostrar dados específicos
                    if (isset($data['tarefas'])) {
                        echo "📊 Tarefas encontradas: " . count($data['tarefas']) . "<br>";
                    }
                    if (isset($data['total'])) {
                        echo "📊 Total: " . $data['total'] . "<br>";
                    }
                    if (isset($data['labels']) && isset($data['tarefas'])) {
                        echo "📊 Gráfico - Labels: " . implode(', ', $data['labels']) . "<br>";
                        echo "📊 Gráfico - Dados: " . implode(', ', $data['tarefas']) . "<br>";
                    }
                    if (isset($data['totalGeral'])) {
                        echo "📊 Total Geral: " . $data['totalGeral'] . "<br>";
                    }
                    if (isset($data['media_diaria'])) {
                        echo "📊 Média Diária: " . $data['media_diaria'] . "<br>";
                    }
                    
                    return true;
                } else {
                    echo "❌ API retornou erro: " . ($data['message'] ?? 'Erro desconhecido') . "<br>";
                }
            } else {
                echo "❌ JSON inválido: " . json_last_error_msg() . "<br>";
                echo "Output: " . htmlspecialchars(substr($output, 0, 500)) . "<br>";
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

// Testar APIs
$apis_teste = [
    'api_tarefas_hoje.php' => 'Tarefas de Hoje',
    'api_distribuicao_prioridade.php' => 'Distribuição por Prioridade',
    'api_produtividade_7_dias.php' => 'Produtividade 7 Dias'
];

$resultados = [];

foreach ($apis_teste as $arquivo => $nome) {
    $resultados[$arquivo] = testarAPI($nome, $arquivo);
    echo "<hr>";
}

// Resumo dos resultados
echo "<h2>📋 RESUMO DOS RESULTADOS</h2>";

$sucessos = 0;
foreach ($resultados as $arquivo => $sucesso) {
    if ($sucesso) {
        echo "✅ $arquivo: FUNCIONANDO<br>";
        $sucessos++;
    } else {
        echo "❌ $arquivo: COM PROBLEMAS<br>";
    }
}

echo "<br><strong>APIs funcionando: $sucessos/" . count($resultados) . "</strong><br>";

// Recomendações
echo "<h3>💡 RECOMENDAÇÕES</h3>";

if ($sucessos == count($resultados)) {
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Todas as APIs estão funcionando!</h4>";
    echo "<p>As estatísticas devem estar funcionando corretamente agora.</p>";
    echo "<ol>";
    echo "<li><a href='tarefas.php'>Testar o modal de estatísticas</a></li>";
    echo "<li>Verificar se os gráficos estão sendo exibidos</li>";
    echo "<li>Testar todas as funcionalidades do modal</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>❌ Algumas APIs ainda têm problemas</h4>";
    echo "<p>Execute <a href='verificar_estrutura_tabela.php'>verificar_estrutura_tabela.php</a> para ver a estrutura da tabela.</p>";
    echo "<ol>";
    echo "<li>Verifique quais APIs específicas estão com problemas</li>";
    echo "<li>Execute este script novamente</li>";
    echo "<li>Teste no modal de estatísticas</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>✅ Teste concluído!</strong> Use as recomendações acima para resolver os problemas.</p>";
?>
