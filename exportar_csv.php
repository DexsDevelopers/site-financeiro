<?php
// /exportar_csv.php (Versão com Filtro de Data)

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'includes/db_connect.php';
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'usuario';

// 1. Coleta e valida as datas do filtro (se existirem)
$data_inicio = $_GET['inicio'] ?? null;
$data_fim = $_GET['fim'] ?? null;
$params = [$userId];

// 2. Prepara o nome do arquivo e a query SQL dinamicamente
$filename = "extrato_financeiro_" . strtolower(str_replace(' ', '_', $userName));
$sql = "SELECT t.data_transacao, t.descricao, c.nome as categoria, t.tipo, t.valor
        FROM transacoes t
        LEFT JOIN categorias c ON t.id_categoria = c.id
        WHERE t.id_usuario = ?";

if ($data_inicio && $data_fim) {
    $sql .= " AND t.data_transacao BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
    $filename .= "_de_" . $data_inicio . "_a_" . $data_fim;
} elseif ($data_inicio) {
    $sql .= " AND t.data_transacao >= ?";
    $params[] = $data_inicio;
    $filename .= "_a_partir_de_" . $data_inicio;
} elseif ($data_fim) {
    $sql .= " AND t.data_transacao <= ?";
    $params[] = $data_fim;
    $filename .= "_ate_" . $data_fim;
}

$sql .= " ORDER BY t.data_transacao DESC";
$filename .= ".csv";

// 3. Define os cabeçalhos HTTP para forçar o download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '";');

// 4. Busca os dados no banco de dados com os filtros aplicados
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar dados para exportação: " . $e->getMessage());
}

// 5. Escreve o arquivo CSV (lógica original, que já era perfeita)
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
$cabecalho = ['Data', 'Descricao', 'Categoria', 'Tipo', 'Valor'];
fputcsv($output, $cabecalho, ';');

if (!empty($transacoes)) {
    foreach ($transacoes as $transacao) {
        $transacao['valor'] = number_format($transacao['valor'], 2, ',', '.');
        fputcsv($output, $transacao, ';');
    }
}

fclose($output);
exit();
?>