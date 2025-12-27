<?php
/**
 * Client map for matching each botId to the correct n8n webhook.
 */
return [
    'demo' => [
        'label' => 'Salesbot Demo',
        'n8nWebhookUrl' => 'https://n8n.example.com/webhook/salesbot-demo',
        'demoResponse' => 'Hello! I am a demonstration chatbot. Ask me anything to receive a sample reply.',
        'disabled' => false,
    ],
    'client-alpha' => [
        'label' => 'Client Alpha Sales Assistant',
        'n8nWebhookUrl' => 'https://n8n-client-alpha.weba-ai.com/webhook/sales',
        'demoResponse' => 'Client Alpha: hello! This message simulates an n8n-powered response.',
        'disabled' => false,
    ],
];

