<?php
// google_integration_manager.php - Gerenciador de Integrações Google

class GoogleIntegrationManager {
    private $pdo;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scopes;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Configurações OAuth (podem estar no .env ou como constantes)
        // Tenta carregar do .env primeiro, depois de constantes, depois padrão
        $this->clientId = getenv('GOOGLE_CLIENT_ID') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
        $this->clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '');
        
        $redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : '');
        if (empty($redirectUri)) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $redirectUri = $protocol . '://' . $host . '/seu_projeto/google_oauth_callback.php';
        }
        $this->redirectUri = $redirectUri;
        
        // Scopes necessários para os serviços
        $this->scopes = [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/tasks',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.readonly', // Para verificar perfil
            'https://www.googleapis.com/auth/spreadsheets'
        ];
        
        // Criar tabela se não existir
        $this->createTablesIfNotExists();
    }
    
    /**
     * Criar tabelas necessárias para armazenar tokens
     */
    private function createTablesIfNotExists() {
        try {
            // Tabela de tokens OAuth
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS google_oauth_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_usuario INT NOT NULL,
                    access_token TEXT NOT NULL,
                    refresh_token TEXT,
                    token_type VARCHAR(50) DEFAULT 'Bearer',
                    expires_in INT DEFAULT 3600,
                    expires_at DATETIME,
                    scope TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user (id_usuario),
                    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Tabela de configurações de integração
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS google_integrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_usuario INT NOT NULL,
                    service_name VARCHAR(50) NOT NULL,
                    enabled TINYINT(1) DEFAULT 1,
                    settings JSON,
                    last_sync DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_service (id_usuario, service_name),
                    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            // Tabelas já existem ou erro de permissão
            error_log("Erro ao criar tabelas Google: " . $e->getMessage());
        }
    }
    
    /**
     * Gerar URL de autorização OAuth
     */
    public function getAuthUrl($userId) {
        if (empty($this->clientId)) {
            throw new Exception('Google Client ID não configurado. Configure GOOGLE_CLIENT_ID no .env');
        }
        
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_user_id'] = $userId;
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Trocar código de autorização por tokens
     */
    public function exchangeCodeForTokens($code, $state) {
        // Verificar state
        if (!isset($_SESSION['google_oauth_state']) || $_SESSION['google_oauth_state'] !== $state) {
            throw new Exception('Estado OAuth inválido');
        }
        
        $userId = $_SESSION['google_oauth_user_id'] ?? null;
        if (!$userId) {
            throw new Exception('ID do usuário não encontrado na sessão');
        }
        
        // Trocar código por tokens
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $data = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Erro ao obter tokens: " . $response);
            throw new Exception('Erro ao obter tokens de acesso');
        }
        
        $tokenData = json_decode($response, true);
        
        if (isset($tokenData['error'])) {
            throw new Exception('Erro: ' . $tokenData['error_description']);
        }
        
        // Salvar tokens no banco
        $this->saveTokens($userId, $tokenData);
        
        // Limpar sessão
        unset($_SESSION['google_oauth_state']);
        unset($_SESSION['google_oauth_user_id']);
        
        return true;
    }
    
    /**
     * Salvar tokens no banco de dados
     */
    private function saveTokens($userId, $tokenData) {
        $expiresAt = null;
        if (isset($tokenData['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + $tokenData['expires_in']);
        }
        
        $sql = "INSERT INTO google_oauth_tokens 
                (id_usuario, access_token, refresh_token, token_type, expires_in, expires_at, scope)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                token_type = VALUES(token_type),
                expires_in = VALUES(expires_in),
                expires_at = VALUES(expires_at),
                scope = VALUES(scope),
                updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $tokenData['token_type'] ?? 'Bearer',
            $tokenData['expires_in'] ?? 3600,
            $expiresAt,
            implode(' ', $this->scopes)
        ]);
    }
    
    /**
     * Obter token de acesso válido (renova se necessário)
     */
    public function getValidAccessToken($userId) {
        $stmt = $this->pdo->prepare("
            SELECT access_token, refresh_token, expires_at 
            FROM google_oauth_tokens 
            WHERE id_usuario = ?
        ");
        $stmt->execute([$userId]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token) {
            return null;
        }
        
        // Verificar se o token expirou
        if ($token['expires_at'] && strtotime($token['expires_at']) <= time() + 60) {
            // Renovar token
            if ($token['refresh_token']) {
                $newToken = $this->refreshAccessToken($token['refresh_token'], $userId);
                return $newToken ? $newToken['access_token'] : null;
            }
            return null;
        }
        
        return $token['access_token'];
    }
    
    /**
     * Renovar token de acesso usando refresh token
     */
    private function refreshAccessToken($refreshToken, $userId) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Erro ao renovar token: " . $response);
            return null;
        }
        
        $tokenData = json_decode($response, true);
        
        if (isset($tokenData['error'])) {
            return null;
        }
        
        // Atualizar tokens (preservar refresh_token)
        $expiresAt = date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 3600));
        
        $sql = "UPDATE google_oauth_tokens 
                SET access_token = ?, 
                    expires_at = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id_usuario = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $tokenData['access_token'],
            $expiresAt,
            $userId
        ]);
        
        return $tokenData;
    }
    
    /**
     * Verificar se usuário está conectado ao Google
     */
    public function isConnected($userId) {
        $token = $this->getValidAccessToken($userId);
        return $token !== null;
    }
    
    /**
     * Desconectar conta Google
     */
    public function disconnect($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM google_oauth_tokens WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        
        $stmt2 = $this->pdo->prepare("DELETE FROM google_integrations WHERE id_usuario = ?");
        $stmt2->execute([$userId]);
        
        return true;
    }
    
    /**
     * Fazer requisição autenticada à API do Google
     */
    public function makeApiRequest($userId, $url, $method = 'GET', $data = null) {
        $accessToken = $this->getValidAccessToken($userId);
        
        if (!$accessToken) {
            throw new Exception('Token de acesso não disponível. Reconecte sua conta Google.');
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            error_log("Erro na API Google ($httpCode): " . $response);
            throw new Exception("Erro na API Google: " . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Habilitar/desabilitar serviço específico
     */
    public function setServiceEnabled($userId, $serviceName, $enabled, $settings = null) {
        $sql = "INSERT INTO google_integrations (id_usuario, service_name, enabled, settings)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                settings = COALESCE(VALUES(settings), settings),
                updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $serviceName,
            $enabled ? 1 : 0,
            $settings ? json_encode($settings) : null
        ]);
    }
    
    /**
     * Verificar se serviço está habilitado
     */
    public function isServiceEnabled($userId, $serviceName) {
        $stmt = $this->pdo->prepare("
            SELECT enabled FROM google_integrations 
            WHERE id_usuario = ? AND service_name = ?
        ");
        $stmt->execute([$userId, $serviceName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['enabled'] == 1;
    }
}

