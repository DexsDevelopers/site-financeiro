<?php
// processar_analise_ia_memoria.php
// Versão: Streaming SSE + Contexto Persistente + Correção de API

// 1. Configurações de Streaming
set_time_limit(0); // Sem limite de tempo de execução
@ini_set('zlib.output_compression', 0); // Desativa compressão para stream fluir
@ini_set('implicit_flush', 1);
while (ob_get_level()) ob_end_clean(); // Limpa buffers anteriores

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
// CORS (Opcional, habilite se o front estiver em outro domínio)
header('Access-Control-Allow-Origin: *');

session_start();

// 2. Validação de Sessão
if (!isset($_SESSION['user_id'])) {
    echo "data: " . json_encode(["error" => "Acesso negado. Faça login."]) . "\n\n";
    flush();
    exit();
}

// 3. Includes Seguros
require_once __DIR__ . '/includes/db_connect.php';

// Carregar config de forma segura (tenta caminho absoluto ou relativo)
if (file_exists('/home/u853242961/config/config.php')) {
    require_once '/home/u853242961/config/config.php';
} elseif (file_exists(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
}

// 4. Recebimento de Dados (GET)
// ALERTA: Historico via GET tem limite de tamanho. O ideal futuramente é usar POST + ID temporário.
$pergunta_usuario = $_GET['pergunta'] ?? '';
$historico_json = $_GET['historico'] ?? '[]';
$userId = $_SESSION['user_id'];

// Decodifica histórico e garante que é um array
$historico_conversa = json_decode(urldecode($historico_json), true);
if (!is_array($historico_conversa)) $historico_conversa = [];

if (empty($pergunta_usuario)) {
    echo "data: " . json_encode(["error" => "Pergunta vazia."]) . "\n\n";
    flush();
    exit();
}

// 5. Coleta de Contexto (RAG - Retrieval Augmented Generation)
$contexto = "";
try {
    // Dados Financeiros (Últimos 30 dias)
    // Nota: Usando tabela 'transacoes' (português) conforme seu padrão neste arquivo
    $sql_despesas = "SELECT c.nome, SUM(t.valor) as total 
                     FROM transacoes t 
                     JOIN categorias c ON t.id_categoria = c.id 
                     WHERE t.id_usuario = ? AND t.tipo = 'despesa' 
                     AND t.data_transacao >= CURDATE() - INTERVAL 30 DAY 
                     GROUP BY c.nome 
                     ORDER BY total DESC";
    $stmt = $pdo->prepare($sql_despesas);
    $stmt->execute([$userId]);
    $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $contexto .= "Resumo financeiro (30 dias):\n";
    if (empty($despesas)) {
        $contexto .= "- Nenhuma despesa recente.\n";
    } else {
        foreach ($despesas as $d) { 
            $contexto .= "- " . $d['nome'] . ": R$ " . number_format($d['total'], 2, ',', '.') . "\n"; 
        }
    }

    // Tarefas Pendentes
    $sql_tarefas = "SELECT descricao, data_limite, prioridade 
                    FROM tarefas 
                    WHERE id_usuario = ? AND status = 'pendente' 
                    ORDER BY data_limite ASC LIMIT 10";
    $stmt = $pdo->prepare($sql_tarefas);
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $contexto .= "\nTarefas urgentes:\n";
    if (empty($tarefas)) {
        $contexto .= "- Tudo em dia!\n";
    } else {
        foreach ($tarefas as $t) { 
            $dataLim = $t['data_limite'] ? date('d/m', strtotime($t['data_limite'])) : 'S/D';
            $contexto .= "- " . $t['descricao'] . " [" . $t['prioridade'] . "] (Até: $dataLim)\n"; 
        }
    }
} catch (PDOException $e) {
    $contexto = "Erro ao ler banco de dados: " . $e->getMessage();
}

// 6. Montagem do Payload (Correção de Lógica)
// Usamos 'systemInstruction' para garantir que o contexto financeiro SEMPRE esteja presente,
// independente do tamanho do histórico.

$systemInstruction = "Você é Orion, assistente pessoal de finanças. 
Seja direto, motivador e use Markdown.
CONTEXTO ATUAL DO USUÁRIO:
$contexto";

// Limpeza básica do histórico para evitar erros da API (formatos inválidos)
$contents = [];
foreach ($historico_conversa as $msg) {
    if (isset($msg['role']) && isset($msg['parts'])) {
        $contents[] = [
            'role' => ($msg['role'] == 'user' || $msg['role'] == 'model') ? $msg['role'] : 'user',
            'parts' => $msg['parts']
        ];
    }
}

// Adiciona a pergunta atual
$contents[] = ['role' => 'user', 'parts' => [['text' => $pergunta_usuario]]];

// 7. Chamada API Streaming
// URL Corrigida: Usando 'gemini-1.5-flash' (alias padrão estável)
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:streamGenerateContent?key=' . GEMINI_API_KEY;

$payload = [
    'contents' => $contents,
    'systemInstruction' => [
        'parts' => [['text' => $systemInstruction]]
    ],
    'generationConfig' => [
        'temperature' => 0.4,
        'maxOutputTokens' => 500
    ]
];

$ch = curl_init($url);

// Função de Callback para Streaming
$write_function = function($curl, $chunk) {
    // A API do Google manda chunks brutos. Para SSE, precisamos garantir o prefixo data:
    // No entanto, se o seu frontend espera JSON bruto do Google, mantenha o echo $chunk.
    // Para compatibilidade padrão SSE, encapsulamos:
    
    if (trim($chunk) !== "") {
        // Envia o chunk bruto prefixado com data: para o JS processar
        echo "data: " . json_encode(['raw' => $chunk]) . "\n\n";
        flush();
    }
    return strlen($chunk);
};

curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write_function);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas se tiver problemas com SSL local
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout da conexão

curl_exec($ch);

if (curl_errno($ch)) {
    echo "data: " . json_encode(["error" => "Erro cURL: " . curl_error($ch)]) . "\n\n";
    flush();
}

curl_close($ch);

// Evento final para fechar conexão no front
echo "data: [DONE]\n\n";
flush();
?>