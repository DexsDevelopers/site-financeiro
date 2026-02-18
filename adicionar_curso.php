<?php
// adicionar_curso.php - Adicionar novo curso
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->prepare("SHOW TABLES LIKE 'cursos'");
    $stmt_check->execute();
    $table_exists = $stmt_check->fetch();
    
    if (!$table_exists) {
        // Criar tabela se não existir
        $create_table = "
            CREATE TABLE IF NOT EXISTS cursos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                nome_curso VARCHAR(255) NOT NULL,
                descricao TEXT DEFAULT NULL,
                plataforma VARCHAR(100) DEFAULT NULL,
                link_curso TEXT DEFAULT NULL,
                status ENUM('pendente', 'assistindo', 'concluido') DEFAULT 'pendente',
                progresso INT DEFAULT 0,
                data_inicio DATE DEFAULT NULL,
                data_conclusao DATE DEFAULT NULL,
                prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
                categoria VARCHAR(100) DEFAULT NULL,
                duracao_horas INT DEFAULT NULL,
                valor DECIMAL(10,2) DEFAULT NULL,
                ordem INT DEFAULT 0,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
            )
        ";
        $pdo->exec($create_table);
    }
    
    // Validar dados obrigatórios
    if (empty($_POST['nome_curso'])) {
        echo json_encode(['success' => false, 'message' => 'Nome do curso é obrigatório']);
        exit;
    }
    
    // Preparar dados
    $nome_curso = trim($_POST['nome_curso']);
    $descricao = trim($_POST['descricao'] ?? '');
    $plataforma = trim($_POST['plataforma'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $duracao_horas = !empty($_POST['duracao_horas']) ? (int)$_POST['duracao_horas'] : null;
    $valor = !empty($_POST['valor']) ? (float)$_POST['valor'] : null;
    $link_curso = trim($_POST['link_curso'] ?? '');
    $prioridade = $_POST['prioridade'] ?? 'media';
    
    // Validar prioridade
    if (!in_array($prioridade, ['baixa', 'media', 'alta'])) {
        $prioridade = 'media';
    }
    
    // Inserir curso
    $stmt = $pdo->prepare("
        INSERT INTO cursos (
            id_usuario, nome_curso, descricao, plataforma, categoria, 
            duracao_horas, valor, link_curso, prioridade, status, 
            progresso, data_criacao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 0, NOW())
    ");
    
    $result = $stmt->execute([
        $userId, $nome_curso, $descricao, $plataforma, $categoria,
        $duracao_horas, $valor, $link_curso, $prioridade
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Curso criado com sucesso!',
            'curso_id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar curso']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao criar curso: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
} catch (Exception $e) {
    error_log("Erro geral ao criar curso: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
