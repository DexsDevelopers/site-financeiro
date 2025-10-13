<?php
// src/Models/TarefaModel.php - Model para Tarefas

require_once 'BaseModel.php';

class TarefaModel extends BaseModel {
    protected $table = 'tarefas';
    
    /**
     * Buscar tarefas do usuário
     */
    public function findByUser($userId, $status = null, $orderBy = 'data_criacao DESC') {
        $conditions = ['id_usuario' => $userId];
        
        if ($status) {
            $conditions['status'] = $status;
        }
        
        return $this->findAll($conditions, $orderBy);
    }
    
    /**
     * Estatísticas de tarefas
     */
    public function getStats($userId) {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN DATE(data_criacao) = CURDATE() THEN 1 ELSE 0 END) as hoje,
                SUM(CASE WHEN YEARWEEK(data_criacao, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as semana
            FROM {$this->table} 
            WHERE id_usuario = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Tarefas de hoje
     */
    public function getTodayTasks($userId) {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE id_usuario = ? 
            AND status = 'pendente' 
            AND (DATE(data_limite) = CURDATE() OR data_limite IS NULL)
            ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC
            LIMIT 5
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualizar status
     */
    public function updateStatus($id, $status) {
        $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * Reordenar tarefas
     */
    public function reorder($ids) {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($ids as $order => $id) {
                $sql = "UPDATE {$this->table} SET ordem = ? WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$order, $id]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
