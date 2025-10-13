<?php
// includes/remember_me_manager.php - Gerenciador de "Lembrar-me"

class RememberMeManager {
    private $pdo;
    private $cookie_name = 'remember_token';
    private $cookie_expire = 30; // 30 dias
    private $token_length = 64;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
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
            
            // Definir cookie seguro
            $this->setRememberCookie($token, $expiresAt);
            
            return $token;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Verificar token de lembrança
     */
    public function verifyRememberToken($token) {
        if (empty($token)) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT rt.*, u.id, u.nome, u.email 
                FROM remember_tokens rt 
                JOIN usuarios u ON rt.user_id = u.id 
                WHERE rt.token = ? 
                AND rt.is_active = 1 
                AND rt.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Atualizar último uso
                $this->updateLastUsed($token);
                return $result;
            }
            
            return false;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Fazer login automático com token
     */
    public function autoLogin($token) {
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
            $_SESSION['user'] = [
                'id' => $userData['id'],
                'nome' => $userData['nome'],
                'email' => $userData['email']
            ];
            
            // Renovar token se necessário (últimos 7 dias)
            $lastUsed = strtotime($userData['last_used_at']);
            if ((time() - $lastUsed) > (7 * 24 * 60 * 60)) {
                $this->renewToken($token);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Renovar token existente
     */
    public function renewToken($oldToken) {
        $userData = $this->verifyRememberToken($oldToken);
        
        if ($userData) {
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
            
            // Remover cookie
            $this->clearRememberCookie();
            
            return true;
            
        } catch (PDOException $e) {
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
            
            // Remover cookie
            $this->clearRememberCookie();
            
            return true;
            
        } catch (PDOException $e) {
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
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
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
            // Ignorar erro
        }
    }
    
    /**
     * Definir cookie de lembrança
     */
    private function setRememberCookie($token, $expiresAt) {
        $expires = strtotime($expiresAt);
        
        setcookie(
            $this->cookie_name,
            $token,
            $expires,
            '/',
            '',
            true,  // HTTPS only
            true   // HttpOnly
        );
    }
    
    /**
     * Limpar cookie de lembrança
     */
    private function clearRememberCookie() {
        setcookie(
            $this->cookie_name,
            '',
            time() - 3600,
            '/',
            '',
            true,
            true
        );
    }
    
    /**
     * Obter token do cookie
     */
    public function getTokenFromCookie() {
        return $_COOKIE[$this->cookie_name] ?? null;
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
            return false;
        }
    }
}
?>
