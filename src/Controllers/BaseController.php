<?php
// src/Controllers/BaseController.php - Controller Base

abstract class BaseController {
    protected $pdo;
    protected $userId;
    protected $userName;
    
    public function __construct($pdo, $userId, $userName) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->userName = $userName;
    }
    
    /**
     * Validar entrada de dados
     */
    protected function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (isset($rule['required']) && $rule['required'] && empty($data[$field])) {
                $errors[$field] = "Campo {$field} é obrigatório";
                continue;
            }
            
            if (isset($data[$field]) && !empty($data[$field])) {
                if (isset($rule['type'])) {
                    switch ($rule['type']) {
                        case 'email':
                            if (!filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                                $errors[$field] = "Email inválido";
                            }
                            break;
                        case 'numeric':
                            if (!is_numeric($data[$field])) {
                                $errors[$field] = "Deve ser um número";
                            }
                            break;
                        case 'date':
                            if (!strtotime($data[$field])) {
                                $errors[$field] = "Data inválida";
                            }
                            break;
                    }
                }
                
                if (isset($rule['min_length']) && strlen($data[$field]) < $rule['min_length']) {
                    $errors[$field] = "Mínimo {$rule['min_length']} caracteres";
                }
                
                if (isset($rule['max_length']) && strlen($data[$field]) > $rule['max_length']) {
                    $errors[$field] = "Máximo {$rule['max_length']} caracteres";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Sanitizar dados
     */
    protected function sanitizeInput($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Resposta JSON padronizada
     */
    protected function jsonResponse($success, $message, $data = null, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Verificar CSRF token
     */
    protected function verifyCSRF($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $this->jsonResponse(false, 'Token CSRF inválido', null, 403);
        }
    }
    
    /**
     * Gerar CSRF token
     */
    protected function generateCSRF() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
