<?php
// src/Services/QueryOptimizer.php - Otimizador de Queries

class QueryOptimizer {
    private $pdo;
    private $cache;
    
    public function __construct($pdo, $cache) {
        $this->pdo = $pdo;
        $this->cache = $cache;
    }
    
    /**
     * Dashboard otimizado
     */
    public function getDashboardData($userId, $mes, $ano) {
        $cacheKey = "dashboard_{$userId}_{$mes}_{$ano}";
        
        return $this->cache->getUserCache($userId, $cacheKey, function() use ($userId, $mes, $ano) {
            // Query única para buscar todos os dados do dashboard
            $sql = "
                SELECT 
                    -- Resumo financeiro
                    SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as total_receitas,
                    SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as total_despesas,
                    
                    -- Estatísticas de tarefas
                    (SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND status = 'pendente') as tarefas_pendentes,
                    (SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND status = 'concluida' AND DATE(data_criacao) = CURDATE()) as tarefas_hoje_concluidas,
                    (SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND DATE(data_criacao) = CURDATE()) as tarefas_hoje_total,
                    
                    -- Últimos lançamentos
                    (SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', t.id,
                            'descricao', t.descricao,
                            'valor', t.valor,
                            'tipo', t.tipo,
                            'data_transacao', t.data_transacao,
                            'categoria', c.nome
                        )
                    ) FROM transacoes t 
                    LEFT JOIN categorias c ON t.id_categoria = c.id 
                    WHERE t.id_usuario = ? 
                    ORDER BY t.data_transacao DESC 
                    LIMIT 5) as ultimos_lancamentos
                    
                FROM transacoes 
                WHERE id_usuario = ? 
                AND MONTH(data_transacao) = ? 
                AND YEAR(data_transacao) = ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $userId, $userId, $userId, $userId, $mes, $ano]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Processar dados
            $result['total_receitas'] = (float) ($result['total_receitas'] ?? 0);
            $result['total_despesas'] = (float) ($result['total_despesas'] ?? 0);
            $result['saldo_mes'] = $result['total_receitas'] - $result['total_despesas'];
            $result['ultimos_lancamentos'] = json_decode($result['ultimos_lancamentos'] ?? '[]', true);
            
