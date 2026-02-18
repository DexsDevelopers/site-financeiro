<?php
// corrigir_apis_json.php - Correção definitiva das APIs JSON

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔧 CORREÇÃO DEFINITIVA DAS APIs JSON</h2>";

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

// 1. Corrigir API de Tarefas de Hoje
echo "<h3>1. Corrigindo API de Tarefas de Hoje</h3>";

$api_tarefas_hoje = '<?php
// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_clean();
}

session_start();

// Headers corretos para JSON
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

$response = ["success" => false, "message" => "Erro inesperado."];

// Verificar se o usuário está logado
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    $response["message"] = "Acesso negado.";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    require_once "includes/db_connect.php";
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = "Erro de conexão: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = $_SESSION["user_id"];
$dataHoje = date("Y-m-d");

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE \'tarefas\'");
    if (!$stmt_check->fetch()) {
        $response["message"] = "Tabela tarefas não encontrada.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Buscar tarefas de hoje
    $stmt = $pdo->prepare("
        SELECT 
            id,
            descricao,
            prioridade,
            status,
            data_limite,
            tempo_estimado,
            data_criacao,
            data_conclusao
        FROM tarefas 
        WHERE id_usuario = ? 
        AND (
            DATE(data_limite) = ? 
            OR DATE(data_criacao) = ?
        )
        ORDER BY 
            CASE prioridade 
                WHEN \'Alta\' THEN 1 
                WHEN \'Média\' THEN 2 
                WHEN \'Baixa\' THEN 3 
            END,
            data_criacao DESC
    ");
    $stmt->execute([$userId, $dataHoje, $dataHoje]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sanitizar dados
    foreach ($tarefas as &$tarefa) {
        $tarefa["descricao"] = mb_convert_encoding($tarefa["descricao"], "UTF-8", "UTF-8");
        $tarefa["prioridade"] = mb_convert_encoding($tarefa["prioridade"], "UTF-8", "UTF-8");
        $tarefa["status"] = mb_convert_encoding($tarefa["status"], "UTF-8", "UTF-8");
    }
    
    $response["success"] = true;
    $response["tarefas"] = $tarefas;
    $response["total"] = count($tarefas);
    $response["message"] = "Tarefas de hoje carregadas com sucesso";
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response["message"] = "Erro no banco de dados: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = "Erro geral: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>';

if (file_put_contents('api_tarefas_hoje.php', $api_tarefas_hoje)) {
    echo "✅ API de tarefas de hoje corrigida<br>";
} else {
    echo "❌ Erro ao corrigir API de tarefas de hoje<br>";
}

// 2. Corrigir API de Distribuição por Prioridade
echo "<h3>2. Corrigindo API de Distribuição por Prioridade</h3>";

$api_distribuicao = '<?php
// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_clean();
}

session_start();

// Headers corretos para JSON
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

$response = ["success" => false, "message" => "Erro inesperado."];

// Verificar se o usuário está logado
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    $response["message"] = "Acesso negado.";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    require_once "includes/db_connect.php";
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = "Erro de conexão: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = $_SESSION["user_id"];

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE \'tarefas\'");
    if (!$stmt_check->fetch()) {
        $response["message"] = "Tabela tarefas não encontrada.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Buscar distribuição por prioridade
    $stmt = $pdo->prepare("
        SELECT 
            prioridade,
            COUNT(*) as total
        FROM tarefas 
        WHERE id_usuario = ? 
        GROUP BY prioridade
        ORDER BY 
            CASE prioridade
                WHEN \'Alta\' THEN 1
                WHEN \'Média\' THEN 2
                WHEN \'Baixa\' THEN 3
                ELSE 4
            END
    ");
    
    $stmt->execute([$userId]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico
    $labels = [];
    $dados = [];
    $cores = [];
    
    foreach ($resultados as $resultado) {
        $prioridade = trim($resultado["prioridade"]);
        $total = (int)$resultado["total"];
        
        $labels[] = ucfirst($prioridade);
        $dados[] = $total;
        
        // Definir cores baseadas na prioridade
        switch (strtolower($prioridade)) {
            case \'alta\':
                $cores[] = "#fd7e14";
                break;
            case \'média\':
                $cores[] = "#6c757d";
                break;
            case \'baixa\':
                $cores[] = "#28a745";
                break;
            default:
                $cores[] = "#6c757d";
        }
    }
    
    $response["success"] = true;
    $response["labels"] = $labels;
    $response["tarefas"] = $dados;
    $response["cores"] = $cores;
    $response["total"] = array_sum($dados);
    $response["message"] = "Distribuição por prioridade carregada com sucesso";
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response["message"] = "Erro no banco de dados: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = "Erro geral: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>';

if (file_put_contents('api_distribuicao_prioridade.php', $api_distribuicao)) {
    echo "✅ API de distribuição por prioridade corrigida<br>";
} else {
    echo "❌ Erro ao corrigir API de distribuição por prioridade<br>";
}

// 3. Corrigir API de Produtividade 7 Dias
echo "<h3>3. Corrigindo API de Produtividade 7 Dias</h3>";

$api_produtividade = '<?php
// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_clean();
}

session_start();

// Headers corretos para JSON
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

$response = ["success" => false, "message" => "Erro inesperado."];

// Verificar se o usuário está logado
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    $response["message"] = "Acesso negado.";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    require_once "includes/db_connect.php";
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = "Erro de conexão: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = $_SESSION["user_id"];

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE \'tarefas\'");
    if (!$stmt_check->fetch()) {
        $response["message"] = "Tabela tarefas não encontrada.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Buscar produtividade dos últimos 7 dias
    $stmt = $pdo->prepare("
        SELECT 
            DATE(data_conclusao) as data,
            COUNT(*) as total
        FROM tarefas 
        WHERE id_usuario = ? 
        AND status = \'concluida\'
        AND data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(data_conclusao)
        ORDER BY data ASC
    ");
    
    $stmt->execute([$userId]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico (últimos 7 dias)
    $labels = [];
    $dados = [];
    
    // Criar array com os últimos 7 dias
    for ($i = 6; $i >= 0; $i--) {
        $data = date("Y-m-d", strtotime("-$i days"));
        $labels[] = date("d/m", strtotime($data));
        
        // Procurar dados para esta data
        $encontrado = false;
        foreach ($resultados as $resultado) {
            if ($resultado["data"] == $data) {
                $dados[] = (int)$resultado["total"];
                $encontrado = true;
                break;
            }
        }
        
        if (!$encontrado) {
            $dados[] = 0;
        }
    }
    
    // Calcular estatísticas
    $total_7_dias = array_sum($dados);
    $media_diaria = $total_7_dias / 7;
    
    $response["success"] = true;
    $response["labels"] = $labels;
    $response["tarefas"] = $dados;
    $response["totalGeral"] = $total_7_dias;
    $response["media_diaria"] = round($media_diaria, 1);
    $response["message"] = "Produtividade dos últimos 7 dias carregada com sucesso";
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response["message"] = "Erro no banco de dados: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = "Erro geral: " . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>';

if (file_put_contents('api_produtividade_7_dias.php', $api_produtividade)) {
    echo "✅ API de produtividade 7 dias corrigida<br>";
} else {
    echo "❌ Erro ao corrigir API de produtividade 7 dias<br>";
}

// 4. Atualizar JavaScript em tarefas.php
echo "<h3>4. Atualizando JavaScript em tarefas.php</h3>";

$arquivo_tarefas = 'tarefas.php';
if (file_exists($arquivo_tarefas)) {
    $conteudo = file_get_contents($arquivo_tarefas);
    
    // Substituir URLs das APIs
    $conteudo = str_replace('buscar_tarefas_hoje.php', 'api_tarefas_hoje.php', $conteudo);
    $conteudo = str_replace('buscar_distribuicao_prioridade.php', 'api_distribuicao_prioridade.php', $conteudo);
    $conteudo = str_replace('buscar_produtividade_7_dias.php', 'api_produtividade_7_dias.php', $conteudo);
    
    if (file_put_contents($arquivo_tarefas, $conteudo)) {
        echo "✅ JavaScript atualizado em tarefas.php<br>";
    } else {
        echo "❌ Erro ao atualizar JavaScript<br>";
    }
} else {
    echo "❌ Arquivo tarefas.php não encontrado<br>";
}

// 5. Teste final das APIs
echo "<h3>5. Teste Final das APIs</h3>";

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
echo "<p><strong>✅ Correção concluída!</strong> Use as recomendações acima para resolver os problemas.</p>";
?>
