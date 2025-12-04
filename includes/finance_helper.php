<?php
// includes/finance_helper.php - Funções helper para sistema financeiro

/**
 * Registra uma transação financeira na tabela transacoes (compatível com o painel)
 */
function registerTransaction(PDO $pdo, string $type, float $value, string $description, ?string $category = null, ?int $clientId = null, ?int $userId = null, ?string $createdBy = null, ?int $idConta = null, ?int $idCategoria = null): array {
    try {
        if (!$userId) {
            return [
                'success' => false,
                'error' => 'ID do usuário é obrigatório'
            ];
        }
        
        // 1. Obter ou criar conta
        if ($idConta) {
            // Verificar se a conta pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM contas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$idConta, $userId]);
            if (!$stmt->fetchColumn()) {
                $idConta = null; // Conta inválida, buscar padrão
            }
        }
        
        if (!$idConta) {
            // Buscar primeira conta do usuário
            $stmt = $pdo->prepare("SELECT id FROM contas WHERE id_usuario = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$userId]);
            $idConta = $stmt->fetchColumn();
            
            // Se não tiver conta, criar uma "Geral"
            if (!$idConta) {
                $stmt = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, saldo_inicial) VALUES (?, 'Geral', 'dinheiro', 0)");
                $stmt->execute([$userId]);
                $idConta = $pdo->lastInsertId();
            }
        }
        
        // 2. Obter ou criar categoria
        if ($idCategoria) {
            // Verificar se a categoria pertence ao usuário e tem o tipo correto
            $stmt = $pdo->prepare("SELECT id, tipo FROM categorias WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$idCategoria, $userId]);
            $catInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$catInfo || $catInfo['tipo'] !== $type) {
                $idCategoria = null; // Categoria inválida ou tipo incorreto
            }
        }
        
        if (!$idCategoria) {
            // Buscar categoria padrão do tipo (receita ou despesa)
            $nomeCategoriaPadrao = $type === 'receita' ? 'Outras Receitas' : 'Outras Despesas';
            $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND tipo = ? AND nome LIKE ? LIMIT 1");
            $stmt->execute([$userId, $type, "%$nomeCategoriaPadrao%"]);
            $idCategoria = $stmt->fetchColumn();
            
            // Se não encontrar, buscar qualquer categoria do tipo
            if (!$idCategoria) {
                $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND tipo = ? ORDER BY id ASC LIMIT 1");
                $stmt->execute([$userId, $type]);
                $idCategoria = $stmt->fetchColumn();
            }
            
            // Se ainda não tiver, criar categoria padrão
            if (!$idCategoria) {
                $stmt = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $nomeCategoriaPadrao, $type]);
                $idCategoria = $pdo->lastInsertId();
            }
        }
        
        // 3. Inserir transação na tabela transacoes
        $dataTransacao = date('Y-m-d H:i:s');
        $sql = "INSERT INTO transacoes (id_usuario, id_categoria, id_conta, descricao, valor, tipo, data_transacao) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $idCategoria, $idConta, $description, $value, $type, $dataTransacao]);
        
        return [
            'success' => true,
            'transaction_id' => $pdo->lastInsertId(),
            'message' => ucfirst($type) . ' registrada com sucesso',
            'id_conta' => $idConta,
            'id_categoria' => $idCategoria
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao registrar transação: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtém saldo do mês/ano (usando tabela transacoes)
 */
function getBalance(PDO $pdo, ?int $month = null, ?int $year = null, ?int $userId = null): array {
    try {
        if (!$month) $month = (int)date('m');
        if (!$year) $year = (int)date('Y');
        
        $where = "YEAR(data_transacao) = ? AND MONTH(data_transacao) = ?";
        $params = [$year, $month];
        
        if ($userId) {
            $where .= " AND id_usuario = ?";
            $params[] = $userId;
        }
        
        // Receitas
        $sqlReceitas = "SELECT COALESCE(SUM(valor), 0) as total, COUNT(*) as count 
                        FROM transacoes 
                        WHERE tipo = 'receita' AND $where";
        $stmt = $pdo->prepare($sqlReceitas);
        $stmt->execute($params);
        $receitas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Despesas
        $sqlDespesas = "SELECT COALESCE(SUM(valor), 0) as total, COUNT(*) as count 
                        FROM transacoes 
                        WHERE tipo = 'despesa' AND $where";
        $stmt = $pdo->prepare($sqlDespesas);
        $stmt->execute($params);
        $despesas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $saldo = $receitas['total'] - $despesas['total'];
        
        return [
            'success' => true,
            'month' => $month,
            'year' => $year,
            'receitas' => [
                'total' => (float)$receitas['total'],
                'count' => (int)$receitas['count']
            ],
            'despesas' => [
                'total' => (float)$despesas['total'],
                'count' => (int)$despesas['count']
            ],
            'saldo' => $saldo
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao calcular saldo: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtém extrato de período (usando tabela transacoes)
 */
function getExtract(PDO $pdo, ?string $startDate = null, ?string $endDate = null, ?int $userId = null, int $limit = 50): array {
    try {
        if (!$startDate) $startDate = date('Y-m-01');
        if (!$endDate) $endDate = date('Y-m-t');
        
        $where = "DATE(t.data_transacao) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($userId) {
            $where .= " AND t.id_usuario = ?";
            $params[] = $userId;
        }
        
        $sql = "SELECT t.*, cat.nome as categoria_nome, conta.nome as conta_nome
                FROM transacoes t 
                LEFT JOIN categorias cat ON t.id_categoria = cat.id
                LEFT JOIN contas conta ON t.id_conta = conta.id
                WHERE $where 
                ORDER BY t.data_transacao DESC 
                LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'transactions' => $transactions,
            'count' => count($transactions)
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao buscar extrato: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtém pendências de um cliente
 */
function getClientPendencies(PDO $pdo, ?int $clientId = null, ?int $userId = null): array {
    try {
        $where = "status = 'pendente' AND due_date >= CURDATE()";
        $params = [];
        
        if ($clientId) {
            $where .= " AND client_id = ?";
            $params[] = $clientId;
        }
        
        if ($userId) {
            $where .= " AND id_usuario = ?";
            $params[] = $userId;
        }
        
        $sql = "SELECT c.*, cl.name as client_name, cl.whatsapp_number 
                FROM charges c 
                LEFT JOIN clients cl ON c.client_id = cl.id 
                WHERE $where 
                ORDER BY c.due_date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pendencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = 0;
        foreach ($pendencies as $p) {
            $total += (float)$p['value'];
        }
        
        return [
            'success' => true,
            'pendencies' => $pendencies,
            'count' => count($pendencies),
            'total' => $total
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao buscar pendências: ' . $e->getMessage()
        ];
    }
}

/**
 * Gera relatório do mês
 */
function generateMonthReport(PDO $pdo, ?int $month = null, ?int $year = null, ?int $userId = null): array {
    try {
        if (!$month) $month = (int)date('m');
        if (!$year) $year = (int)date('Y');
        
        $balance = getBalance($pdo, $month, $year, $userId);
        if (!$balance['success']) {
            return $balance;
        }
        
        // Top clientes
        $where = "YEAR(t.created_at) = ? AND MONTH(t.created_at) = ? AND t.type = 'receita' AND t.client_id IS NOT NULL";
        $params = [$year, $month];
        
        if ($userId) {
            $where .= " AND t.id_usuario = ?";
            $params[] = $userId;
        }
        
        $sqlTop = "SELECT c.name, SUM(t.value) as total 
                   FROM transactions t 
                   JOIN clients c ON t.client_id = c.id 
                   WHERE $where 
                   GROUP BY t.client_id, c.name 
                   ORDER BY total DESC 
                   LIMIT 5";
        $stmt = $pdo->prepare($sqlTop);
        $stmt->execute($params);
        $topClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top categorias de despesa
        $sqlTopCat = "SELECT category, SUM(value) as total 
                      FROM transactions 
                      WHERE type = 'despesa' AND YEAR(transactions.created_at) = ? AND MONTH(transactions.created_at) = ? AND category IS NOT NULL";
        $paramsCat = [$year, $month];
        if ($userId) {
            $sqlTopCat .= " AND id_usuario = ?";
            $paramsCat[] = $userId;
        }
        $sqlTopCat .= " GROUP BY category ORDER BY total DESC LIMIT 5";
        
        $stmt = $pdo->prepare($sqlTopCat);
        $stmt->execute($paramsCat);
        $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'month' => $month,
            'year' => $year,
            'balance' => $balance,
            'top_clients' => $topClients,
            'top_categories' => $topCategories
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao gerar relatório: ' . $e->getMessage()
        ];
    }
}

/**
 * Formata valor monetário
 */
function formatMoney(float $value, string $currency = 'BRL'): string {
    if ($currency === 'BRL') {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
    return number_format($value, 2, '.', ',');
}

/**
 * Formata data
 */
function formatDate(string $date, string $format = 'd/m/Y'): string {
    return date($format, strtotime($date));
}

/**
 * Normaliza número de telefone para busca
 */
function normalizePhoneForSearch(string $phone): string {
    $phone = preg_replace('/\D+/', '', $phone);
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '55') {
        return $phone;
    }
    if (strlen($phone) === 11) {
        return '55' . $phone;
    }
    if (strlen($phone) === 13) {
        return $phone;
    }
    return $phone;
}

