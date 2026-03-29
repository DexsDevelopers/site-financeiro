<?php
/**
 * processar_ia.php — Motor de Lançamento Inteligente (Orion NLP Local)
 * Sem APIs externas. Processamento 100% local com regras + fuzzy matching.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

$userId       = (int)$_SESSION['user_id'];
$input        = json_decode(file_get_contents('php://input'), true);
$texto        = trim($input['texto'] ?? '');
$id_conta_req = isset($input['id_conta']) ? (int)$input['id_conta'] : null;

if (empty($texto)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Nenhum texto fornecido.']));
}

// ══════════════════════════════════════════════════════════════════
// 1. NORMALIZAÇÃO
// ══════════════════════════════════════════════════════════════════
$textoNorm = mb_strtolower($texto, 'UTF-8');

// ══════════════════════════════════════════════════════════════════
// 2. DETECÇÃO DE TIPO (despesa / receita)
// ══════════════════════════════════════════════════════════════════
$tipo = 'despesa'; // padrão

$palavrasReceita = ['recebi','ganhei','vendi','venda','lucro','faturei','entrada','salário','salario',
                    'depósito','deposito','renda','pagamento recebido','reembolso','dividendo','freelance recebido'];
$palavrasDespesa = ['gastei','paguei','comprei','compra','gasto','despesa','saída','saida','débito','debito',
                    'assinatura','parcela','aluguel','conta','fatura','consumi'];

foreach ($palavrasReceita as $p) {
    if (str_contains($textoNorm, $p)) { $tipo = 'receita'; break; }
}
// Despesa sobrepõe receita se ambas aparecerem (caso raro)
foreach ($palavrasDespesa as $p) {
    if (str_contains($textoNorm, $p)) { $tipo = 'despesa'; break; }
}

// ══════════════════════════════════════════════════════════════════
// 3. EXTRAÇÃO DE VALOR
//    Suporta: R$ 25 / 25 reais / 25,50 / R$25.90 / "vinte e cinco"
// ══════════════════════════════════════════════════════════════════
$valor = null;

// Números por extenso (até 999)
$numExtenso = [
    'zero'=>0,'um'=>1,'uma'=>1,'dois'=>2,'duas'=>2,'três'=>3,'tres'=>3,'quatro'=>4,
    'cinco'=>5,'seis'=>6,'sete'=>7,'oito'=>8,'nove'=>9,'dez'=>10,'onze'=>11,
    'doze'=>12,'treze'=>13,'quatorze'=>14,'catorze'=>14,'quinze'=>15,'dezesseis'=>16,
    'dezessete'=>17,'dezoito'=>18,'dezenove'=>19,'vinte'=>20,'trinta'=>30,
    'quarenta'=>40,'cinquenta'=>50,'sessenta'=>60,'setenta'=>70,'oitenta'=>80,
    'noventa'=>90,'cem'=>100,'cento'=>100,'duzentos'=>200,'duzentas'=>200,
    'trezentos'=>300,'trezentas'=>300,'quatrocentos'=>400,'quinhentos'=>500,
    'seiscentos'=>600,'setecentos'=>700,'oitocentos'=>800,'novecentos'=>900,'mil'=>1000
];

// Tenta número por extenso primeiro
$valorExtenso = null;
foreach ($numExtenso as $palavra => $num) {
    if (preg_match('/\b' . preg_quote($palavra, '/') . '\b/i', $textoNorm)) {
        if ($valorExtenso === null) $valorExtenso = $num;
        else $valorExtenso += $num; // "vinte e cinco" → 20 + 5
    }
}

// Regex para valores numéricos (tem prioridade sobre extenso)
if (preg_match('/r\$\s*(\d{1,6}(?:[.,]\d{1,2})?)/i', $texto, $m)) {
    $valor = (float) str_replace(['.', ','], ['', '.'], $m[1]);
} elseif (preg_match('/(\d{1,6}(?:[.,]\d{1,2})?)\s*(?:reais|real)/i', $texto, $m)) {
    $valor = (float) str_replace(',', '.', $m[1]);
} elseif (preg_match('/(\d{1,6}[.,]\d{2})/', $texto, $m)) {
    $valor = (float) str_replace(',', '.', $m[1]);
} elseif (preg_match('/\b(\d{1,6})\b/', $texto, $m)) {
    $valor = (float) $m[1];
} elseif ($valorExtenso !== null) {
    $valor = (float) $valorExtenso;
}

if (!$valor || $valor <= 0) {
    exit(json_encode(['success' => false, 'message' => 'Não identifiquei o valor. Tente: "Comprei pizza R$ 25 hoje".']));
}

// ══════════════════════════════════════════════════════════════════
// 4. EXTRAÇÃO DE DATA
// ══════════════════════════════════════════════════════════════════
$data = date('Y-m-d'); // padrão: hoje

if (str_contains($textoNorm, 'ontem')) {
    $data = date('Y-m-d', strtotime('-1 day'));
} elseif (preg_match('/ant[eo]ontem/i', $textoNorm)) {
    $data = date('Y-m-d', strtotime('-2 days'));
} elseif (preg_match('/semana\s+passada/i', $textoNorm)) {
    $data = date('Y-m-d', strtotime('-7 days'));
} elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/', $texto, $dm)) {
    $d = str_pad($dm[1], 2, '0', STR_PAD_LEFT);
    $mo = str_pad($dm[2], 2, '0', STR_PAD_LEFT);
    $y = isset($dm[3]) ? (strlen($dm[3]) == 2 ? '20'.$dm[3] : $dm[3]) : date('Y');
    $ts = mktime(0, 0, 0, (int)$mo, (int)$d, (int)$y);
    if ($ts) $data = date('Y-m-d', $ts);
} elseif (preg_match('/\bsegunda(?:-feira)?\b/i', $textoNorm)) {
    $data = date('Y-m-d', strtotime('last monday'));
} elseif (preg_match('/\bterça(?:-feira)?\b/i', $textoNorm)) {
    $data = date('Y-m-d', strtotime('last tuesday'));
} elseif (preg_match('/\bquarta(?:-feira)?\b/i', $textoNorm)) {
    $data = date('Y-m-d', strtotime('last wednesday'));
} elseif (preg_match('/\bquinta(?:-feira)?\b/i', $textoNorm)) {
    $data = date('Y-m-d', strtotime('last thursday'));
} elseif (preg_match('/\bsexta(?:-feira)?\b/i', $textoNorm)) {
    $data = date('Y-m-d', strtotime('last friday'));
}

// ══════════════════════════════════════════════════════════════════
// 5. DETECÇÃO DE PARCELAMENTO
//    "parcelei 300 em 3x" / "em 6 parcelas" / "3 vezes"
// ══════════════════════════════════════════════════════════════════
$parcelas = 1;
if (preg_match('/(\d+)\s*[xX](?:\s|$)/i', $texto, $pm)) {
    $parcelas = max(1, min(60, (int)$pm[1]));
} elseif (preg_match('/em\s+(\d+)\s+parcelas?/i', $texto, $pm)) {
    $parcelas = max(1, min(60, (int)$pm[1]));
} elseif (preg_match('/(\d+)\s+vezes/i', $texto, $pm)) {
    $parcelas = max(1, min(60, (int)$pm[1]));
}
$valorParcela = round($valor / $parcelas, 2);

// ══════════════════════════════════════════════════════════════════
// 6. EXTRAÇÃO DE DESCRIÇÃO (remove ruído, mantém o essencial)
// ══════════════════════════════════════════════════════════════════
$desc = $texto;
$remover = [
    '/\br\$\s*\d+(?:[.,]\d{1,2})?\b/i',
    '/\b\d+(?:[.,]\d{1,2})?\s*(?:reais|real)\b/i',
    '/\b\d+(?:[.,]\d{1,2})?\b/',
    '/\bhoje\b|\bontem\b|\banteontem\b|\bsemana passada\b/i',
    '/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/',
    '/\b(gastei|paguei|comprei|recebi|ganhei|vendi|parcelei|em\s+\d+x|em\s+\d+\s+parcelas?|\d+\s+vezes)\b/i',
    '/\b(de|da|do|para|por|no|na|em|com|uma?|uns?|umas?)\b/i',
    '/\s{2,}/',
];
foreach ($remover as $pattern) {
    $desc = preg_replace($pattern, ' ', $desc);
}
$desc = trim($desc);
if (mb_strlen($desc) < 3) {
    $desc = ($tipo === 'receita') ? 'Receita' : 'Despesa';
}
$desc = ucfirst(mb_strtolower($desc, 'UTF-8'));

// ══════════════════════════════════════════════════════════════════
// 7. MAPA DE PALAVRAS-CHAVE → CATEGORIA (base de conhecimento)
// ══════════════════════════════════════════════════════════════════
$mapaCategoria = [
    // Alimentação
    'Alimentação'   => ['pizza','lanche','hamburguer','hambúrguer','restaurante','almoço','almoco','jantar',
                        'café','cafe','cafezinho','comida','marmita','ifood','delivery','supermercado',
                        'mercado','padaria','açaí','acai','sushi','churrasco','feira','hortifruti'],
    // Transporte
    'Transporte'    => ['uber','99','cabify','táxi','taxi','ônibus','onibus','metrô','metro','combustível',
                        'combustivel','gasolina','etanol','diesel','pedágio','pedagio','estacionamento',
                        'passagem','bilhete','trem'],
    // Saúde
    'Saúde'         => ['farmácia','farmacia','remédio','remedio','médico','medico','dentista','consulta',
                        'exame','hospital','clínica','clinica','academia','plano de saúde','plano saude'],
    // Lazer
    'Lazer'         => ['cinema','teatro','show','ingresso','netflix','spotify','disney','amazon prime',
                        'jogo','steam','viagem','hotel','hostel','airbnb','praia','parque','festa'],
    // Educação
    'Educação'      => ['curso','livro','escola','faculdade','udemy','alura','coursera','mensalidade',
                        'material escolar','apostila','treinamento'],
    // Moradia
    'Moradia'       => ['aluguel','condomínio','condominio','água','agua','luz','energia','gás','gas',
                        'internet','telefone','iptu','reforma','manutenção','manutencao'],
    // Vestuário
    'Vestuário'     => ['roupa','calçado','calcado','tênis','tenis','camisa','calça','calca','vestido',
                        'sapato','sandália','sandalia','moda','shein','zara','renner'],
    // Pets
    'Pets'          => ['ração','racao','veterinário','veterinario','pet shop','petshop','banho tosa'],
    // Tecnologia
    'Tecnologia'    => ['celular','smartphone','notebook','computador','tablet','fone','headset',
                        'cabo','carregador','acessório','acessorio'],
    // Salário/Renda
    'Salário'       => ['salário','salario','pagamento','holerite','contra-cheque','contrachque'],
    'Freelance'     => ['freela','freelance','serviço','servico','projeto','cliente'],
];

// ══════════════════════════════════════════════════════════════════
// 8. BUSCA DE CATEGORIA (3 níveis: exata → fuzzy → palavras-chave)
// ══════════════════════════════════════════════════════════════════
try {
    $stmtCats = $pdo->prepare("SELECT id, nome, tipo FROM categorias WHERE id_usuario = ? ORDER BY nome");
    $stmtCats->execute([$userId]);
    $categoriasDB = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categoriasDB = [];
}

$idCategoria  = null;
$nomeCategoria = null;
$catCriada    = false;

// Nível 1: palavra da categoria aparece no texto
foreach ($categoriasDB as $cat) {
    if ($cat['tipo'] !== $tipo) continue;
    if (mb_stripos($textoNorm, mb_strtolower($cat['nome'], 'UTF-8')) !== false) {
        $idCategoria   = $cat['id'];
        $nomeCategoria = $cat['nome'];
        break;
    }
}

// Nível 2: palavras-chave do mapa batem no texto → nome do mapa bate nas categorias do user
if (!$idCategoria) {
    $candidataMapa = null;
    foreach ($mapaCategoria as $nomeMapa => $palavras) {
        foreach ($palavras as $p) {
            if (str_contains($textoNorm, $p)) { $candidataMapa = $nomeMapa; break 2; }
        }
    }
    if ($candidataMapa) {
        foreach ($categoriasDB as $cat) {
            if ($cat['tipo'] !== $tipo) continue;
            similar_text(mb_strtolower($cat['nome'], 'UTF-8'), mb_strtolower($candidataMapa, 'UTF-8'), $perc);
            if ($perc >= 70) {
                $idCategoria   = $cat['id'];
                $nomeCategoria = $cat['nome'];
                break;
            }
        }
        // Se não achou no banco, usa o nome do mapa como nova categoria
        if (!$idCategoria) $nomeCategoria = $candidataMapa;
    }
}

// Nível 3: fuzzy match entre palavras do texto e nomes de categoria
if (!$idCategoria) {
    $palavrasTexto = explode(' ', $textoNorm);
    $melhorScore   = 0;
    $melhorCat     = null;
    foreach ($categoriasDB as $cat) {
        if ($cat['tipo'] !== $tipo) continue;
        foreach ($palavrasTexto as $w) {
            if (mb_strlen($w) < 4) continue;
            similar_text($w, mb_strtolower($cat['nome'], 'UTF-8'), $perc);
            if ($perc > $melhorScore) { $melhorScore = $perc; $melhorCat = $cat; }
        }
    }
    if ($melhorScore >= 60 && $melhorCat) {
        $idCategoria   = $melhorCat['id'];
        $nomeCategoria = $melhorCat['nome'];
    }
}

// Fallback: cria/usa "Outros"
if (!$idCategoria) {
    $nomeCategoria = $nomeCategoria ?: 'Outros';
    // Tenta achar "Outros" ou similar no banco
    foreach ($categoriasDB as $cat) {
        if ($cat['tipo'] === $tipo && preg_match('/^(outros|geral)/i', $cat['nome'])) {
            $idCategoria   = $cat['id'];
            $nomeCategoria = $cat['nome'];
            break;
        }
    }
    if (!$idCategoria) {
        try {
            $stmtNovaCat = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
            $stmtNovaCat->execute([$userId, $nomeCategoria, $tipo]);
            $idCategoria = $pdo->lastInsertId();
            $catCriada   = true;
        } catch (PDOException $e) {
            exit(json_encode(['success' => false, 'message' => 'Erro ao criar categoria: ' . $e->getMessage()]));
        }
    }
}

// ══════════════════════════════════════════════════════════════════
// 9. RESOLVER CONTA
// ══════════════════════════════════════════════════════════════════
$id_conta = $id_conta_req;
if (!$id_conta) {
    try {
        $stmtConta = $pdo->prepare("SELECT id FROM contas WHERE id_usuario = ? ORDER BY id LIMIT 1");
        $stmtConta->execute([$userId]);
        $id_conta = $stmtConta->fetchColumn();
        if (!$id_conta) {
            $stmtNovaConta = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, saldo_inicial) VALUES (?, 'Carteira', 'dinheiro', 0)");
            $stmtNovaConta->execute([$userId]);
            $id_conta = $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        $id_conta = 0;
    }
}

// ══════════════════════════════════════════════════════════════════
// 10. INSERIR TRANSAÇÃO(ÕES) — suporte a parcelamento
// ══════════════════════════════════════════════════════════════════
try {
    $pdo->beginTransaction();

    $stmtIns = $pdo->prepare(
        "INSERT INTO transacoes (id_usuario, id_categoria, id_conta, descricao, valor, tipo, data_transacao)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    for ($i = 0; $i < $parcelas; $i++) {
        $descParcela = ($parcelas > 1) ? "{$desc} ({$i+1}/{$parcelas})" : $desc;
        $dataParcela = ($i === 0) ? $data : date('Y-m-d', strtotime($data . " +{$i} month"));
        $stmtIns->execute([$userId, $idCategoria, $id_conta, $descParcela, $valorParcela, $tipo, $dataParcela]);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]));
}

// ══════════════════════════════════════════════════════════════════
// 11. PUSH EVENTO
// ══════════════════════════════════════════════════════════════════
try {
    require_once __DIR__ . '/includes/push_eventos.php';
    $evento = ($tipo === 'despesa') ? 'nova_despesa' : 'nova_receita';
    dispararPushEvento($pdo, $userId, $evento, [
        'valor'     => $valor,
        'categoria' => $nomeCategoria,
        'descricao' => $desc,
    ]);
} catch (Exception $pushErr) { /* silencioso */ }

// ══════════════════════════════════════════════════════════════════
// 12. RESPOSTA
// ══════════════════════════════════════════════════════════════════
$icone    = $tipo === 'receita' ? '💰' : '💸';
$valorFmt = 'R$ ' . number_format($valor, 2, ',', '.');

if ($parcelas > 1) {
    $parcelaFmt = 'R$ ' . number_format($valorParcela, 2, ',', '.');
    $msg = "{$icone} {$parcelas}x de {$parcelaFmt} em {$nomeCategoria} — {$desc}";
} else {
    $msg = "{$icone} {$valorFmt} em {$nomeCategoria} — {$desc}";
}
if ($catCriada) $msg .= ' (nova categoria criada 🏷️)';

echo json_encode([
    'success' => true,
    'message' => $msg,
    'dados'   => [
        'descricao'     => $desc,
        'valor'         => $valor,
        'valorParcela'  => $valorParcela,
        'parcelas'      => $parcelas,
        'data'          => $data,
        'tipo'          => $tipo,
        'categoria_nome'=> $nomeCategoria,
    ]
]);
?>