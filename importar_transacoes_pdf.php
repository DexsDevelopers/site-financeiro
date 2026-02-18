<?php
// importar_transacoes_pdf.php - Endpoint para importar transações do PDF

session_start();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

// Verificar se usuário está logado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não logado';
    echo json_encode($response);
    exit();
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido';
    echo json_encode($response);
    exit();
}

$userId = $_SESSION['user_id'];

// Conectar ao banco de dados
try {
    require_once 'includes/db_connect.php';
} catch (Exception $e) {
    $response['message'] = 'Erro de conexão com banco de dados';
    echo json_encode($response);
    exit();
}

// Verificar se transações foram enviadas
$transacoes = json_decode($_POST['transacoes'] ?? '[]', true);

if (empty($transacoes)) {
    $response['message'] = 'Nenhuma transação para importar';
    echo json_encode($response);
    exit();
}

try {
    $salvas = 0;
    $duplicadas = 0;
    $erros = 0;
    
    foreach ($transacoes as $transacao) {
        try {
            // Verificar se já existe transação similar
            $stmt = $pdo->prepare("
                SELECT id FROM transacoes 
                WHERE id_usuario = ? 
                AND data_transacao = ? 
                AND valor = ? 
                AND descricao LIKE ?
            ");
            $stmt->execute([
                $userId,
                $transacao['data'],
                $transacao['valor'],
                '%' . substr($transacao['descricao'], 0, 20) . '%'
            ]);
            
            if ($stmt->fetch()) {
                $duplicadas++;
                continue;
            }
            
            // Inserir nova transação
            $stmt = $pdo->prepare("
                INSERT INTO transacoes (
                    id_usuario, 
                    data_transacao, 
                    descricao, 
                    valor, 
                    tipo, 
                    data_criacao
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $transacao['data'],
                $transacao['descricao'],
                $transacao['valor'],
                $transacao['tipo']
            ]);
            
            $salvas++;
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar transação: " . $e->getMessage());
            $erros++;
        }
    }
    
    // Resposta de sucesso
    $response['success'] = true;
    $response['message'] = "Importação concluída! {$salvas} transações importadas, {$duplicadas} duplicadas ignoradas";
    
    if ($erros > 0) {
        $response['message'] .= ", {$erros} erros encontrados";
    }
    
    $response['dados'] = [
        'salvas' => $salvas,
        'duplicadas' => $duplicadas,
        'erros' => $erros,
        'total' => count($transacoes)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erro na importação: " . $e->getMessage());
    $response['message'] = 'Erro interno: ' . $e->getMessage();
    echo json_encode($response);
}
?>
