<?php
// processar_ia.php (Versão Otimizada e Blindada)

session_start();
header('Content-Type: application/json; charset=utf-8'); // Forçar UTF-8

// 1. Configurações Iniciais e Timezone
date_default_timezone_set('America/Sao_Paulo'); // Garante que "hoje" seja hoje no Brasil
require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';

// Carregar Configuração (se não estiver no db_connect)
// Certifique-se que GEMINI_API_KEY está definida antes deste ponto
if (!defined('GEMINI_API_KEY')) {
    // Tenta carregar de um config se não estiver definido
    if (file_exists('includes/config.php')) require_once 'includes/config.php';
    if (!defined('GEMINI_API_KEY')) {
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'Erro de Configuração: API Key não encontrada.']));
    }
}

// 2. Autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$texto_usuario = trim($input['texto'] ?? '');
$id_conta_req = isset($input['id_conta']) ? (int)$input['id_conta'] : null;

if (empty($texto_usuario)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Nenhum texto fornecido.']));
}

// 3. Rate Limiting Interno
$enableInternalRateLimit = true; 
if ($enableInternalRateLimit && class_exists('RateLimiter')) {
    try {
        $rateLimiter = new RateLimiter($pdo);
        $rateLimitCheck = $rateLimiter->checkRateLimit($userId, 'gemini');

        if (!$rateLimitCheck['allowed']) {
            http_response_code(429);
            exit(json_encode([
                'success' => false,
                'message' => $rateLimitCheck['message'],
                'retry_after' => $rateLimitCheck['retry_after'],
                'internal_rate_limit' => true
            ]));
        }
    } catch (Exception $e) {
        error_log("Rate Limiter Error: " . $e->getMessage());
    }
}

// 4. Coleta de Contexto (Categorias)
try {
    $stmt_cats = $pdo->prepare("SELECT nome, tipo FROM categorias WHERE id_usuario = ?");
    $stmt_cats->execute([$userId]);
    $categorias_usuario = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
    
    $categorias_despesa = [];
    $categorias_receita = [];
    // Normalização para evitar problemas de aspas
    foreach ($categorias_usuario as $cat) {
        $nome_sanitizado = str_replace('"', '', $cat['nome']); 
        if ($cat['tipo'] === 'despesa') {
            $categorias_despesa[] = '"' . $nome_sanitizado . '"';
        } else {
            $categorias_receita[] = '"' . $nome_sanitizado . '"';
        }
    }
    $lista_cat_despesa = implode(', ', $categorias_despesa);
    $lista_cat_receita = implode(', ', $categorias_receita);

} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Erro ao buscar contexto.']));
}

// 5. Engenharia de Prompt
$hoje = date('Y-m-d');
$ontem = date('Y-m-d', strtotime('-1 day'));

$prompt = <<<PROMPT
Analise o texto e extraia os dados financeiros em JSON estrito.

CONTEXTO:
- Data Hoje: $hoje
- Data Ontem: $ontem
- Categorias DESPESA disponíveis: [$lista_cat_despesa]
- Categorias RECEITA disponíveis: [$lista_cat_receita]

