<?php
// includes/push_helper.php
// Versão Elite: Suporta Badges (iOS/Android), Ações e Caminhos Dinâmicos

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Envia notificaçao web push nativa
 * @param PDO $pdo Conexão banco
 * @param int $user_id ID do destinatário
 * @param string $title Título da notificação
 * @param string $body Mensagem
 * @param string $url URL para abrir ao clicar
 * @param array $options Opções extras: [badge => int, actions => [[action=>str, title=>str, icon=>str]]]
 */
function sendWebPush($pdo, $user_id, $title, $body, $url = 'dashboard.php', $options = []) {
    $configFile = __DIR__ . '/config_push.php';
    if (!file_exists($configFile)) return false;
    require_once $configFile;

    if (!defined('VAPID_PUBLIC_KEY')) return false;

    // Tentar descobrir a URL base do site para os ícones (evitar caminhos quebrados no mobile)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . $host . '/seu_projeto/'; // Ajuste manual se necessário ou automatize

    $auth = [
        'VAPID' => [
            'subject' => VAPID_SUBJECT,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    try {
        $webPush = new WebPush($auth);
        
        $stmt = $pdo->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subscriptions)) return false;

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => strpos($url, 'http') === 0 ? $url : $baseUrl . ltrim($url, '/'),
            'icon' => $baseUrl . 'assets/img/icon_192.png',
            'badge_icon' => $baseUrl . 'assets/img/badge_96.png', // Ícone que aparece na barra de status (Android)
            'badge_count' => $options['badge'] ?? 1, // Número que aparece no ícone do App (iOS/Android)
            'actions' => $options['actions'] ?? [
                ['action' => 'open', 'title' => 'Ver Agora']
            ],
            'timestamp' => time() * 1000
        ]);

        foreach ($subscriptions as $subData) {
            $subscription = Subscription::create([
                'endpoint' => $subData['endpoint'],
                'publicKey' => $subData['p256dh'],
                'authToken' => $subData['auth'],
            ]);

            $webPush->queueNotification($subscription, $payload);
        }

        $results = [];
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                if ($report->isSubscriptionExpired()) {
                    $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$report->getEndpoint()]);
                }
                error_log("[Push] Erro: " . $report->getReason());
            }
            $results[] = $report->isSuccess();
        }

        return [
            'success' => in_array(true, $results),
            'sent' => count(array_filter($results)),
            'failed' => count(array_filter($results, fn($v) => !$v))
        ];

    } catch (Exception $e) {
        error_log('[Push] Erro fatal: ' . $e->getMessage());
        return false;
    }
}
