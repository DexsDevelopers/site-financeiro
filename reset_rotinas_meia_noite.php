<?php
// reset_rotinas_meia_noite.php - Script para reset automático das rotinas fixas
// Este script deve ser executado via cron job diariamente à meia-noite
//
// ==============================================================================
// INSTRUÇÕES DE CONFIGURAÇÃO DO CRON JOB:
// ==============================================================================
// 
// 1. Acessar o painel de controle do seu servidor (Hostinger, Bluehost, etc)
// 2. Procurar por "Cron Jobs" ou "Agendador de Tarefas"
// 3. Adicionar um novo cron job com:
//    - Tempo: 00:00 (meia-noite)
//    - Frequência: Diária
//    - Comando: php /caminho/absoluto/seu/dominio/public_html/reset_rotinas_meia_noite.php
//
// 4. Alternativa via terminal (SSH):
//    $ crontab -e
//    Adicionar a linha:
//    0 0 * * * /usr/bin/php /home/usuario/dominio.com.br/public_html/reset_rotinas_meia_noite.php
//
// 5. Verificar execução no arquivo de log:
//    /logs/reset_rotinas_YYYY-MM.log
//
// ==============================================================================

require_once 'includes/db_connect.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando reset automático das rotinas fixas...\n";

try {
    // Buscar todos os usuários
    $stmt = $pdo->query("SELECT id FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $dataHoje = date('Y-m-d');
    $totalUsuarios = count($usuarios);
    $usuariosProcessados = 0;
    
    echo "Encontrados $totalUsuarios usuários para processar...\n";
    
    foreach ($usuarios as $userId) {
        // Buscar rotinas fixas ativas do usuário
        $stmt = $pdo->prepare("
            SELECT id FROM rotinas_fixas 
            WHERE id_usuario = ? AND ativo = TRUE
        ");
        $stmt->execute([$userId]);
        $rotinasAtivas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($rotinasAtivas)) {
            // Verificar se já existem controles para hoje
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM rotina_controle_diario 
                WHERE id_usuario = ? AND data_execucao = ?
            ");
            $stmt->execute([$userId, $dataHoje]);
            $controlesExistentes = $stmt->fetchColumn();
            
            if ($controlesExistentes == 0) {
                // Criar controles para hoje (reset automático)
                $controlesCriados = 0;
                foreach ($rotinasAtivas as $rotinaId) {
                    $stmt = $pdo->prepare("
                        INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                        VALUES (?, ?, ?, 'pendente')
                    ");
                    if ($stmt->execute([$userId, $rotinaId, $dataHoje])) {
                        $controlesCriados++;
                    }
                }
                
                echo "Usuário $userId: Criados $controlesCriados controles para hoje\n";
            } else {
                echo "Usuário $userId: Controles já existem para hoje\n";
            }
        } else {
            echo "Usuário $userId: Nenhuma rotina fixa ativa\n";
        }
        
        $usuariosProcessados++;
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Reset automático concluído!\n";
    echo "Usuários processados: $usuariosProcessados/$totalUsuarios\n";
    
} catch (PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("Erro no reset automático das rotinas: " . $e->getMessage());
}

// Log de execução
$logFile = __DIR__ . '/logs/reset_rotinas_' . date('Y-m') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logEntry = "[" . date('Y-m-d H:i:s') . "] Reset executado - Usuários: $usuariosProcessados/$totalUsuarios\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
?>
