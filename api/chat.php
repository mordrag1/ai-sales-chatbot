<?php
declare(strict_types=1);

/**
 * Lightweight proxy that routes chat requests to the proper n8n logic per botId.
 * You can add an HTTP client, scheduler, cache, and logging for production use.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '{}', true) ?? [];

if ($payload === []) {
    $payload = $_POST;
}

$botId = $payload['botId'] ?? 'demo';
$userId = $payload['userId'] ?? 'guest';
$userMessage = trim((string) ($payload['message'] ?? ''));

$clients = require __DIR__ . '/../data/clients.php';
$client = $clients[$botId] ?? $clients['demo'];

$responseText = $client['demoResponse'] ?? 'The bot is not configured yet.';

$response = [
    'botId' => $botId,
    'userId' => $userId,
    'clientLabel' => $client['label'] ?? 'Unknown client',
    'n8nWebhookUrl' => $client['n8nWebhookUrl'] ?? null,
    'messages' => [],
];

/**
 * TODO: send the payload to $client['n8nWebhookUrl'], e.g.:
 * $http->post($client['n8nWebhookUrl'], ['json' => ['message' => $userMessage, 'userId' => $userId]]);
 */

if ($userMessage === '') {
    $response['messages'][] = [
        'role' => 'assistant',
        'text' => $responseText,
    ];
    $response['nextAction'] = 'request_input';
} else {
    $response['messages'][] = [
        'role' => 'assistant',
        'text' => $responseText,
    ];
    $response['messages'][] = [
        'role' => 'assistant',
        'text' => sprintf('This is a demo connection to %s', $response['n8nWebhookUrl']),
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

