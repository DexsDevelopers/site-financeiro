<?php
// /excluir_exercicio_rotina.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro inesperado.'];
if (!isset($_SESSION['user_id'])) { http_response_code(403); $response['message'] = 'Acesso negado.'; echo json_encode($response); exit(); }
require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$rotinaExercicioId = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($rotinaExercicioId)) { http_response_code(400); $response['message'] = 'ID inválido.'; echo json_encode($response); exit(); }

try {
    $sql = "DELETE re FROM rotina_exercicios re JOIN rotina_dias rd ON re.id_rotina_dia = rd.id JOIN rotinas r ON rd.id_rotina = r.id WHERE re.id = ? AND r.id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$rotinaExercicioId, $userId]);
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Exercício removido da rotina.';
    } else {
        http_response_code(404);
        $response['message'] = 'Exercício não encontrado na rotina.';
    }
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>