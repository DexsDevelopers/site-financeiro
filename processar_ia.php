<?php
// /processar_ia.php (Versão com Criação Inteligente de Categoria)

session_start();
header('Content-Type: application/json');

require_once 'includes/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

$input = json_decode(file_get_contents('php://input'), true);
$texto_usuario = $input['texto'] ?? '';
$userId = $_SESSION['user_id'];

if (empty($texto_usuario)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Nenhum texto fornecido.']));
}

// --- COLETA DE DADOS PARA CONTEXTO DA IA ---
try {
    $stmt_cats = $pdo->prepare("SELECT nome, tipo FROM categorias WHERE id_usuario = ?");
    $stmt_cats->execute([$userId]);
    $categorias_usuario = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
    
    $categorias_despesa = [];
    $categorias_receita = [];
    foreach ($categorias_usuario as $cat) {
        if ($cat['tipo'] === 'despesa') {
            $categorias_despesa[] = "'" . $cat['nome'] . "'";
        } else {
            $categorias_receita[] = "'" . $cat['nome'] . "'";
        }
    }
    $lista_cat_despesa = implode(', ', $categorias_despesa);
    $lista_cat_receita = implode(', ', $categorias_receita);

} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Erro ao buscar categorias no banco de dados.']));
}

// ==========================================================
// INÍCIO DA ENGENHARIA DE PROMPT AVANÇADA
// ==========================================================
$prompt = "
    Analise o texto a seguir para extrair os detalhes de uma transação financeira e retorne APENAS um objeto JSON válido.

    **IMPORTANTE: Seja FLEXÍVEL na interpretação. Mesmo que o usuário não forneça todos os detalhes explicitamente, tente inferir informações razoáveis.**

    **Sua Tarefa Principal:**
    1.  Extraia 'descricao', 'valor' e 'data'.
    2.  Determine o 'tipo' ('receita' ou 'despesa') com base em palavras-chave como 'gastei', 'comprei', 'recebi', 'vendi', 'paguei', 'ganhei'.
    3.  **Decisão de Categoria (IMPORTANTE):**
        - **Primeiro, tente encaixar** a transação em uma das categorias existentes. A lista de despesas é: [$lista_cat_despesa]. A lista de receitas é: [$lista_cat_receita].
        - **Se não conseguir encaixar perfeitamente**, escolha a categoria mais próxima ou similar.
        - **Apenas se realmente não houver nenhuma categoria relacionada**, crie uma nova usando 'nova_categoria_nome' e 'nova_categoria_tipo'.

    **Regras de Inferência:**
    - Se não houver valor explícito, mas houver contexto (ex: 'almoço'), use valores típicos razoáveis.
    - Se não houver data explícita, assuma 'hoje': " . date('Y-m-d') . ".
    - Se não houver categoria explícita, infira pela descrição (ex: 'pizza' = Alimentação, 'uber' = Transporte).
    - Para descrições vagas como 'comprei algo', seja mais específico baseado no contexto.

    **Regras de Formato:**
    - 'valor' deve ser um número com '.' como separador decimal.
    - 'data' deve estar no formato 'YYYY-MM-DD'. 'Hoje' é " . date('Y-m-d') . ", 'ontem' é " . date('Y-m-d', strtotime('-1 day')) . ".

    **Texto do usuário:**
    \"" . $texto_usuario . "\"

    **Exemplo de Saída 1 (Categoria Existente):**
    {
      \"descricao\": \"Conta de luz de Agosto\",
      \"valor\": 150.75,
      \"data\": \"2025-08-01\",
      \"categoria_nome\": \"Contas\" 
    }

    **Exemplo de Saída 2 (Nova Categoria):**
    {
      \"descricao\": \"Rendimento do Tesouro Direto\",
      \"valor\": 55.40,
      \"data\": \"2025-08-01\",
      \"nova_categoria_nome\": \"Investimentos\",
      \"nova_categoria_tipo\": \"receita\"
    }
";
// ==========================================================
// FIM DA ENGENHARIA DE PROMPT
// ==========================================================

// --- CHAMADA PARA A API DO GEMINI ---
$gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . GEMINI_API_KEY;
$data = ['contents' => [['parts' => [['text' => $prompt]]]]];

