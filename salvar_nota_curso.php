<?php
// salvar_nota_curso.php - Salvar nova anotação
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $titulo = trim($_POST['titulo'] ?? '');
    $conteudo = trim($_POST['conteudo'] ?? '');
    $categoria = $_POST['categoria'] ?? 'outros';
    $id_curso = !empty($_POST['id_curso']) ? (int)$_POST['id_curso'] : null;
    $prioridade = $_POST['prioridade'] ?? 'baixa';
    
    if (empty($titulo) || empty($conteudo)) {
        echo json_encode(['success' => false, 'message' => 'Título e conteúdo são obrigatórios']);
        exit;
    }
    
    // Verificar se o curso pertence ao usuário (se fornecido)
    if ($id_curso) {
        $stmt_curso = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND id_usuario = ?");
        $stmt_curso->execute([$id_curso, $userId]);
        if (!$stmt_curso->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Curso não encontrado']);
            exit;
        }
    }
    
    // Inserir nova nota
    $sql = "INSERT INTO notas_cursos (id_usuario, titulo, conteudo, categoria, id_curso, prioridade, data_criacao) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$userId, $titulo, $conteudo, $categoria, $id_curso, $prioridade]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Anotação salva com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar anotação']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>