<?php
require_once 'api/db.php';

$sql_file = 'database.sql';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação do Banco de Dados</title>
    <style>
        body { font-family: sans-serif; background: #000; color: #fff; padding: 2rem; }
        .success { color: #22c55e; border: 1px solid #22c55e; padding: 1rem; border-radius: 0.5rem; }
        .error { color: #ef4444; border: 1px solid #ef4444; padding: 1rem; border-radius: 0.5rem; }
    </style>
</head>
<body>
    <h1>Configuração do Banco de Dados</h1>
    
    <?php
    if (!file_exists($sql_file)) {
        echo '<div class="error">Erro: Arquivo ' . htmlspecialchars($sql_file) . ' não encontrado.</div>';
    } else {
        $sql = file_get_contents($sql_file);
        
        // Remove comentários e linhas vazias para evitar erros em alguns ambientes
        // (Opcional, mas multi_query geralmente lida bem se o arquivo estiver limpo)
        
        if ($conn->multi_query($sql)) {
            $count = 0;
            do {
                // Consumir resultados para liberar o buffer
                if ($result = $conn->store_result()) {
                    $result->free();
                }
                $count++;
            } while ($conn->next_result());
            
            echo '<div class="success">
                <h3>Sucesso!</h3>
                <p>O banco de dados foi importado/atualizado com sucesso.</p>
                <p>Processados ' . $count . ' comandos/blocos.</p>
                <p>Por segurança, delete o arquivo <strong>setup_db.php</strong> após o uso.</p>
            </div>';
            echo '<p><a href="/" style="color: #fff; text-decoration: underline;">Voltar para a Home</a></p>';
        } else {
            echo '<div class="error">
                <h3>Erro na importação:</h3>
                <p>' . htmlspecialchars($conn->error) . '</p>
            </div>';
        }
    }
    $conn->close();
    ?>
</body>
</html>
