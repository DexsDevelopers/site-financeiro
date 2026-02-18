<?php
/**
 * Arquivo: includes/tarefas_queries.php
 * Descrição: Queries centralizadas para o sistema de tarefas
 * Uso: Importar em tarefas.php para separar lógica de banco de dados
 */

/**
 * Buscar rotinas fixas do dia
 * @param PDO $pdo
 * @param int $userId
 * @param string $dataHoje
 * @return array
 */
function buscarRotinasFixas(PDO $pdo, int $userId, string $dataHoje): array {
    $stmt = $pdo->prepare("
        SELECT rf.id, rf.nome, rf.horario_sugerido, rf.descricao,
               rcd.status as status_hoje, rcd.id as controle_id
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd 
            ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY COALESCE(rf.horario_sugerido, '23:59:59'), rf.nome
    ");
    $stmt->execute([$dataHoje, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Criar controle diário para rotina (se não existir)
 * @param PDO $pdo
 * @param int $userId
 * @param int $rotinaId
 * @param string $dataHoje
 * @return int ID do novo controle
 */
function criarControleRotinaSeDia(PDO $pdo, int $userId, int $rotinaId, string $dataHoje): int {
    $stmtInsert = $pdo->prepare("
        INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
        VALUES (?, ?, ?, 'pendente')
    ");
    $stmtInsert->execute([$userId, $rotinaId, $dataHoje]);
    return $pdo->lastInsertId();
}

/**
 * Buscar tarefas pendentes
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function buscarTarefasPendentes(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT id, descricao, prioridade, data_limite, status, tempo_estimado, data_criacao
        FROM tarefas 
        WHERE id_usuario = ? AND status = 'pendente'
        ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), COALESCE(ordem, 9999), data_limite
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Buscar subtarefas de tarefas pendentes
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function buscarSubtarefas(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT id, id_tarefa_principal, descricao, status 
        FROM subtarefas 
        WHERE id_tarefa_principal IN (
            SELECT id FROM tarefas WHERE id_usuario = ? AND status = 'pendente'
        )
        ORDER BY id_tarefa_principal, id ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mapear subtarefas por tarefa principal
 * @param array $subtarefas
 * @return array
 */
function mapearSubtarefasPorTarefa(array $subtarefas): array {
    $mapeada = [];
    foreach ($subtarefas as $sub) {
        $tid = (int)$sub['id_tarefa_principal'];
        if (!isset($mapeada[$tid])) {
            $mapeada[$tid] = [];
        }
        $mapeada[$tid][] = $sub;
    }
    return $mapeada;
}

/**
 * Contar estatísticas de tarefas
 * @param array $tarefas
 * @return array
 */
function contarEstatisticas(array $tarefas): array {
    $stats = ['Alta' => 0, 'Média' => 0, 'Baixa' => 0, 'total' => count($tarefas)];
    foreach ($tarefas as $t) {
        $stats[$t['prioridade']]++;
    }
    return $stats;
}

/**
 * Contar rotinas concluídas
 * @param array $rotinas
 * @return int
 */
function contarRotinasConcluidas(array $rotinas): int {
    return count(array_filter($rotinas, fn($r) => $r['status_hoje'] === 'concluido'));
}
?>
