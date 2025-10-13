<?php
// /processar_analise_ia_memoria.php (Versão Corrigida para ler da URL)

set_time_limit(0);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo "data: {\"error\": \"Acesso negado.\"}\n\n";
    flush();
    exit();
}

require_once 'includes/db_connect.php';
require_once '/home/u853242961/config/config.php';

// CORREÇÃO: Lê a pergunta da URL (parâmetro GET) em vez do corpo da requisição
$pergunta_usuario = $_GET['pergunta'] ?? '';
$historico_json = $_GET['historico'] ?? '[]';
$historico_conversa = json_decode($historico_json, true);
$userId = $_SESSION['user_id'];

if (empty($pergunta_usuario)) {
    echo "data: {\"error\": \"Nenhuma pergunta fornecida.\"}\n\n";
    flush();
    exit();
}

// --- COLETA DE DADOS PARA CONTEXTO DA IA ---
$contexto = "";
try {
    $sql_despesas = "SELECT c.nome, SUM(t.valor) as total FROM transacoes t JOIN categorias c ON t.id_categoria = c.id WHERE t.id_usuario = ? AND t.tipo = 'despesa' AND t.data_transacao >= CURDATE() - INTERVAL 30 DAY GROUP BY c.nome ORDER BY total DESC";
    $stmt_despesas = $pdo->prepare($sql_despesas);
    $stmt_despesas->execute([$userId]);
    $despesas = $stmt_despesas->fetchAll(PDO::FETCH_ASSOC);
    $contexto .= "Despesas dos últimos 30 dias por categoria:\n";
    foreach ($despesas as $d) { $contexto .= "- " . $d['nome'] . ": R$ " . number_format($d['total'], 2, ',', '.') . "\n"; }

    $sql_tarefas = "SELECT descricao, data_limite, prioridade FROM tarefas WHERE id_usuario = ? AND status = 'pendente' AND data_limite IS NOT NULL ORDER BY data_limite ASC";
    $stmt_tarefas = $pdo->prepare($sql_tarefas);
    $stmt_tarefas->execute([$userId]);
    $tarefas = $stmt_tarefas->fetchAll(PDO::FETCH_ASSOC);
    $contexto .= "\nTarefas pendentes com prazo:\n";
    foreach ($tarefas as $t) { $contexto .= "- " . $t['descricao'] . " (Prioridade: " . $t['prioridade'] . ", Limite: " . date('d/m/Y', strtotime($t['data_limite'])) . ")\n"; }
} catch (PDOException $e) {
    $contexto = "Não foi possível buscar os dados do usuário.";
}

// --- ENGENHARIA DE PROMPT COM HISTÓRICO ---
$instrucoes_sistema = "Sua Identidade: Você é 'Orion', um assistente pessoal de finanças e produtividade. Seu tom é amigável e encorajador. Sua Tarefa: Use o contexto da conversa e os dados financeiros/tarefas para responder à última pergunta do usuário. Formate suas respostas com markdown simples (negrito com **, listas com -). Contexto de Dados (situação atual do usuário):\n" . $contexto;

$contents = [];
if (empty($historico_conversa)) {
    // Se for a primeira pergunta, envia as instruções e a pergunta
    $contents[] = ['role' => 'user', 'parts' => [['text' => $instrucoes_sistema . "\n\nPergunta: " . $pergunta_usuario]]];
} else {
    // Se já houver histórico, o recria para a API
    foreach ($historico_conversa as $msg) {
        $contents[] = $msg;
    }
    // Adiciona a pergunta atual no final
    $contents[] = ['role' => 'user', 'parts' => [['text' => $pergunta_usuario]]];
}

// --- CHAMADA PARA A API DO GEMINI ---
$gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:streamGenerateContent?key=' . GEMINI_API_KEY;
$data = ['contents' => $contents];

$ch = curl_init($gemini_api_url);

$write_function = function($curl, $chunk) {
    echo $chunk;
    flush();
    return strlen($chunk);
};

curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write_function);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);

curl_exec($ch);

if (curl_errno($ch)) {
    echo "data: {\"error\": \"Erro de conexão cURL: " . curl_error($ch) . "\"}\n\n";
    flush();
}
curl_close($ch);
?>