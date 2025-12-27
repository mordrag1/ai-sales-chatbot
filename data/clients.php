<?php
/**
 * Карта клиентов и их серверов n8n.
 * Каждый бот идентифицируется уникальным ключом (botId).
 * Для быстрой локальной итерации возвращаем demo-ответ.
 */
return [
    'demo' => [
        'label' => 'Salesbot demo',
        'n8nWebhookUrl' => 'https://n8n.example.com/webhook/salesbot-demo',
        'demoResponse' => 'Привет! Я демонстрационный чат-бот. Напиши что-нибудь, и я отвечу тестовым сообщением.',
        'disabled' => false,
    ],
    'client-alpha' => [
        'label' => 'Client Alpha Sales Assistant',
        'n8nWebhookUrl' => 'https://n8n-client-alpha.weba-ai.com/webhook/sales',
        'demoResponse' => 'Client Alpha: привет! Этот ответ симулирует интеграцию с n8n.',
        'disabled' => false,
    ],
];

