<?php
// diagnostico_estatisticas_completo.php - Diagnóstico completo das estatísticas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔍 DIAGNÓSTICO COMPLETO DAS ESTATÍSTICAS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para testar API específica
function testarAPICompleta($nome, $arquivo) {
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

// Testar APIs originais
echo "<h2>📊 TESTE DAS APIs ORIGINAIS</h2>";

$apis_originais = [
    'buscar_tarefas_hoje.php' => 'Tarefas de Hoje (Original)',
    'buscar_distribuicao_prioridade.php' => 'Distribuição por Prioridade (Original)',
    'buscar_produtividade_7_dias.php' => 'Produtividade 7 Dias (Original)'
];

$resultados_originais = [];

foreach ($apis_originais as $arquivo => $nome) {
    $resultados_originais[$arquivo] = testarAPICompleta($nome, $arquivo);
    echo "<hr>";
}

// Testar APIs alternativas
echo "<h2>📊 TESTE DAS APIs ALTERNATIVAS</h2>";

$apis_alternativas = [
    'api_tarefas_hoje.php' => 'Tarefas de Hoje (Alternativa)',
    'api_distribuicao_prioridade.php' => 'Distribuição por Prioridade (Alternativa)',
    'api_produtividade_7_dias.php' => 'Produtividade 7 Dias (Alternativa)'
];

$resultados_alternativas = [];

foreach ($apis_alternativas as $arquivo => $nome) {
    $resultados_alternativas[$arquivo] = testarAPICompleta($nome, $arquivo);
    echo "<hr>";
}

// Resumo dos resultados
echo "<h2>📋 RESUMO DOS RESULTADOS</h2>";

echo "<h3>APIs Originais:</h3>";
$sucessos_originais = 0;
foreach ($resultados_originais as $arquivo => $sucesso) {
    if ($sucesso) {
        echo "✅ $arquivo: FUNCIONANDO<br>";
        $sucessos_originais++;
    } else {
        echo "❌ $arquivo: COM PROBLEMAS<br>";
    }
}

echo "<h3>APIs Alternativas:</h3>";
$sucessos_alternativas = 0;
foreach ($resultados_alternativas as $arquivo => $sucesso) {
    if ($sucesso) {
        echo "✅ $arquivo: FUNCIONANDO<br>";
        $sucessos_alternativas++;
    } else {
        echo "❌ $arquivo: COM PROBLEMAS<br>";
    }
}

echo "<br><strong>APIs Originais: $sucessos_originais/" . count($resultados_originais) . " funcionando</strong><br>";
echo "<strong>APIs Alternativas: $sucessos_alternativas/" . count($resultados_alternativas) . " funcionando</strong><br>";

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
        
        // Verificar tarefas de hoje
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            AND (
                DATE(data_limite) = CURDATE() 
                OR DATE(data_criacao) = CURDATE()
            )
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $hoje = $stmt->fetch()['total'];
        echo "📅 Tarefas de hoje: $hoje<br>";
        
        // Verificar tarefas concluídas dos últimos 7 dias
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            AND status = 'concluida'
            AND data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $concluidas_7_dias = $stmt->fetch()['total'];
        echo "📊 Tarefas concluídas (7 dias): $concluidas_7_dias<br>";
        
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

// Recomendações
echo "<h3>💡 RECOMENDAÇÕES</h3>";

if ($sucessos_alternativas > $sucessos_originais) {
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Use as APIs Alternativas</h4>";
    echo "<p>As APIs alternativas estão funcionando melhor que as originais. Execute:</p>";
    echo "<ol>";
    echo "<li><a href='atualizar_javascript_estatisticas.php'>Atualizar JavaScript</a></li>";
    echo "<li><a href='tarefas.php'>Testar Modal de Estatísticas</a></li>";
    echo "</ol>";
    echo "</div>";
} elseif ($sucessos_originais > 0) {
    echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>⚠️ APIs Originais Funcionando</h4>";
    echo "<p>As APIs originais estão funcionando. O problema pode estar no JavaScript ou no modal.</p>";
    echo "<ol>";
    echo "<li>Verifique o console do navegador (F12)</li>";
    echo "<li>Teste o modal de estatísticas</li>";
    echo "<li>Verifique se há erros JavaScript</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>❌ Problemas Identificados</h4>";
    echo "<p>Nenhuma API está funcionando. Possíveis causas:</p>";
    echo "<ul>";
    echo "<li>Problemas de conexão com banco de dados</li>";
    echo "<li>Usuário não logado corretamente</li>";
    echo "<li>Arquivos corrompidos ou com erros</li>";
    echo "<li>Problemas de permissão</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>✅ Diagnóstico concluído!</strong> Use as recomendações acima para resolver os problemas.</p>";
?>
