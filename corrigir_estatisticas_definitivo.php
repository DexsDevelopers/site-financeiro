<?php
// corrigir_estatisticas_definitivo.php - Correção definitiva das estatísticas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔧 CORREÇÃO DEFINITIVA DAS ESTATÍSTICAS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para criar/atualizar API
function criarAPI($nome, $conteudo) {
    $arquivo = $nome . '.php';
    
    if (file_put_contents($arquivo, $conteudo)) {
        echo "✅ Arquivo $arquivo criado/atualizado<br>";
        return true;
    } else {
        echo "❌ Erro ao criar arquivo $arquivo<br>";
        return false;
    }
}

// 1. Corrigir API de Tarefas de Hoje
echo "<h3>1. Corrigindo API de Tarefas de Hoje</h3>";

$api_tarefas_hoje = '<?php
session_start();
require_once "includes/db_connect.php";

// Limpar qualquer output anterior
ob_clean();

// Configurar headers
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

try {
    // Verificar se o usuário está logado
    if (!isset($_SESSION["user_id"])) {
        throw new Exception("Usuário não está logado");
    }
    
    $user_id = $_SESSION["user_id"];
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE \'tarefas\'");
    if (!$stmt->fetch()) {
        throw new Exception("Tabela \'tarefas\' não existe");
    }
    
    // Buscar tarefas de hoje
    $stmt = $pdo->prepare("
        SELECT 
            id,
            titulo,
            descricao,
            prioridade,
            status,
            data_limite,
            data_criacao,
            data_conclusao
        FROM tarefas 
        WHERE id_usuario = ? 
        AND (
            DATE(data_limite) = CURDATE() 
            OR DATE(data_criacao) = CURDATE()
        )
        ORDER BY prioridade DESC, data_limite ASC
    ");
    
    $stmt->execute([$user_id]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sanitizar dados
    foreach ($tarefas as &$tarefa) {
        $tarefa["titulo"] = mb_convert_encoding($tarefa["titulo"], "UTF-8", "auto");
        $tarefa["descricao"] = mb_convert_encoding($tarefa["descricao"], "UTF-8", "auto");
        $tarefa["prioridade"] = trim($tarefa["prioridade"]);
    }
    
    // Preparar resposta
    $response = [
        "success" => true,
        "tarefas" => $tarefas,
        "total" => count($tarefas),
        "message" => "Tarefas de hoje carregadas com sucesso"
    ];
    
    // Verificar se json_encode funcionou
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception("Erro ao codificar JSON: " . json_last_error_msg());
    }
    
    echo $json;
    
} catch (PDOException $e) {
    $response = [
        "success" => false,
        "message" => "Erro de banco de dados: " . $e->getMessage(),
        "tarefas" => [],
        "total" => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $response = [
        "success" => false,
        "message" => "Erro: " . $e->getMessage(),
        "tarefas" => [],
        "total" => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>';

criarAPI('api_tarefas_hoje', $api_tarefas_hoje);

// 2. Corrigir API de Distribuição por Prioridade
echo "<h3>2. Corrigindo API de Distribuição por Prioridade</h3>";

$api_distribuicao = '<?php
session_start();
require_once "includes/db_connect.php";

// Limpar qualquer output anterior
ob_clean();

// Configurar headers
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

try {
    // Verificar se o usuário está logado
    if (!isset($_SESSION["user_id"])) {
        throw new Exception("Usuário não está logado");
    }
    
    $user_id = $_SESSION["user_id"];
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE \'tarefas\'");
    if (!$stmt->fetch()) {
        throw new Exception("Tabela \'tarefas\' não existe");
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
                WHEN \'urgente\' THEN 1
                WHEN \'alta\' THEN 2
                WHEN \'media\' THEN 3
                WHEN \'baixa\' THEN 4
                ELSE 5
            END
    ");
    
    $stmt->execute([$user_id]);
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
            case \'urgente\':
                $cores[] = \'#dc3545\';
                break;
            case \'alta\':
                $cores[] = \'#fd7e14\';
                break;
            case \'media\':
                $cores[] = \'#ffc107\';
                break;
            case \'baixa\':
                $cores[] = \'#28a745\';
                break;
            default:
                $cores[] = \'#6c757d\';
        }
    }
    
    // Preparar resposta
    $response = [
        "success" => true,
        "labels" => $labels,
        "tarefas" => $dados,
        "cores" => $cores,
        "total" => array_sum($dados),
        "message" => "Distribuição por prioridade carregada com sucesso"
    ];
    
    // Verificar se json_encode funcionou
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception("Erro ao codificar JSON: " . json_last_error_msg());
    }
    
    echo $json;
    
} catch (PDOException $e) {
    $response = [
        "success" => false,
        "message" => "Erro de banco de dados: " . $e->getMessage(),
        "labels" => [],
        "tarefas" => [],
        "cores" => [],
        "total" => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $response = [
        "success" => false,
        "message" => "Erro: " . $e->getMessage(),
        "labels" => [],
        "tarefas" => [],
        "cores" => [],
        "total" => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>';

criarAPI('api_distribuicao_prioridade', $api_distribuicao);

// 3. Corrigir API de Produtividade 7 Dias
echo "<h3>3. Corrigindo API de Produtividade 7 Dias</h3>";

$api_produtividade = '<?php
session_start();
require_once "includes/db_connect.php";

// Limpar qualquer output anterior
ob_clean();

// Configurar headers
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

try {
    // Verificar se o usuário está logado
    if (!isset($_SESSION["user_id"])) {
        throw new Exception("Usuário não está logado");
    }
    
    $user_id = $_SESSION["user_id"];
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE \'tarefas\'");
    if (!$stmt->fetch()) {
        throw new Exception("Tabela \'tarefas\' não existe");
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
    
    $stmt->execute([$user_id]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico (últimos 7 dias)
    $labels = [];
    $dados = [];
    
    // Criar array com os últimos 7 dias
    for ($i = 6; $i >= 0; $i--) {
        $data = date(\'Y-m-d\', strtotime("-$i days"));
        $labels[] = date(\'d/m\', strtotime($data));
        
        // Procurar dados para esta data
        $encontrado = false;
        foreach ($resultados as $resultado) {
            if ($resultado[\'data\'] == $data) {
                $dados[] = (int)$resultado[\'total\'];
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
    
    // Preparar resposta
    $response = [
        "success" => true,
        "labels" => $labels,
        "tarefas" => $dados,
        "totalGeral" => $total_7_dias,
        "media_diaria" => round($media_diaria, 1),
        "message" => "Produtividade dos últimos 7 dias carregada com sucesso"
    ];
    
    // Verificar se json_encode funcionou
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception("Erro ao codificar JSON: " . json_last_error_msg());
    }
    
    echo $json;
    
} catch (PDOException $e) {
    $response = [
        "success" => false,
        "message" => "Erro de banco de dados: " . $e->getMessage(),
        "labels" => [],
        "tarefas" => [],
        "totalGeral" => 0,
        "media_diaria" => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $response = [
        "success" => false,
        "message" => "Erro: " . $e->getMessage(),
        "labels" => [],
        "tarefas" => [],
        "totalGeral" => 0,
        "media_diaria" => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>';

criarAPI('api_produtividade_7_dias', $api_produtividade);

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

// 5. Criar dados de teste se necessário
echo "<h3>5. Verificando/Criando Dados de Teste</h3>";

try {
    // Verificar se há tarefas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch()['total'];
    
    if ($total == 0) {
        echo "⚠️ Nenhuma tarefa encontrada. Criando dados de teste...<br>";
        
        // Criar algumas tarefas de exemplo
        $tarefas_exemplo = [
            [
                'titulo' => 'Tarefa Urgente de Hoje',
                'descricao' => 'Esta é uma tarefa urgente para hoje',
                'prioridade' => 'urgente',
                'status' => 'pendente',
                'data_limite' => date('Y-m-d H:i:s')
            ],
            [
                'titulo' => 'Tarefa Concluída Ontem',
                'descricao' => 'Esta tarefa foi concluída ontem',
                'prioridade' => 'alta',
                'status' => 'concluida',
                'data_limite' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'data_conclusao' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'titulo' => 'Tarefa de Média Prioridade',
                'descricao' => 'Esta é uma tarefa de média prioridade',
                'prioridade' => 'media',
                'status' => 'pendente',
                'data_limite' => date('Y-m-d H:i:s', strtotime('+1 day'))
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO tarefas (id_usuario, titulo, descricao, prioridade, status, data_limite, data_criacao, data_conclusao) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        foreach ($tarefas_exemplo as $tarefa) {
            $data_conclusao = isset($tarefa['data_conclusao']) ? $tarefa['data_conclusao'] : null;
            $stmt->execute([
                $_SESSION['user_id'],
                $tarefa['titulo'],
                $tarefa['descricao'],
                $tarefa['prioridade'],
                $tarefa['status'],
                $tarefa['data_limite'],
                $data_conclusao
            ]);
        }
        
        echo "✅ Dados de teste criados<br>";
    } else {
        echo "✅ Já existem $total tarefas<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar/criar dados: " . $e->getMessage() . "<br>";
}

// 6. Teste final
echo "<h3>6. Teste Final das APIs</h3>";

$apis_teste = [
    'api_tarefas_hoje.php' => 'Tarefas de Hoje',
    'api_distribuicao_prioridade.php' => 'Distribuição por Prioridade',
    'api_produtividade_7_dias.php' => 'Produtividade 7 Dias'
];

foreach ($apis_teste as $arquivo => $nome) {
    echo "<h4>Testando: $nome</h4>";
    
    if (file_exists($arquivo)) {
        echo "✅ Arquivo existe<br>";
        
        // Capturar output
        ob_start();
        try {
            include $arquivo;
            $output = ob_get_clean();
            
            $data = json_decode($output, true);
            if ($data !== null && isset($data['success']) && $data['success'] === true) {
                echo "✅ API funcionando corretamente<br>";
                echo "📊 Dados: " . json_encode($data, JSON_PRETTY_PRINT) . "<br>";
            } else {
                echo "❌ API com problemas: " . ($data['message'] ?? 'Erro desconhecido') . "<br>";
            }
        } catch (Exception $e) {
            ob_end_clean();
            echo "❌ Erro: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Arquivo não encontrado<br>";
    }
    echo "<hr>";
}

echo "<h2>✅ CORREÇÃO CONCLUÍDA!</h2>";
echo "<p><strong>Próximos passos:</strong></p>";
echo "<ol>";
echo "<li><a href='tarefas.php'>Testar o modal de estatísticas</a></li>";
echo "<li><a href='diagnostico_estatisticas_completo.php'>Executar diagnóstico completo</a></li>";
echo "<li>Verificar se os gráficos estão funcionando</li>";
echo "</ol>";
?>
