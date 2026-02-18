<?php
// salvar_configuracoes_alertas.php

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

$gasto_alto_valor = (float) ($_POST['gasto_alto_valor'] ?? 500);
$gasto_semanal_limite = (float) ($_POST['gasto_semanal_limite'] ?? 2000);
$saldo_negativo_ativo = isset($_POST['saldo_negativo_ativo']) ? 1 : 0;
$metas_progresso_ativo = isset($_POST['metas_progresso_ativo']) ? 1 : 0;
$gastos_recorrentes_ativo = isset($_POST['gastos_recorrentes_ativo']) ? 1 : 0;
$notificacoes_email = isset($_POST['notificacoes_email']) ? 1 : 0;
$notificacoes_push = isset($_POST['notificacoes_push']) ? 1 : 0;

// Validações
if ($gasto_alto_valor < 0) {
    echo json_encode(['success' => false, 'message' => 'Valor para gasto alto deve ser positivo']);
    exit;
}

if ($gasto_semanal_limite < 0) {
    echo json_encode(['success' => false, 'message' => 'Limite semanal deve ser positivo']);
    exit;
}

// Criar tabelas se não existirem
try {
    $sql_create_alertas = "CREATE TABLE IF NOT EXISTS alertas_inteligentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        tipo ENUM('urgente', 'info', 'sucesso', 'aviso') NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        mensagem TEXT NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        lido TINYINT(1) DEFAULT 0,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (id_usuario),
        INDEX idx_tipo (tipo),
        INDEX idx_data (data_criacao)
    )";
    $pdo->exec($sql_create_alertas);
    
    $sql_create_config = "CREATE TABLE IF NOT EXISTS configuracoes_alertas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL UNIQUE,
        gasto_alto_valor DECIMAL(10,2) DEFAULT 500.00,
        gasto_semanal_limite DECIMAL(10,2) DEFAULT 2000.00,
        saldo_negativo_ativo TINYINT(1) DEFAULT 1,
        metas_progresso_ativo TINYINT(1) DEFAULT 1,
        gastos_recorrentes_ativo TINYINT(1) DEFAULT 1,
        notificacoes_email TINYINT(1) DEFAULT 0,
        notificacoes_push TINYINT(1) DEFAULT 1,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_create_config);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tabelas: ' . $e->getMessage()]);
    exit;
}

// Salvar ou atualizar configurações
try {
    $stmt = $pdo->prepare("INSERT INTO configuracoes_alertas (id_usuario, gasto_alto_valor, gasto_semanal_limite, saldo_negativo_ativo, metas_progresso_ativo, gastos_recorrentes_ativo, notificacoes_email, notificacoes_push) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          gasto_alto_valor = VALUES(gasto_alto_valor),
                          gasto_semanal_limite = VALUES(gasto_semanal_limite),
                          saldo_negativo_ativo = VALUES(saldo_negativo_ativo),
                          metas_progresso_ativo = VALUES(metas_progresso_ativo),
                          gastos_recorrentes_ativo = VALUES(gastos_recorrentes_ativo),
                          notificacoes_email = VALUES(notificacoes_email),
                          notificacoes_push = VALUES(notificacoes_push)");
    
    $stmt->execute([$userId, $gasto_alto_valor, $gasto_semanal_limite, $saldo_negativo_ativo, $metas_progresso_ativo, $gastos_recorrentes_ativo, $notificacoes_email, $notificacoes_push]);
    
    echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()]);
}
?>
