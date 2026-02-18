<?php
// /atualizar_meta.php (Versão Moderna com AJAX e Retorno de Dados)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. VERIFICAÇÃO DE SEGURANÇA E VALIDAÇÃO ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$metaId = $_POST['meta_id'] ?? 0;
$valor_adicionado = $_POST['valor_adicionado'] ?? 0;

if (empty($metaId) || !is_numeric($valor_adicionado) || $valor_adicionado <= 0) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'O ID da meta e um valor válido maior que zero são obrigatórios.';
    echo json_encode($response);
    exit();
}

try {
    // --- 2. ATUALIZAÇÃO E BUSCA DENTRO DE UMA TRANSAÇÃO SEGURA ---
    $pdo->beginTransaction();

    // Primeiro, executa o UPDATE. A cláusula 'AND id_usuario' garante a segurança.
    $stmt_update = $pdo->prepare(
        "UPDATE metas_compra SET valor_poupado = valor_poupado + ? WHERE id = ? AND id_usuario = ?"
    );
    $stmt_update->execute([$valor_adicionado, $metaId, $userId]);

    // Verifica se a meta realmente existia e foi atualizada
    if ($stmt_update->rowCount() === 0) {
        $pdo->rollBack(); // Desfaz a transação
        http_response_code(404); // Não Encontrado
        $response['message'] = 'Meta não encontrada ou você não tem permissão para alterá-la.';
        echo json_encode($response);
        exit();
    }

    // Em seguida, busca os dados atualizados para retornar ao frontend
    $stmt_select = $pdo->prepare(
        "SELECT valor_poupado, valor_total FROM metas_compra WHERE id = ?"
    );
    $stmt_select->execute([$metaId]);
    $meta_atualizada = $stmt_select->fetch(PDO::FETCH_ASSOC);

    $pdo->commit(); // Confirma as alterações no banco de dados

    // --- 3. RESPOSTA DE SUCESSO COM OS DADOS ATUALIZADOS ---
    $response['success'] = true;
    $response['message'] = 'Valor adicionado à meta com sucesso!';
    // Retorna os dados atualizados para o JavaScript poder atualizar a interface
    $response['meta'] = [
        'id'            => (int)$metaId,
        'valor_poupado' => (float)$meta_atualizada['valor_poupado'],
        'valor_total'   => (float)$meta_atualizada['valor_total']
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao atualizar a meta.';
    // error_log($e->getMessage()); // Em produção
    echo json_encode($response);
}
?>