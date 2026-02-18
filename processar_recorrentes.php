<?php
// /processar_recorrentes.php (Versão Profissional e Robusta)
// ESTE SCRIPT DEVE SER EXECUTADO VIA CRON JOB DIARIAMENTE

// Garante que a data e hora estarão corretas, independente do servidor
date_default_timezone_set('America/Sao_Paulo');

// Inclui a conexão com o banco de dados.
require_once __DIR__ . '/includes/db_connect.php';

// --- INÍCIO DO SCRIPT ---
$log = "--------------------------------------------------\n";
$log .= "Iniciando processamento de recorrências em: " . date('Y-m-d H:i:s') . "\n";

$hoje = date('Y-m-d');
$dia_do_mes_hoje = (int)date('j');
$ultimo_dia_do_mes = (int)date('t');
$mes_ano_hoje = date('Y-m');

try {
    // --- LÓGICA INTELIGENTE PARA O DIA DE EXECUÇÃO ---
    // Se hoje for o último dia do mês, o script também processará as recorrências agendadas
    // para dias que não existem neste mês (ex: agendamentos do dia 31 em Fevereiro).
    $dias_para_processar = [$dia_do_mes_hoje];
    if ($dia_do_mes_hoje === $ultimo_dia_do_mes) {
        for ($i = $ultimo_dia_do_mes + 1; $i <= 31; $i++) {
            $dias_para_processar[] = $i;
        }
    }
    $placeholders_dias = implode(',', array_fill(0, count($dias_para_processar), '?'));

    // --- BUSCA DAS RECORRÊNCIAS ---
    $sql = "SELECT tr.*, c.tipo as tipo_categoria 
            FROM transacoes_recorrentes tr
            JOIN categorias c ON tr.id_categoria = c.id
            WHERE tr.frequencia = 'Mensal' 
            AND tr.dia_execucao IN ($placeholders_dias)
            AND (tr.ultima_execucao IS NULL OR DATE_FORMAT(tr.ultima_execucao, '%Y-%m') < ?)";
            
    $params = array_merge($dias_para_processar, [$mes_ano_hoje]);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recorrencias_para_lancar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($recorrencias_para_lancar)) {
        $log .= "Nenhuma recorrência para lançar hoje.\n";
        echo $log; // Imprime o log
        exit;
    }

    $log .= "Encontradas " . count($recorrencias_para_lancar) . " recorrências para lançar.\n";

    // --- PROCESSAMENTO EM LOTE COM TRANSAÇÃO ÚNICA ---
    // Prepara as queries uma vez, fora do loop
    $sql_insert = "INSERT INTO transacoes (id_usuario, id_categoria, descricao, valor, tipo, data_transacao) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = $pdo->prepare($sql_insert);

    $sql_update = "UPDATE transacoes_recorrentes SET ultima_execucao = ? WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    
    // Inicia a transação ANTES do loop. Ou tudo funciona, ou nada é salvo.
    $pdo->beginTransaction();

    foreach ($recorrencias_para_lancar as $rec) {
        // 1. Insere na tabela principal de transações
        $stmt_insert->execute([
            $rec['id_usuario'],
            $rec['id_categoria'],
            $rec['descricao'],
            $rec['valor'],
            $rec['tipo_categoria'],
            $hoje
        ]);

        // 2. Atualiza a data da última execução na tabela de recorrências
        $stmt_update->execute([$hoje, $rec['id']]);

        $log .= "  - Lançada com sucesso: " . htmlspecialchars($rec['descricao']) . "\n";
    }
    
    // Se o loop terminou sem erros, confirma todas as operações no banco de uma só vez.
    $pdo->commit();

    $log .= "Processamento finalizado com sucesso.\n";

} catch (PDOException $e) {
    // Se qualquer erro ocorrer, desfaz TODAS as operações do lote.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $log .= "\nERRO CRÍTICO no processamento do CRON: " . $e->getMessage() . "\n";
    // Em produção, você logaria o erro em um arquivo e talvez enviaria um e-mail de alerta.
    // error_log($log);
}

// Imprime o log de execução, que pode ser salvo em um arquivo pelo Cron Job
echo nl2br($log); // nl2br para exibir quebras de linha se você testar no navegador
?>