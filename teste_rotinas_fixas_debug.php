<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

$debug = [];

try {
    // Verificar conexão
    $debug['conexao'] = 'OK';
    
    // Verificar se as tabelas existem
    $tabelas = ['rotinas_fixas', 'rotina_controle_diario'];
    foreach ($tabelas as $tabela) {
        try {
            $pdo->query("SELECT 1 FROM $tabela LIMIT 1");
            $debug["tabela_$tabela"] = 'EXISTE';
        } catch (PDOException $e) {
            $debug["tabela_$tabela"] = 'NÃO EXISTE: ' . $e->getMessage();
        }
    }
    
    // Verificar estrutura das tabelas
    try {
        $stmt = $pdo->query("DESCRIBE rotinas_fixas");
        $debug['estrutura_rotinas_fixas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $debug['estrutura_rotinas_fixas'] = 'ERRO: ' . $e->getMessage();
    }
    
    try {
        $stmt = $pdo->query("DESCRIBE rotina_controle_diario");
        $debug['estrutura_controle_diario'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $debug['estrutura_controle_diario'] = 'ERRO: ' . $e->getMessage();
    }
    
    // Verificar se há dados
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM rotinas_fixas WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $debug['rotinas_usuario'] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $debug['rotinas_usuario'] = 'ERRO: ' . $e->getMessage();
        }
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM rotina_controle_diario WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $debug['controles_usuario'] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $debug['controles_usuario'] = 'ERRO: ' . $e->getMessage();
        }
    } else {
        $debug['usuario'] = 'NÃO LOGADO';
    }
    
    // Teste de inserção simples
    try {
        $dataHoje = date('Y-m-d');
        $debug['data_hoje'] = $dataHoje;
        
        // Verificar se conseguimos fazer uma query simples
        $stmt = $pdo->prepare("SELECT NOW() as agora");
        $stmt->execute();
        $debug['query_teste'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $debug['query_teste'] = 'ERRO: ' . $e->getMessage();
    }
    
} catch (Exception $e) {
    $debug['erro_geral'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
