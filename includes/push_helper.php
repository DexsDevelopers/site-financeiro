<?php
// Exemplo de helper para enviar Notificação Web Push para um usuário específico
// Você pode dar include('includes/push_helper.php') em qualquer canto e usar sendWebPush($user_id, $payload)

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendWebPush($pdo, $user_id, $title, $body, $url = '/', $icon = null) {
    // Tenta pegar as configurações
    $configFile = __DIR__ . '/config_push.php';
    if (!file_exists($configFile)) {
        error_log('Web Push: config_push.php não encontrado. Verifique a instalação VAPID.');
        return false;
    }
    require_once $configFile;

    if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY')) {
        error_log('Web Push: Chaves VAPID não definidas.');
        return false;
    }

    $auth = [
        'VAPID' => [
            'subject' => VAPID_SUBJECT,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    try {
        $webPush = new WebPush($auth);
        
        // Pega todos os endpoints válidos deste usuário (pode ter em vários dispositivos)
        $stmt = $pdo->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($subscriptions) === 0) {
            return false; // Usuário não tem nenhum dispositivo inscrito
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => $icon ?: '/assets/img/icon_192.png',
            'badge' => '/assets/img/badge_96.png'
        ]);

        $enviados = 0;
        $falhas = 0;

        // Envia para cada dispositivo que ele tem logado
        foreach ($subscriptions as $subData) {
            $subscription = Subscription::create([
                'endpoint' => $subData['endpoint'],
                'publicKey' => $subData['p256dh'],
                'authToken' => $subData['auth'],
            ]);

            $report = $webPush->sendOneNotification($subscription, $payload);

            if ($report->isSuccess()) {
                $enviados++;
            } else {
                $falhas++;
                error_log("Falha ao enviar Push para endpoint: {$report->getEndpoint()} - Motivo: {$report->getReason()}");

                // Se o erro indicar que o dispositivo cancelou inscrição / desinstalou, excluímos do banco
                if ($report->isSubscriptionExpired()) {
                    $delStmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE id = ?");
                    $delStmt->execute([$subData['id']]);
                }
            }
        }

        return ['success' => true, 'sent' => $enviados, 'failed' => $falhas];

    } catch (Exception $e) {
        error_log('Erro fatal no disparo Web Push: ' . $e->getMessage());
        return false;
    }
}
