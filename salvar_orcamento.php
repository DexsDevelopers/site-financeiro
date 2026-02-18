<?php
// /salvar_orcamento.php (Versão Profissional Refinada)

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. VALIDAÇÕES INICIAIS (FAIL FAST) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- 2. COLETA E VALIDAÇÃO DOS DADOS ---
$userId = $_SESSION['user_id'];
$orcamentos = $_POST['orcamento'] ?? [];
$mes_atual = date('n');
$ano_atual = date('Y');

if (!is_array($orcamentos)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Formato de dados inválido.';
    echo json_encode($response);
    exit();
}

try {
    // --- 3. REFINAMENTO DE SEGURANÇA: Validar se os IDs de Categoria pertencem ao usuário ---
    // Busca todos os IDs de categoria de despesa que são válidos para este usuário
    $stmt_valid_cats = $pdo->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND tipo = 'despesa'");
    $stmt_valid_cats->execute([$userId]);
    $valid_cat_ids = $stmt_valid_cats->fetchAll(PDO::FETCH_COLUMN, 0);

    // Filtra os orçamentos recebidos, mantendo apenas aqueles cujas chaves (ID da categoria) são válidas
    $orcamentos_validados = array_filter(
        $orcamentos,
        fn($cat_id) => in_array($cat_id, $valid_cat_ids),
        ARRAY_FILTER_USE_KEY
    );

    // --- 4. ATUALIZAÇÃO SEGURA COM TRANSAÇÃO ---
    $pdo->beginTransaction();

    $sql = "INSERT INTO orcamentos (id_usuario, id_categoria, mes, ano, valor) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
    $stmt = $pdo->prepare($sql);

    // Itera sobre cada orçamento VALIDADO
    foreach ($orcamentos_validados as $id_categoria => $valor) {
        $valor_numerico = (float)($valor ?: 0); // Converte valor vazio para 0
        // O valor 0 efetivamente remove/zera o orçamento para aquela categoria no mês
        $stmt->execute([$userId, $id_categoria, $mes_atual, $ano_atual, $valor_numerico]);
    }
    
    $pdo->commit();

    // --- 5. RESPOSTA DE SUCESSO ---
    $response['success'] = true;
    $response['message'] = 'Orçamentos salvos com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao salvar orçamentos.';
    // error_log($e->getMessage()); // Em produção
    echo json_encode($response);
}
?>