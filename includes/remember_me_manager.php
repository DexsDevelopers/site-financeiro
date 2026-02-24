<?php
// includes/remember_me_manager.php - Gerenciador de "Lembrar-me" (Versão Melhorada)

class RememberMeManager {
    private $pdo;
    private $cookie_name = 'remember_token';
    private $cookie_expire = 30; // 30 dias
    private $token_length = 64;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        error_log("RememberMeManager: Instância criada. Cookie expire: " . $this->cookie_expire . " dias");
    }
    
    /**
     * Criar token de lembrança para o usuário
     */
    public function createRememberToken($userId, $userAgent = '', $ipAddress = '') {
        // Gerar token seguro
        $token = bin2hex(random_bytes($this->token_length / 2));
        
        // Data de expiração
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->cookie_expire * 24 * 60 * 60));
        
        try {
            // Inserir token no banco
            $stmt = $this->pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $token, $expiresAt, $userAgent, $ipAddress]);
            
            error_log("RememberMeManager: Token inserido no banco para user_id: $userId. Expira: $expiresAt");
            
            // Definir cookie seguro
            $this->setRememberCookie($token, $expiresAt);
            
            return $token;
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao inserir token - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar token de lembrança
     */
    public function verifyRememberToken($token) {
        if (empty($token)) {
            error_log("RememberMeManager: Token vazio");
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT rt.*, u.id, u.nome_completo as nome, u.email, u.tipo
                FROM remember_tokens rt 
                JOIN usuarios u ON rt.user_id = u.id 
                WHERE rt.token = ? 
                AND rt.is_active = 1 
                AND rt.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                error_log("RememberMeManager: Token válido para usuário: " . $result['nome']);
                // Atualizar último uso
                $this->updateLastUsed($token);
                return $result;
            }
            
            error_log("RememberMeManager: Token não encontrado ou expirado: " . substr($token, 0, 10) . "...");
            return false;
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao verificar token - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fazer login automático com token
     */
    public function autoLogin($token) {
        error_log("RememberMeManager: Iniciando autoLogin com token: " . substr($token, 0, 10) . "...");
        
        $userData = $this->verifyRememberToken($token);
        
        if ($userData) {
            // Iniciar sessão
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Definir dados da sessão
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['user_name'] = $userData['nome'];
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['user_type'] = $userData['tipo'];
            $_SESSION['user'] = [
                'id' => $userData['id'],
                'nome' => $userData['nome'],
                'email' => $userData['email'],
                'tipo' => $userData['tipo']
            ];
            
            error_log("RememberMeManager: Sessão criada para usuário ID: " . $userData['id']);
            
            // Renovar token se necessário (últimos 7 dias)
            $lastUsed = strtotime($userData['last_used_at'] ?? $userData['created_at']);
            if ((time() - $lastUsed) > (7 * 24 * 60 * 60)) {
                error_log("RememberMeManager: Renovando token (últimos 7 dias)");
                $this->renewToken($token);
            }
            
            return true;
        }
        
        error_log("RememberMeManager: autoLogin falhou - dados de usuário não encontrados");
        return false;
    }
    
    /**
     * Renovar token existente
     */
    public function renewToken($oldToken) {
        $userData = $this->verifyRememberToken($oldToken);
        
        if ($userData) {
            error_log("RememberMeManager: Renovando token para user_id: " . $userData['user_id']);
            
            // Criar novo token
            $newToken = $this->createRememberToken(
                $userData['user_id'], 
                $userData['user_agent'], 
                $userData['ip_address']
            );
            
            // Desativar token antigo
            $this->revokeToken($oldToken);
            
            return $newToken;
        }
        
        return false;
    }
    
    /**
     * Revogar token (logout)
     */
    public function revokeToken($token) {
        try {
            $stmt = $this->pdo->prepare("UPDATE remember_tokens SET is_active = 0 WHERE token = ?");
            $stmt->execute([$token]);
            
            error_log("RememberMeManager: Token revogado");
            
            // Remover cookie
            $this->clearRememberCookie();
            
            return true;
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao revogar token - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revogar todos os tokens do usuário
     */
    public function revokeAllUserTokens($userId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE remember_tokens SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $count = $stmt->rowCount();
            error_log("RememberMeManager: " . $count . " token(s) revogados para user_id: $userId");
            
            // Remover cookie
            $this->clearRememberCookie();
            
            return true;
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao revogar todos os tokens - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpar tokens expirados
     */
    public function cleanExpiredTokens() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW() OR is_active = 0");
            $stmt->execute();
            
            $count = $stmt->rowCount();
            error_log("RememberMeManager: " . $count . " token(s) expirado(s) removido(s)");
            
            return $count;
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao limpar tokens - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualizar último uso do token
     */
    private function updateLastUsed($token) {
        try {
            $stmt = $this->pdo->prepare("UPDATE remember_tokens SET last_used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao atualizar last_used_at - " . $e->getMessage());
        }
    }
    
    /**
     * Definir cookie de lembrança
     */
    private function setRememberCookie($token, $expiresAt) {
        $expires = strtotime($expiresAt);
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        error_log("RememberMeManager: Configurando cookie. Expires timestamp: $expires, Secure: " . ($secure ? 'true' : 'false'));
        
        // Tentar definir cookie
        try {
            $result = setcookie(
                $this->cookie_name,
                $token,
                [
                    'expires' => $expires,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            
            if ($result) {
                error_log("RememberMeManager: Cookie definido com sucesso! Token: " . substr($token, 0, 10) . "... Expira em: $expiresAt");
            } else {
                error_log("RememberMeManager: FALHA ao definir cookie! Verifique se headers já foram enviados.");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("RememberMeManager: Exceção ao definir cookie - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpar cookie de lembrança
     */
    private function clearRememberCookie() {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        error_log("RememberMeManager: Limpando cookie remember_token");
        
        setcookie(
            $this->cookie_name,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }
    
    /**
     * Obter token do cookie
     */
    public function getTokenFromCookie() {
        $token = $_COOKIE[$this->cookie_name] ?? null;
        if ($token) {
            error_log("RememberMeManager: Token obtido do cookie: " . substr($token, 0, 10) . "...");
        }
        return $token;
    }
    
    /**
     * Verificar se usuário tem token ativo
     */
    public function hasActiveToken($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM remember_tokens 
                WHERE user_id = ? 
                AND is_active = 1 
                AND expires_at > NOW()
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao verificar token ativo - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter estatísticas dos tokens
     */
    public function getTokenStats($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_tokens,
                    COUNT(CASE WHEN is_active = 1 AND expires_at > NOW() THEN 1 END) as active_tokens,
                    COUNT(CASE WHEN is_active = 0 THEN 1 END) as revoked_tokens,
                    COUNT(CASE WHEN expires_at < NOW() THEN 1 END) as expired_tokens
                FROM remember_tokens 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("RememberMeManager: Erro ao obter estatísticas - " . $e->getMessage());
            return false;
        }
    }
}
?>
