<?php
// duplicar_nota_curso.php - Duplicar uma nota existente
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$nota_id = $input['id'] ?? 0;

if (empty($nota_id)) {
    echo json_encode(['success' => false, 'message' => 'ID da nota não fornecido']);
    exit;
}

try {
    // Buscar nota original
    $stmt_original = $pdo->prepare("SELECT titulo, conteudo, categoria, id_curso, prioridade 
                                   FROM notas_cursos 
                                   WHERE id = ? AND id_usuario = ?");
    $stmt_original->execute([$nota_id, $userId]);
    $nota_original = $stmt_original->fetch(PDO::FETCH_ASSOC);
    
    if (!$nota_original) {
        echo json_encode(['success' => false, 'message' => 'Nota não encontrada']);
        exit;
    }
    
    // Criar cópia
    $sql = "INSERT INTO notas_cursos (id_usuario, titulo, conteudo, categoria, id_curso, prioridade, data_criacao) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $novo_titulo = $nota_original['titulo'] . ' (Cópia)';
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $userId,
        $novo_titulo,
        $nota_original['conteudo'],
        $nota_original['categoria'],
        $nota_original['id_curso'],
        $nota_original['prioridade']
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Nota duplicada com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao duplicar nota']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>

