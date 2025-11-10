<?php
/**
 * Sistema de Rate Limiting para API Gemini
 * Controla o número de requisições por usuário para evitar erro 429
 */

class RateLimiter {
    private $pdo;
    private $maxRequestsPerMinute = 5;  // Máximo de 5 requisições por minuto
    private $maxRequestsPerHour = 30;   // Máximo de 30 requisições por hora
    private $retryAfterSeconds = 60;    // Tempo mínimo entre requisições (segundos)

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->createTableIfNotExists();
    }

    /**
     * Cria a tabela de controle de rate limiting se não existir
     */
    private function createTableIfNotExists() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rate_limit_ia (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_usuario INT NOT NULL,
                    tipo_requisicao VARCHAR(50) NOT NULL DEFAULT 'gemini',
                    timestamp_request DATETIME DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45),
                    INDEX idx_usuario_timestamp (id_usuario, timestamp_request),
                    INDEX idx_timestamp (timestamp_request),
                    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            // Tabela pode já existir, ignorar erro
            error_log("Rate Limiter: " . $e->getMessage());
        }
    }

    /**
     * Verifica se o usuário pode fazer uma requisição
     * @param int $userId ID do usuário
     * @param string $tipo Tipo de requisição (gemini, etc)
     * @return array ['allowed' => bool, 'retry_after' => int, 'message' => string]
     */
    public function checkRateLimit(int $userId, string $tipo = 'gemini'): array {
        try {
            $now = date('Y-m-d H:i:s');
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // Limpar requisições antigas (mais de 1 hora)
            $this->cleanOldRequests();

            // Verificar requisições nos últimos 60 segundos
            $stmtMinute = $this->pdo->prepare("
                SELECT COUNT(*) as count, MAX(timestamp_request) as last_request
                FROM rate_limit_ia
                WHERE id_usuario = ? AND tipo_requisicao = ?
                AND timestamp_request >= DATE_SUB(?, INTERVAL 60 SECOND)
            ");
            $stmtMinute->execute([$userId, $tipo, $now]);
            $minuteData = $stmtMinute->fetch(PDO::FETCH_ASSOC);
            $requestsLastMinute = (int)$minuteData['count'];
            $lastRequest = $minuteData['last_request'];

            // Verificar requisições na última hora
            $stmtHour = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM rate_limit_ia
                WHERE id_usuario = ? AND tipo_requisicao = ?
                AND timestamp_request >= DATE_SUB(?, INTERVAL 1 HOUR)
            ");
            $stmtHour->execute([$userId, $tipo, $now]);
            $requestsLastHour = (int)$stmtHour->fetchColumn();

            // Verificar limite por minuto
            if ($requestsLastMinute >= $this->maxRequestsPerMinute) {
                $lastRequestTime = strtotime($lastRequest);
                $currentTime = time();
                $secondsSinceLastRequest = $currentTime - $lastRequestTime;
                $retryAfter = max(0, $this->retryAfterSeconds - $secondsSinceLastRequest);

                return [
                    'allowed' => false,
                    'retry_after' => $retryAfter,
                    'message' => "Você atingiu o limite de {$this->maxRequestsPerMinute} requisições por minuto. Aguarde " . ceil($retryAfter) . " segundo(s) antes de tentar novamente.",
                    'limit_type' => 'minute'
                ];
            }

            // Verificar limite por hora
            if ($requestsLastHour >= $this->maxRequestsPerHour) {
                return [
                    'allowed' => false,
                    'retry_after' => 3600, // 1 hora
                    'message' => "Você atingiu o limite de {$this->maxRequestsPerHour} requisições por hora. Aguarde alguns minutos antes de tentar novamente.",
                    'limit_type' => 'hour'
                ];
            }

            // Verificar tempo mínimo entre requisições
            if ($lastRequest) {
                $lastRequestTime = strtotime($lastRequest);
                $currentTime = time();
                $secondsSinceLastRequest = $currentTime - $lastRequestTime;

                if ($secondsSinceLastRequest < $this->retryAfterSeconds) {
                    $retryAfter = $this->retryAfterSeconds - $secondsSinceLastRequest;
                    return [
                        'allowed' => false,
                        'retry_after' => $retryAfter,
                        'message' => "Aguarde " . ceil($retryAfter) . " segundo(s) antes de fazer outra requisição.",
                        'limit_type' => 'cooldown'
                    ];
                }
            }

            // Registra a requisição
            $this->recordRequest($userId, $tipo, $ipAddress);

            return [
                'allowed' => true,
                'retry_after' => 0,
                'message' => '',
                'limit_type' => null
            ];

        } catch (PDOException $e) {
            error_log("Rate Limiter Error: " . $e->getMessage());
            // Em caso de erro, permite a requisição para não bloquear o usuário
            return [
                'allowed' => true,
                'retry_after' => 0,
                'message' => '',
                'limit_type' => null
            ];
        }
    }

    /**
     * Registra uma requisição no banco de dados
     */
    private function recordRequest(int $userId, string $tipo, string $ipAddress) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limit_ia (id_usuario, tipo_requisicao, ip_address)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $tipo, $ipAddress]);
        } catch (PDOException $e) {
            error_log("Rate Limiter Record Error: " . $e->getMessage());
        }
    }

    /**
     * Limpa requisições antigas (mais de 1 hora)
     */
    private function cleanOldRequests() {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM rate_limit_ia
                WHERE timestamp_request < DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Rate Limiter Clean Error: " . $e->getMessage());
        }
    }

    /**
     * Obtém estatísticas de uso para um usuário
     */
    public function getUsageStats(int $userId, string $tipo = 'gemini'): array {
        try {
            $now = date('Y-m-d H:i:s');

            // Requisições na última hora
            $stmtHour = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM rate_limit_ia
                WHERE id_usuario = ? AND tipo_requisicao = ?
                AND timestamp_request >= DATE_SUB(?, INTERVAL 1 HOUR)
            ");
            $stmtHour->execute([$userId, $tipo, $now]);
            $requestsLastHour = (int)$stmtHour->fetchColumn();

            // Requisições no último minuto
            $stmtMinute = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM rate_limit_ia
                WHERE id_usuario = ? AND tipo_requisicao = ?
                AND timestamp_request >= DATE_SUB(?, INTERVAL 60 SECOND)
            ");
            $stmtMinute->execute([$userId, $tipo, $now]);
            $requestsLastMinute = (int)$stmtMinute->fetchColumn();

            return [
                'requests_last_minute' => $requestsLastMinute,
                'requests_last_hour' => $requestsLastHour,
                'limit_per_minute' => $this->maxRequestsPerMinute,
                'limit_per_hour' => $this->maxRequestsPerHour,
                'remaining_minute' => max(0, $this->maxRequestsPerMinute - $requestsLastMinute),
                'remaining_hour' => max(0, $this->maxRequestsPerHour - $requestsLastHour)
            ];
        } catch (PDOException $e) {
            error_log("Rate Limiter Stats Error: " . $e->getMessage());
            return [];
        }
    }
}
?>

