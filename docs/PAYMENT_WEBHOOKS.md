# Payment Webhooks Specification

## Обзор

Система интеграции с Polar.sh для управления подписками пользователей.

## Тарифные планы

| Plan ID | Название | Описание |
|---------|----------|----------|
| `demo`  | Demo     | Бесплатный тариф по умолчанию для новых пользователей |
| `start` | Start    | Начальный платный тариф |
| `pro`   | Pro      | Профессиональный тариф |
| `max`   | Max      | Максимальный тариф |

## Эндпоинты API

### POST /api/webhook.php

Обработчик вебхуков от Polar.sh.

**URL:** `https://api.weba-ai.com/api/webhook.php`

**Headers:**
```
Content-Type: application/json
webhook-id: <webhook_id>
webhook-timestamp: <timestamp>
webhook-signature: <signature>
```

**Поддерживаемые события:**

#### 1. `order.paid`
Срабатывает при успешной оплате заказа (одноразовый платеж или первая оплата подписки).

```json
{
  "type": "order.paid",
  "data": {
    "id": "order_xxx",
    "customer": {
      "id": "customer_xxx",
      "email": "user@example.com",
      "external_id": "user_client_id"
    },
    "product": {
      "id": "product_xxx",
      "name": "Pro Plan"
    },
    "subscription_id": "sub_xxx",
    "amount": 1990,
    "currency": "usd"
  }
}
```

**Действие:** Обновить `plan_id` пользователя на основе `product.name` или `product.id`.

#### 2. `subscription.created`
Срабатывает при создании подписки.

```json
{
  "type": "subscription.created",
  "data": {
    "id": "sub_xxx",
    "status": "active",
    "customer": {
      "id": "customer_xxx",
      "email": "user@example.com",
      "external_id": "user_client_id"
    },
    "product": {
      "id": "product_xxx",
      "name": "Pro Plan"
    },
    "current_period_start": "2026-01-18T00:00:00Z",
    "current_period_end": "2026-02-18T00:00:00Z"
  }
}
```

**Действие:** Записать подписку в БД и обновить `plan_id` пользователя.

#### 3. `subscription.updated`
Срабатывает при изменении подписки (смена плана, отмена, возобновление).

```json
{
  "type": "subscription.updated",
  "data": {
    "id": "sub_xxx",
    "status": "active|canceled|past_due",
    "customer": {
      "id": "customer_xxx",
      "email": "user@example.com",
      "external_id": "user_client_id"
    },
    "product": {
      "id": "product_xxx",
      "name": "Pro Plan"
    },
    "cancel_at_period_end": false
  }
}
```

**Действие:** 
- Если `status` = `active` — обновить `plan_id` пользователя.
- Если `status` = `canceled` — откатить на `demo` после окончания периода.

#### 4. `subscription.canceled`
Срабатывает при полной отмене подписки.

```json
{
  "type": "subscription.canceled",
  "data": {
    "id": "sub_xxx",
    "customer": {
      "email": "user@example.com",
      "external_id": "user_client_id"
    }
  }
}
```

**Действие:** Откатить `plan_id` пользователя на `demo`.

---

## Маппинг продуктов на планы

Настройка в `data/plans.php`:

```php
return [
    'products' => [
        'product_start_xxx' => 'start',
        'product_pro_xxx'   => 'pro',
        'product_max_xxx'   => 'max',
    ],
    'names' => [
        'Start Plan' => 'start',
        'Pro Plan'   => 'pro',
        'Max Plan'   => 'max',
    ],
];
```

## Идентификация пользователя

Приоритет поиска пользователя:
1. `customer.external_id` → `users.client_id`
2. `customer.email` → `users.email`

**Важно:** При создании checkout-сессии на лендинге передавать `customer_external_id` равный `client_id` пользователя.

## Безопасность

### Верификация подписи вебхука

Polar.sh подписывает вебхуки с использованием HMAC-SHA256.

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? '';
$webhookId = $_SERVER['HTTP_WEBHOOK_ID'] ?? '';

$signedContent = "{$webhookId}.{$timestamp}.{$payload}";
$expectedSignature = base64_encode(hash_hmac('sha256', $signedContent, $webhookSecret, true));

// Сравнить $signature с $expectedSignature
```

## Ответы API

### Успех

```json
{
  "success": true,
  "message": "Webhook processed"
}
```

HTTP Status: `200 OK`

### Ошибки

```json
{
  "success": false,
  "error": "Invalid signature"
}
```

HTTP Status: `400 Bad Request` или `401 Unauthorized`

---

## Логирование

Все вебхуки логируются в таблицу `webhook_logs`:

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Primary key |
| event_type | VARCHAR(64) | Тип события |
| event_id | VARCHAR(128) | ID события от Polar |
| payload | JSON | Полный payload |
| status | ENUM | success, error |
| error_message | TEXT | Сообщение об ошибке |
| created_at | TIMESTAMP | Время получения |

---

## Интеграция с лендингом

На лендинге при инициализации оплаты использовать Polar API:

```javascript
// POST https://api.polar.sh/v1/checkouts/custom/
{
  "product_id": "product_pro_xxx",
  "customer_email": "user@example.com",
  "customer_external_id": "user_client_id",  // Важно для идентификации!
  "success_url": "https://weba-ai.com/success",
  "metadata": {
    "source": "landing"
  }
}
```


