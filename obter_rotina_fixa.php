
<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

require_once 'includes/db_connect.php';

try {
    $rotinaId = intval($_GET['id'] ?? 0);
    if (!$rotinaId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da rotina é obrigatório']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nome, horario_sugerido, descricao
        FROM rotinas_fixas
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$rotinaId, $userId]);
    $rotina = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rotina) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rotina não encontrada']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'rotina' => $rotina]);

} catch (PDOException $e) {
    error_log("Erro ao obter rotina fixa: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar rotina']);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar requisição']);
}
?>