            return $result;
        }, 300); // Cache por 5 minutos
    }
    
    /**
     * Gráficos otimizados
     */
    public function getChartData($userId, $mes, $ano) {
        $cacheKey = "charts_{$userId}_{$mes}_{$ano}";
        
        return $this->cache->getUserCache($userId, $cacheKey, function() use ($userId, $mes, $ano) {
            // Gráfico de barras (despesas diárias)
            $sqlBar = "
                SELECT 
                    DAY(data_transacao) as dia,
                    SUM(valor) as total_gasto
                FROM transacoes 
                WHERE id_usuario = ? 
                AND tipo = 'despesa' 
                AND MONTH(data_transacao) = ? 
                AND YEAR(data_transacao) = ? 
                GROUP BY DAY(data_transacao) 
                ORDER BY dia ASC
            ";
            
            $stmtBar = $this->pdo->prepare($sqlBar);
            $stmtBar->execute([$userId, $mes, $ano]);
            $barData = $stmtBar->fetchAll(PDO::FETCH_ASSOC);
            
            // Gráfico de pizza (categorias)
            $sqlPie = "
                SELECT 
                    c.nome as categoria,
                    SUM(t.valor) as total_categoria
                FROM transacoes t 
                JOIN categorias c ON t.id_categoria = c.id 
                WHERE t.id_usuario = ? 
                AND t.tipo = 'despesa' 
                AND MONTH(t.data_transacao) = ? 
                AND YEAR(t.data_transacao) = ? 
                GROUP BY t.id_categoria, c.nome 
                ORDER BY total_categoria DESC
            ";
            
            $stmtPie = $this->pdo->prepare($sqlPie);
            $stmtPie->execute([$userId, $mes, $ano]);
            $pieData = $stmtPie->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'bar' => $barData,
                'pie' => $pieData
            ];
        }, 600); // Cache por 10 minutos
    }
    
    /**
     * Tarefas otimizadas
     */
    public function getTasksData($userId) {
        $cacheKey = "tasks_{$userId}";
        
        return $this->cache->getUserCache($userId, $cacheKey, function() use ($userId) {
            // Query única para todas as tarefas
            $sql = "
                SELECT 
                    *,
                    CASE 
                        WHEN status = 'concluida' THEN 1 
                        ELSE 0 
                    END as is_concluida,
                    CASE 
                        WHEN DATE(data_limite) = CURDATE() THEN 1 
                        ELSE 0 
                    END as is_hoje
                FROM tarefas 
                WHERE id_usuario = ? 
                ORDER BY 
                    FIELD(prioridade, 'Alta', 'Média', 'Baixa'),
                    ordem ASC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Separar por status
            $pendentes = array_filter($tasks, function($task) {
                return $task['status'] === 'pendente';
            });
            
            $concluidas = array_filter($tasks, function($task) {
                return $task['status'] === 'concluida';
            });
            
            return [
                'pendentes' => array_values($pendentes),
                'concluidas' => array_values($concluidas),
                'total' => count($tasks),
                'pendentes_count' => count($pendentes),
                'concluidas_count' => count($concluidas)
            ];
        }, 180); // Cache por 3 minutos
    }
    
    /**
     * Estatísticas otimizadas
     */
    public function getStatsData($userId) {
        $cacheKey = "stats_{$userId}";
        
        return $this->cache->getUserCache($userId, $cacheKey, function() use ($userId) {
            $sql = "
                SELECT 
                    -- Tarefas
                    (SELECT COUNT(*) FROM tarefas WHERE id_usuario = ?) as total_tarefas,
                    (SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND status = 'concluida') as tarefas_concluidas,
                    (SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND status = 'pendente') as tarefas_pendentes,
                    
                    -- Transações
                    (SELECT COUNT(*) FROM transacoes WHERE id_usuario = ?) as total_transacoes,
                    (SELECT SUM(valor) FROM transacoes WHERE id_usuario = ? AND tipo = 'receita') as total_receitas,
                    (SELECT SUM(valor) FROM transacoes WHERE id_usuario = ? AND tipo = 'despesa') as total_despesas,
                    
                    -- Cursos
                    (SELECT COUNT(*) FROM cursos WHERE id_usuario = ?) as total_cursos,
                    (SELECT COUNT(*) FROM cursos WHERE id_usuario = ? AND status = 'concluido') as cursos_concluidos
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular percentuais
            $result['tarefas_progresso'] = $result['total_tarefas'] > 0 ? 
                round(($result['tarefas_concluidas'] / $result['total_tarefas']) * 100, 2) : 0;
            
            $result['cursos_progresso'] = $result['total_cursos'] > 0 ? 
                round(($result['cursos_concluidos'] / $result['total_cursos']) * 100, 2) : 0;
            
            return $result;
        }, 600); // Cache por 10 minutos
    }
    
    /**
     * Busca otimizada com índices
     */
    public function searchOptimized($userId, $query, $type = 'all') {
        $cacheKey = "search_{$userId}_" . md5($query . $type);
        
        return $this->cache->getUserCache($userId, $cacheKey, function() use ($userId, $query, $type) {
            $results = [];
            
            if ($type === 'all' || $type === 'tarefas') {
                $sql = "
                    SELECT 
                        'tarefa' as tipo,
                        id,
                        descricao as titulo,
                        descricao as conteudo,
                        data_criacao,
                        status
                    FROM tarefas 
                    WHERE id_usuario = ? 
                    AND (descricao LIKE ? OR observacoes LIKE ?)
                    ORDER BY data_criacao DESC
                    LIMIT 10
                ";
                
                $stmt = $this->pdo->prepare($sql);
                $searchTerm = "%{$query}%";
                $stmt->execute([$userId, $searchTerm, $searchTerm]);
                $results['tarefas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if ($type === 'all' || $type === 'transacoes') {
                $sql = "
                    SELECT 
                        'transacao' as tipo,
                        t.id,
                        t.descricao as titulo,
                        CONCAT('R$ ', FORMAT(t.valor, 2, 'pt_BR')) as conteudo,
                        t.data_transacao as data_criacao,
                        t.tipo as status
                    FROM transacoes t 
                    WHERE t.id_usuario = ? 
                    AND t.descricao LIKE ?
                    ORDER BY t.data_transacao DESC
                    LIMIT 10
                ";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$userId, "%{$query}%"]);
                $results['transacoes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $results;
        }, 300); // Cache por 5 minutos
    }
}
