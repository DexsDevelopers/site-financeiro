<?php
// src/Controllers/DashboardController.php - Controller do Dashboard

require_once 'BaseController.php';
require_once '../Services/QueryOptimizer.php';
require_once '../Services/CacheService.php';

class DashboardController extends BaseController {
    private $queryOptimizer;
    private $cache;
    
    public function __construct($pdo, $userId, $userName) {
        parent::__construct($pdo, $userId, $userName);
        $this->cache = new CacheService($pdo);
        $this->queryOptimizer = new QueryOptimizer($pdo, $this->cache);
    }
    
    /**
     * Obter dados do dashboard
     */
    public function getDashboardData($mes = null, $ano = null) {
        $mes = $mes ?? date('n');
        $ano = $ano ?? date('Y');
        
        try {
            // Dados principais
            $dashboardData = $this->queryOptimizer->getDashboardData($this->userId, $mes, $ano);
            
            // Dados dos gráficos
            $chartData = $this->queryOptimizer->getChartData($this->userId, $mes, $ano);
            
            // Dados das tarefas
            $tasksData = $this->queryOptimizer->getTasksData($this->userId);
            
            // Estatísticas
            $statsData = $this->queryOptimizer->getStatsData($this->userId);
            
            return [
                'success' => true,
                'data' => [
                    'dashboard' => $dashboardData,
                    'charts' => $chartData,
                    'tasks' => $tasksData,
                    'stats' => $statsData
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Erro no dashboard: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao carregar dados do dashboard'
            ];
        }
    }
    
    /**
     * Atualizar status da tarefa
     */
    public function updateTaskStatus($taskId, $status) {
        // Validar entrada
        $errors = $this->validateInput([
            'taskId' => $taskId,
            'status' => $status
        ], [
            'taskId' => ['required' => true, 'type' => 'numeric'],
            'status' => ['required' => true, 'type' => 'string']
        ]);
        
        if (!empty($errors)) {
            return $this->jsonResponse(false, 'Dados inválidos', $errors, 400);
        }
        
        try {
            $sql = "UPDATE tarefas SET status = ? WHERE id = ? AND id_usuario = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$status, $taskId, $this->userId]);
            
            if ($result) {
                // Limpar cache do usuário
                $this->cache->clearUserCache($this->userId);
                
                return $this->jsonResponse(true, 'Tarefa atualizada com sucesso');
            } else {
                return $this->jsonResponse(false, 'Erro ao atualizar tarefa');
            }
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar tarefa: " . $e->getMessage());
            return $this->jsonResponse(false, 'Erro interno do servidor');
        }
    }
    
    /**
     * Buscar dados para gráficos
     */
    public function getChartData($mes, $ano) {
        try {
            $data = $this->queryOptimizer->getChartData($this->userId, $mes, $ano);
            return $this->jsonResponse(true, 'Dados carregados', $data);
        } catch (Exception $e) {
            error_log("Erro ao carregar gráficos: " . $e->getMessage());
            return $this->jsonResponse(false, 'Erro ao carregar dados dos gráficos');
        }
    }
    
    /**
     * Buscar estatísticas
     */
    public function getStats() {
        try {
            $data = $this->queryOptimizer->getStatsData($this->userId);
            return $this->jsonResponse(true, 'Estatísticas carregadas', $data);
        } catch (Exception $e) {
            error_log("Erro ao carregar estatísticas: " . $e->getMessage());
            return $this->jsonResponse(false, 'Erro ao carregar estatísticas');
        }
    }
}
