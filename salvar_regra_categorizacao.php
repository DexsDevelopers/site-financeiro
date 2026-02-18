<?php
// salvar_regra_categorizacao.php

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$padrao = trim($_POST['padrao'] ?? '');
$id_categoria = (int) ($_POST['id_categoria'] ?? 0);
$tipo = $_POST['tipo'] ?? '';
$prioridade = $_POST['prioridade'] ?? 'Média';
$ativa = isset($_POST['ativa']) ? 1 : 0;

// Validações
if (empty($padrao)) {
    echo json_encode(['success' => false, 'message' => 'Padrão é obrigatório']);
    exit;
}

if ($id_categoria <= 0) {
    echo json_encode(['success' => false, 'message' => 'Categoria é obrigatória']);
    exit;
}

if (!in_array($tipo, ['receita', 'despesa'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit;
}

if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'])) {
    echo json_encode(['success' => false, 'message' => 'Prioridade inválida']);
    exit;
}

// Validar se a expressão regular é válida
try {
    preg_match('/' . $padrao . '/i', 'teste');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Expressão regular inválida: ' . $e->getMessage()]);
    exit;
}

// Verificar se a categoria pertence ao usuário
try {
    $stmt_cat = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt_cat->execute([$id_categoria, $userId]);
    if (!$stmt_cat->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar categoria']);
    exit;
}

// Verificar se já existe uma regra com o mesmo padrão
try {
    $stmt_duplicate = $pdo->prepare("SELECT id FROM regras_categorizacao WHERE padrao = ? AND id_usuario = ?");
    $stmt_duplicate->execute([$padrao, $userId]);
    if ($stmt_duplicate->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma regra com este padrão']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar duplicatas']);
    exit;
}

// Criar tabela se não existir
try {
    $sql_create_table = "CREATE TABLE IF NOT EXISTS regras_categorizacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        padrao VARCHAR(500) NOT NULL,
        id_categoria INT NOT NULL,
        tipo ENUM('receita', 'despesa') NOT NULL,
        prioridade ENUM('Baixa', 'Média', 'Alta') DEFAULT 'Média',
        ativa TINYINT(1) DEFAULT 1,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (id_categoria) REFERENCES categorias(id) ON DELETE CASCADE,
        INDEX idx_usuario (id_usuario),
        INDEX idx_padrao (padrao),
        INDEX idx_ativa (ativa)
    )";
    $pdo->exec($sql_create_table);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tabela: ' . $e->getMessage()]);
    exit;
}

// Inserir nova regra
try {
    $stmt = $pdo->prepare("INSERT INTO regras_categorizacao (id_usuario, padrao, id_categoria, tipo, prioridade, ativa) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $padrao, $id_categoria, $tipo, $prioridade, $ativa]);
    
    echo json_encode(['success' => true, 'message' => 'Regra de categorização criada com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar regra: ' . $e->getMessage()]);
}
?>
