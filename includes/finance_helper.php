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
            
            // Primeiro: buscar categoria padrão com nome específico e tipo correto
            $stmt = $pdo->prepare("SELECT id, tipo FROM categorias WHERE id_usuario = ? AND tipo = ? AND nome LIKE ? LIMIT 1");
            $stmt->execute([$userId, $type, "%$nomeCategoriaPadrao%"]);
            $catResult = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($catResult && $catResult['tipo'] === $type) {
                $idCategoria = $catResult['id'];
            }
            
            // Se não encontrar, buscar qualquer categoria do tipo correto
            if (!$idCategoria) {
                $stmt = $pdo->prepare("SELECT id, tipo FROM categorias WHERE id_usuario = ? AND tipo = ? ORDER BY id ASC LIMIT 1");
                $stmt->execute([$userId, $type]);
                $catResult = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($catResult && $catResult['tipo'] === $type) {
                    $idCategoria = $catResult['id'];
                }
            }
            
            // Se ainda não tiver, criar categoria padrão com o tipo correto
            if (!$idCategoria) {
                $stmt = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $nomeCategoriaPadrao, $type]);
                $idCategoria = $pdo->lastInsertId();
                error_log("Categoria criada: ID=$idCategoria, Nome=$nomeCategoriaPadrao, Tipo=$type");
            } else {
                // Verificar novamente antes de usar
                $stmt = $pdo->prepare("SELECT id, tipo FROM categorias WHERE id = ? AND id_usuario = ?");
                $stmt->execute([$idCategoria, $userId]);
                $catVerify = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$catVerify || $catVerify['tipo'] !== $type) {
                    // Categoria inválida, criar nova
                    $stmt = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $nomeCategoriaPadrao, $type]);
                    $idCategoria = $pdo->lastInsertId();
                    error_log("Categoria inválida detectada, criada nova: ID=$idCategoria, Tipo=$type");
                }
            }
        }
        
        // Validação final: garantir que a categoria tem o tipo correto
        $stmt = $pdo->prepare("SELECT id, tipo, nome FROM categorias WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$idCategoria, $userId]);
        $catFinal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$catFinal || $catFinal['tipo'] !== $type) {
            error_log("ERRO: Categoria ID=$idCategoria tem tipo '{$catFinal['tipo']}' mas precisa ser '$type'. Nome: {$catFinal['nome']}");
            // Forçar criação de categoria correta
            $nomeCategoriaPadrao = $type === 'receita' ? 'Outras Receitas' : 'Outras Despesas';
            $stmt = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $nomeCategoriaPadrao, $type]);
            $idCategoria = $pdo->lastInsertId();
        }
        
        // 3. Validação final antes de inserir
        // Verificar novamente o tipo da categoria
        $stmt = $pdo->prepare("SELECT tipo, nome FROM categorias WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$idCategoria, $userId]);
        $catCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$catCheck || $catCheck['tipo'] !== $type) {
            error_log("ERRO CRÍTICO: Tentativa de inserir transação tipo '$type' com categoria '{$catCheck['nome']}' tipo '{$catCheck['tipo']}'");
            return [
                'success' => false,
                'error' => "Erro: categoria '{$catCheck['nome']}' é do tipo '{$catCheck['tipo']}' mas a transação é '$type'"
            ];
        }
        
        // 4. Inserir transação na tabela transacoes
        $dataTransacao = date('Y-m-d H:i:s');
        $sql = "INSERT INTO transacoes (id_usuario, id_categoria, id_conta, descricao, valor, tipo, data_transacao) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $idCategoria, $idConta, $description, $value, $type, $dataTransacao]);
        
        $transactionId = $pdo->lastInsertId();
        
        // Log para debug
        error_log("Transação inserida: ID=$transactionId, Tipo=$type, Categoria={$catCheck['nome']}, Valor=$value, Descrição=$description");
        
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'message' => ucfirst($type) . ' registrada com sucesso',
            'id_conta' => $idConta,
            'id_categoria' => $idCategoria,
            'categoria_nome' => $catCheck['nome']
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
        
        // Top categorias de receita
        $where = "YEAR(t.data_transacao) = ? AND MONTH(t.data_transacao) = ? AND t.tipo = 'receita'";
        $params = [$year, $month];
        
        if ($userId) {
            $where .= " AND t.id_usuario = ?";
            $params[] = $userId;
        }
        
        $sqlTop = "SELECT cat.nome as name, SUM(t.valor) as total 
                   FROM transacoes t 
                   JOIN categorias cat ON t.id_categoria = cat.id 
                   WHERE $where 
                   GROUP BY t.id_categoria, cat.nome 
                   ORDER BY total DESC 
                   LIMIT 5";
        $stmt = $pdo->prepare($sqlTop);
        $stmt->execute($params);
        $topClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top categorias de despesa
        $sqlTopCat = "SELECT cat.nome as category, SUM(t.valor) as total 
                      FROM transacoes t
                      JOIN categorias cat ON t.id_categoria = cat.id
                      WHERE t.tipo = 'despesa' AND YEAR(t.data_transacao) = ? AND MONTH(t.data_transacao) = ?";
        $paramsCat = [$year, $month];
        if ($userId) {
            $sqlTopCat .= " AND t.id_usuario = ?";
            $paramsCat[] = $userId;
        }
        $sqlTopCat .= " GROUP BY cat.nome ORDER BY total DESC LIMIT 5";
        
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