$ch = curl_init($gemini_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos
$response_string = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Verificar erros de conectividade
if ($curl_error) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Erro de conexão com o serviço de IA. Tente novamente em alguns instantes.', 'debug' => 'cURL Error: ' . $curl_error]));
}

if ($http_code !== 200) {
    // Log detalhado do erro para debug
    $error_details = '';
    switch ($http_code) {
        case 400:
            $error_details = 'Requisição inválida (400). Verifique o formato dos dados.';
            break;
        case 401:
            $error_details = 'Chave API inválida ou expirada (401).';
            break;
        case 403:
            $error_details = 'Acesso negado (403). Verifique as permissões da API.';
            break;
        case 429:
            $error_details = 'Limite de requisições excedido (429). Aguarde alguns minutos.';
            break;
        case 500:
        case 502:
        case 503:
            $error_details = "Erro no servidor da Google ($http_code). Tente novamente em alguns minutos.";
            break;
        default:
            $error_details = "Erro HTTP $http_code desconhecido.";
    }
    
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => $error_details, 'debug' => 'HTTP Code: ' . $http_code, 'response' => substr($response_string, 0, 500)]));
}

$response_data = json_decode($response_string, true);
$json_text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Verificar se a resposta da API está vazia
if (empty($json_text)) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'A IA não conseguiu processar sua solicitação. Tente reformular com mais detalhes.']));
}

$json_text_cleaned = trim(str_replace(['```json', '```'], '', $json_text));
$resultado_ia = json_decode($json_text_cleaned, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($resultado_ia)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Não consegui processar sua solicitação. Tente incluir mais detalhes como: descrição, valor e categoria. Exemplo: "Comprei pizza por R$ 25 em Alimentação".']));
}

// Validação mais específica dos campos obrigatórios
if (empty($resultado_ia['descricao']) || empty($resultado_ia['valor']) || empty($resultado_ia['data'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Informações incompletas. Por favor, inclua pelo menos: o que foi (descrição), quanto custou (valor) e quando foi (data ou "hoje/ontem"). Exemplo: "Almoço R$ 30 hoje".']));
}

// --- LÓGICA PARA SALVAR NO BANCO DE DADOS ---
try {
    $id_categoria = null;
    $tipo_transacao = '';

    // Se a IA sugeriu uma NOVA categoria
    if (isset($resultado_ia['nova_categoria_nome'])) {
        $nova_cat_nome = $resultado_ia['nova_categoria_nome'];
        $nova_cat_tipo = $resultado_ia['nova_categoria_tipo'];

        // Insere a nova categoria no banco
        $stmt_nova_cat = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
        $stmt_nova_cat->execute([$userId, $nova_cat_nome, $nova_cat_tipo]);
        $id_categoria = $pdo->lastInsertId();
        $tipo_transacao = $nova_cat_tipo;
    
    // Se a IA usou uma categoria EXISTENTE
    } elseif (isset($resultado_ia['categoria_nome'])) {
        $stmt_cat_id = $pdo->prepare("SELECT id, tipo FROM categorias WHERE nome = ? AND id_usuario = ?");
        $stmt_cat_id->execute([$resultado_ia['categoria_nome'], $userId]);
        $categoria_info = $stmt_cat_id->fetch(PDO::FETCH_ASSOC);

        if (!$categoria_info) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => "A categoria '" . htmlspecialchars($resultado_ia['categoria_nome']) . "' sugerida pela IA não foi encontrada."]));
        }
        $id_categoria = $categoria_info['id'];
        $tipo_transacao = $categoria_info['tipo'];
    } else {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'A IA não conseguiu definir uma categoria.']));
    }

    // Finalmente, insere a transação
    $sql = "INSERT INTO transacoes (id_usuario, id_categoria, descricao, valor, tipo, data_transacao) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([ $userId, $id_categoria, $resultado_ia['descricao'], $resultado_ia['valor'], $tipo_transacao, $resultado_ia['data'] ]);

    echo json_encode(['success' => true, 'message' => 'Lançamento adicionado pela IA com sucesso!']);

} catch (PDOException $e) {
    http_response_code(500);
    // Adiciona a mensagem de erro do banco de dados para depuração
    exit(json_encode(['success' => false, 'message' => 'Erro ao salvar no banco de dados: ' . $e->getMessage()]));
}
?>