REGRAS:
1. Retorne APENAS o JSON. Sem Markdown (```json), sem comentários.
2. Identifique 'descricao', 'valor' (float), 'data' (YYYY-MM-DD).
3. CATEGORIA: 
   - Tente encaixar EXATAMENTE em uma das categorias listadas acima.
   - Se não houver correspondência exata, use a mais próxima.
   - SÓ crie nova se for totalmente diferente. Use chaves: "nova_categoria_nome", "nova_categoria_tipo".
4. TIPO: 'receita' ou 'despesa'.

INPUT DO USUÁRIO: "$texto_usuario"

EXEMPLO JSON:
{
  "descricao": "Uber para trabalho",
  "valor": 25.50,
  "data": "$hoje",
  "categoria_nome": "Transporte",
  "tipo": "despesa"
}
PROMPT;

// 6. Função de Chamada API (Isolada)
function callGeminiAPI($prompt) {
    $api_key = GEMINI_API_KEY;
    // Lista de modelos ordenada por preferência (Flash é mais rápido/barato, Pro é fallback)
    $models = ['gemini-2.5-flash', 'gemini-2.5-pro']; 
    
    foreach ($models as $model) {
        $url = "[https://generativelanguage.googleapis.com/v1beta/models/](https://generativelanguage.googleapis.com/v1beta/models/){$model}:generateContent?key={$api_key}";
        
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.2] // Temperatura baixa para ser mais determinístico
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout mais curto para não travar o PHP
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        }
        // Se der erro 429 (Quota) ou 5xx, tenta o próximo modelo no loop
        if ($http_code !== 429 && $http_code < 500) {
            break; // Se for erro 400 (Bad Request), não adianta tentar outro modelo
        }
    }
    return null; // Falha total
}

// 7. Processamento da IA
$response_data = callGeminiAPI($prompt);

if (!$response_data || !isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
    http_response_code(502); // Bad Gateway
    exit(json_encode(['success' => false, 'message' => 'A IA está indisponível ou sobrecarregada no momento.']));
}

$raw_text = $response_data['candidates'][0]['content']['parts'][0]['text'];

// 8. Extração Robusta de JSON (Regex)
// A IA as vezes responde "Aqui está o JSON: { ... }". O json_decode falha nisso.
// Este regex captura apenas o conteúdo entre a primeira { e a última }.
preg_match('/\{[\s\S]*\}/', $raw_text, $matches);
$json_string = $matches[0] ?? '{}';

$dados = json_decode($json_string, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($dados['valor'])) {
    http_response_code(422); // Unprocessable Entity
    exit(json_encode(['success' => false, 'message' => 'Não entendi os dados. Tente: "Almoço 30 reais hoje".']));
}

// 9. Lógica de Banco de Dados
try {
    $pdo->beginTransaction();

    // A. Resolver Conta
    $id_conta = $id_conta_req;
    if (!$id_conta) {
        $stmt = $pdo->prepare("SELECT id FROM contas WHERE id_usuario = ? LIMIT 1");
        $stmt->execute([$userId]);
        $id_conta = $stmt->fetchColumn();
        
        // Se não existir conta, cria uma padrão
        if (!$id_conta) {
            $stmt = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, saldo_inicial) VALUES (?, 'Carteira', 'dinheiro', 0)");
            $stmt->execute([$userId]);
            $id_conta = $pdo->lastInsertId();
        }
    }

    // B. Resolver Categoria
    $id_categoria = null;
    $tipo_final = $dados['tipo'] ?? 'despesa';

    if (!empty($dados['nova_categoria_nome'])) {
        // Criar Nova
        $stmt = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $dados['nova_categoria_nome'], $dados['nova_categoria_tipo'] ?? $tipo_final]);
        $id_categoria = $pdo->lastInsertId();
    } else {
        // Buscar Existente (Case Insensitive para garantir)
        $cat_nome = $dados['categoria_nome'] ?? 'Outros';
        $stmt = $pdo->prepare("SELECT id, tipo FROM categorias WHERE id_usuario = ? AND nome LIKE ? LIMIT 1");
        $stmt->execute([$userId, $cat_nome]); // LIKE padrão do MySQL é case-insensitive
        $cat_existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cat_existente) {
            $id_categoria = $cat_existente['id'];
            $tipo_final = $cat_existente['tipo']; // Prevalece o tipo da categoria do banco
        } else {
            // Fallback: Cria categoria se a IA disse que existia mas o banco não achou (ex: erro de digitação da IA)
            $stmt = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $cat_nome, $tipo_final]);
            $id_categoria = $pdo->lastInsertId();
        }
    }

    // C. Inserir Transação
    $stmt = $pdo->prepare("INSERT INTO transacoes (id_usuario, id_categoria, id_conta, descricao, valor, tipo, data_transacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $id_categoria,
        $id_conta,
        $dados['descricao'],
        $dados['valor'],
        $tipo_final,
        $dados['data']
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Lançamento salvo!',
        'dados' => $dados // Retorna os dados para o frontend mostrar feedback
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]));
}
?>