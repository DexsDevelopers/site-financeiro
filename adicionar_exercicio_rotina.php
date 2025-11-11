<?php
// /adicionar_exercicio_rotina.php

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// Função auxiliar para criar tabelas se não existirem
function criarTabelasAcademiaSeNecessario($pdo) {
    try {
        // Tabela exercicios
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS exercicios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                nome_exercicio VARCHAR(100) NOT NULL,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
                UNIQUE KEY unique_exercicio_usuario (id_usuario, nome_exercicio),
                INDEX idx_usuario (id_usuario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela rotina_exercicios
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rotina_exercicios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_rotina_dia INT NOT NULL,
                id_exercicio INT NOT NULL,
                series_sugeridas INT NULL,
                repeticoes_sugeridas VARCHAR(50) NULL,
                ordem INT DEFAULT 0,
                FOREIGN KEY (id_rotina_dia) REFERENCES rotina_dias(id) ON DELETE CASCADE,
                FOREIGN KEY (id_exercicio) REFERENCES exercicios(id) ON DELETE CASCADE,
                INDEX idx_rotina_dia (id_rotina_dia),
                INDEX idx_exercicio (id_exercicio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        // Ignora se já existir
        error_log("Erro ao criar tabelas (pode ser que já existam): " . $e->getMessage());
    }
}

// Criar tabelas se necessário
criarTabelasAcademiaSeNecessario($pdo);

$userId = $_SESSION['user_id'];
$id_rotina_dia = $_POST['id_rotina_dia'] ?? 0;
$nome_exercicio = trim($_POST['nome_exercicio'] ?? '');
$series = $_POST['series_sugeridas'] ?? null;
$repeticoes = $_POST['repeticoes_sugeridas'] ?? null;

if (empty($id_rotina_dia) || empty($nome_exercicio)) {
    http_response_code(400);
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit();
}

try {
    // Verificar se o id_rotina_dia existe e pertence ao usuário
    $stmt_verificar_dia = $pdo->prepare("
        SELECT rd.id 
        FROM rotina_dias rd 
        JOIN rotinas r ON rd.id_rotina = r.id 
        WHERE rd.id = ? AND r.id_usuario = ?
    ");
    $stmt_verificar_dia->execute([$id_rotina_dia, $userId]);
    $dia_valido = $stmt_verificar_dia->fetch();
    
    if (!$dia_valido) {
        http_response_code(400);
        $response['message'] = 'Dia da rotina não encontrado ou não pertence ao usuário.';
        echo json_encode($response);
        exit();
    }
    
    $pdo->beginTransaction();

    // 1. Verifica se o exercício já existe no "dicionário" do usuário.
    $stmt_check = $pdo->prepare("SELECT id FROM exercicios WHERE id_usuario = ? AND nome_exercicio = ?");
    $stmt_check->execute([$userId, $nome_exercicio]);
    $exercicioId = $stmt_check->fetchColumn();

    // 2. Se não existir, cria o exercício.
    if (!$exercicioId) {
        $stmt_create = $pdo->prepare("INSERT INTO exercicios (id_usuario, nome_exercicio) VALUES (?, ?)");
        $stmt_create->execute([$userId, $nome_exercicio]);
        $exercicioId = $pdo->lastInsertId();
    }

    // 3. Verificar se a coluna 'ordem' existe na tabela
    $coluna_ordem_existe = false;
    try {
        $stmt_check_col = $pdo->query("SHOW COLUMNS FROM rotina_exercicios LIKE 'ordem'");
        $coluna_ordem_existe = $stmt_check_col->rowCount() > 0;
    } catch (PDOException $e) {
        // Se der erro ao verificar, assume que não existe
        $coluna_ordem_existe = false;
    }
    
    // 4. Insere o exercício na rotina daquele dia.
    if ($coluna_ordem_existe) {
        // Buscar ordem máxima para adicionar no final
        $stmt_ordem = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) + 1 as nova_ordem FROM rotina_exercicios WHERE id_rotina_dia = ?");
        $stmt_ordem->execute([$id_rotina_dia]);
        $nova_ordem = $stmt_ordem->fetchColumn();
        
        $stmt_insert = $pdo->prepare("INSERT INTO rotina_exercicios (id_rotina_dia, id_exercicio, series_sugeridas, repeticoes_sugeridas, ordem) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->execute([$id_rotina_dia, $exercicioId, $series, $repeticoes, $nova_ordem]);
    } else {
        // Se não tiver coluna ordem, insere sem ela
        $stmt_insert = $pdo->prepare("INSERT INTO rotina_exercicios (id_rotina_dia, id_exercicio, series_sugeridas, repeticoes_sugeridas) VALUES (?, ?, ?, ?)");
        $stmt_insert->execute([$id_rotina_dia, $exercicioId, $series, $repeticoes]);
    }
    $newRotinaExercicioId = $pdo->lastInsertId();

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Exercício adicionado à rotina!';
    $response['exercicio'] = [
        'id' => $newRotinaExercicioId,
        'nome_exercicio' => $nome_exercicio,
        'series_sugeridas' => $series,
        'repeticoes_sugeridas' => $repeticoes
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    $errorMessage = $e->getMessage();
    
    // Log do erro completo para debug
    error_log("Erro ao adicionar exercício: " . $errorMessage);
    
    // Mensagem mais amigável baseada no tipo de erro
    if (strpos($errorMessage, "doesn't exist") !== false || strpos($errorMessage, "Unknown table") !== false) {
        $response['message'] = 'Tabela não encontrada no banco de dados. Execute o script de criação de tabelas.';
    } elseif (strpos($errorMessage, "Column not found") !== false || strpos($errorMessage, "Unknown column") !== false) {
        $response['message'] = 'Estrutura da tabela incompatível. Verifique se todas as colunas existem.';
    } elseif (strpos($errorMessage, "foreign key") !== false) {
        $response['message'] = 'Erro de referência. Verifique se o dia da rotina existe.';
    } else {
        $response['message'] = 'Erro no banco de dados: ' . $errorMessage;
    }
    
    $response['debug'] = $errorMessage; // Para debug em desenvolvimento
    echo json_encode($response);
}
?>