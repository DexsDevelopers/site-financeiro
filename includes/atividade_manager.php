<?php
/**
 * AtividadeManager - Gerencia rastreamento de atividade de usuários
 */

class AtividadeManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo = $pdo;
    }

    /**
     * Registra atividade do usuário
     * 
     * @param int $idUsuario ID do usuário
     * @param string $tipoAcao Tipo da ação (login, logout, pagina_acessada, etc)
     * @param string|null $pagina Página acessada (opcional)
     * @param array|null $dadosExtras Dados extras em formato array (será convertido para JSON)
     * @return bool
     */
    public function registrarAtividade(
        int $idUsuario,
        string $tipoAcao,
        ?string $pagina = null,
        ?array $dadosExtras = null
    ): bool {
        try {
            // Atualizar ultimo_acesso na tabela usuarios
            $stmt = $this->pdo->prepare("
                UPDATE usuarios 
                SET ultimo_acesso = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$idUsuario]);

            // Registrar log detalhado na tabela usuarios_atividade
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $stmt = $this->pdo->prepare("
                INSERT INTO usuarios_atividade 
                (id_usuario, tipo_acao, pagina, ip_address, user_agent, dados_extras) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $dadosExtrasJson = $dadosExtras ? json_encode($dadosExtras, JSON_UNESCAPED_UNICODE) : null;
            
            return $stmt->execute([
                $idUsuario,
                $tipoAcao,
                $pagina,
                $ipAddress,
                $userAgent,
                $dadosExtrasJson
            ]);

        } catch (PDOException $e) {
            error_log("AtividadeManager::registrarAtividade - Erro: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém usuários ativos nas últimas N horas
     * 
     * @param int $horas Número de horas (padrão: 24)
     * @return array
     */
    public function getUsuariosAtivos(int $horas = 24): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
                    u.id,
                    u.usuario,
                    u.nome_completo,
                    u.telefone,
                    u.ultimo_acesso,
                    u.data_criacao,
                    COUNT(ua.id) as total_atividades
                FROM usuarios u
                LEFT JOIN usuarios_atividade ua ON u.id = ua.id_usuario 
                    AND ua.criado_em >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                WHERE u.tipo = 'usuario'
                    AND u.ultimo_acesso >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY u.id, u.usuario, u.nome_completo, u.telefone, u.ultimo_acesso, u.data_criacao
                ORDER BY u.ultimo_acesso DESC
            ");
            $stmt->execute([$horas, $horas]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("AtividadeManager::getUsuariosAtivos - Erro: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Conta usuários ativos nas últimas N horas
     * 
     * @param int $horas Número de horas (padrão: 24)
     * @return int
     */
    public function contarUsuariosAtivos(int $horas = 24): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT id) 
                FROM usuarios 
                WHERE tipo = 'usuario' 
                    AND ultimo_acesso >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$horas]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("AtividadeManager::contarUsuariosAtivos - Erro: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtém estatísticas de atividade
     * 
     * @return array
     */
    public function getEstatisticas(): array {
        try {
            $stats = [];

            // Total de usuários
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'usuario'");
            $stats['total'] = (int)$stmt->fetchColumn();

            // Usuários ativos nas últimas 24h
            $stats['ativos_24h'] = $this->contarUsuariosAtivos(24);

            // Usuários ativos nas últimas 7 dias
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT id) 
                FROM usuarios 
                WHERE tipo = 'usuario' 
                    AND ultimo_acesso >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats['ativos_7d'] = (int)$stmt->fetchColumn();

            // Usuários ativos nas últimas 30 dias
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT id) 
                FROM usuarios 
                WHERE tipo = 'usuario' 
                    AND ultimo_acesso >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stats['ativos_30d'] = (int)$stmt->fetchColumn();

            // Usuários inativos (sem acesso há mais de 30 dias)
            $stmt = $this->pdo->query("
                SELECT COUNT(*) 
                FROM usuarios 
                WHERE tipo = 'usuario' 
                    AND (ultimo_acesso IS NULL OR ultimo_acesso < DATE_SUB(NOW(), INTERVAL 30 DAY))
            ");
            $stats['inativos'] = (int)$stmt->fetchColumn();

            return $stats;
        } catch (PDOException $e) {
            error_log("AtividadeManager::getEstatisticas - Erro: " . $e->getMessage());
            return [
                'total' => 0,
                'ativos_24h' => 0,
                'ativos_7d' => 0,
                'ativos_30d' => 0,
                'inativos' => 0
            ];
        }
    }
}

