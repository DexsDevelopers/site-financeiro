<?php
// /admin/enviar_notificacao_onesignal.php
require_once '../includes/db_connect.php';

$app_id = ONESIGNAL_APP_ID;
$rest_api_key = "8b948d38-c99d-402b-a456-e99e66fcc60f"; // Você pega essa chave no painel da OneSignal

$fields = [
    'app_id' => $app_id,
    'included_segments' => ['Subscribed Users'], // Envia para todos os inscritos
    'headings' => ['pt' => 'Nova Atualização no Painel!'],
    'contents' => ['pt' => 'Adicionamos a funcionalidade de Relatórios. Venha conferir!'],
    'web_url' => 'https://gold-quail-250128.hostingersite.com/seu_projeto/relatorios.php'
];

$fields = json_encode($fields);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8', 'Authorization: Basic ' . $rest_api_key]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

$response = curl_exec($ch);
curl_close($ch);

echo "Notificação enviada! Resposta da API: " . $response;
?>