<?php
// includes/rotina_fixa_functions.php - Funções para rotinas fixas

/**
 * Atualizar status de rotina fixa
 */
function atualizarStatusRotinaFixa($pdo, $userId, $rotinaId, $status, $observacoes = null) {
    try {
        $dataHoje = date("Y-m-d");
        $horario = $status === "concluido" ? date("H:i:s") : null;
        
        $stmt = $pdo->prepare("
            UPDATE rotina_controle_diario 
            SET status = ?, horario_execucao = ?, observacoes = ?
            WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
        ");
        
        return $stmt->execute([$status, $horario, $observacoes, $userId, $rotinaId, $dataHoje]);
    } catch (PDOException $e) {
        error_log("Erro ao atualizar rotina fixa: " . $e->getMessage());
        return false;
    }
}

/**
 * Adicionar nova rotina fixa
 */
function adicionarRotinaFixa($pdo, $userId, $nome, $horario = null, $descricao = null, $ordem = 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rotinas_fixas (id_usuario, nome, horario_sugerido, descricao, ordem, ativo) 
            VALUES (?, ?, ?, ?, ?, TRUE)
        ");
        
        if ($stmt->execute([$userId, $nome, $horario, $descricao, $ordem])) {
            $idRotina = $pdo->lastInsertId();
            
            // Criar controle para hoje
            $dataHoje = date("Y-m-d");
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, 'pendente')
            ");
            $stmt->execute([$userId, $idRotina, $dataHoje]);
            
            return $idRotina;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Erro ao adicionar rotina fixa: " . $e->getMessage());
        return false;
    }
}

/**
 * Remover rotina fixa
 */
function removerRotinaFixa($pdo, $userId, $rotinaId) {
    try {
        // Desativar rotina fixa
        $stmt = $pdo->prepare("UPDATE rotinas_fixas SET ativo = FALSE WHERE id = ? AND id_usuario = ?");
        return $stmt->execute([$rotinaId, $userId]);
    } catch (PDOException $e) {
        error_log("Erro ao remover rotina fixa: " . $e->getMessage());
        return false;
    }
}

/**
 * Ativar/Desativar rotina fixa
 */
function toggleRotinaFixa($pdo, $userId, $rotinaId, $ativo = true) {
    try {
        $stmt = $pdo->prepare("UPDATE rotinas_fixas SET ativo = ? WHERE id = ? AND id_usuario = ?");
        return $stmt->execute([$ativo, $rotinaId, $userId]);
    } catch (PDOException $e) {
        error_log("Erro ao alterar status da rotina fixa: " . $e->getMessage());
        return false;
    }
}

/**
 * Buscar rotinas fixas do usuário
 */
function buscarRotinasFixas($pdo, $userId, $data = null) {
    try {
        $data = $data ?: date("Y-m-d");
        
        $stmt = $pdo->prepare("
            SELECT rf.*, 
                   rcd.status as status_hoje,
                   rcd.horario_execucao,
                   rcd.observacoes
            FROM rotinas_fixas rf
            LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa 
                AND rcd.id_usuario = rf.id_usuario 
                AND rcd.data_execucao = ?
            WHERE rf.id_usuario = ? AND rf.ativo = TRUE
            ORDER BY rf.ordem, rf.horario_sugerido
        ");
        $stmt->execute([$data, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar rotinas fixas: " . $e->getMessage());
        return [];
    }
}

/**
 * Criar controles diários para todas as rotinas fixas
 */
function criarControlesDiarios($pdo, $userId, $data = null) {
    try {
        $data = $data ?: date("Y-m-d");
        
        // Buscar rotinas fixas ativas
        $stmt = $pdo->prepare("
            SELECT id FROM rotinas_fixas 
            WHERE id_usuario = ? AND ativo = TRUE
        ");
        $stmt->execute([$userId]);
        $rotinas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $controles_criados = 0;
        foreach ($rotinas as $rotinaId) {
            // Verificar se já existe controle para esta data
            $stmt = $pdo->prepare("
                SELECT id FROM rotina_controle_diario 
                WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
            ");
            $stmt->execute([$userId, $rotinaId, $data]);
            
            if (!$stmt->fetch()) {
                // Criar controle
                $stmt = $pdo->prepare("
                    INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                    VALUES (?, ?, ?, 'pendente')
                ");
                $stmt->execute([$userId, $rotinaId, $data]);
                $controles_criados++;
            }
        }
        
        return $controles_criados;
    } catch (PDOException $e) {
        error_log("Erro ao criar controles diários: " . $e->getMessage());
        return 0;
    }
}
?>
