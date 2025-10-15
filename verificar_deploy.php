<?php
header('Content-Type: application/json');

// Verificar se as funções estão no arquivo
$tarefasContent = file_get_contents('tarefas.php');

$checks = [
    'window.mostrarEstatisticas' => strpos($tarefasContent, 'window.mostrarEstatisticas') !== false,
    'window.toggleRotina' => strpos($tarefasContent, 'window.toggleRotina') !== false,
    'window.adicionarRotinaFixa' => strpos($tarefasContent, 'window.adicionarRotinaFixa') !== false,
    'window.editarRotina' => strpos($tarefasContent, 'window.editarRotina') !== false,
    'window.excluirRotina' => strpos($tarefasContent, 'window.excluirRotina') !== false,
];

$allOk = !in_array(false, $checks, true);

echo json_encode([
    'deploy_completo' => $allOk,
    'timestamp' => date('Y-m-d H:i:s'),
    'verificacoes' => $checks,
    'tamanho_arquivo' => strlen($tarefasContent) . ' bytes',
    'mensagem' => $allOk 
        ? '✅ Deploy completo! Funções encontradas no arquivo.' 
        : '❌ Deploy ainda não completou. Funções não encontradas no arquivo do servidor.'
], JSON_PRETTY_PRINT);
?>

