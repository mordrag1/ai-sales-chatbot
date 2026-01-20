# API смены тарифа

## POST /api/plan.php

Смена тарифа пользователя по email.

### Request

```http
POST /api/plan.php
Content-Type: application/json
```

```json
{
  "email": "user@example.com",
  "plan_id": "pro"
}
```

### Параметры

| Поле | Тип | Обязательный | Описание |
|------|-----|--------------|----------|
| email | string | да | Email пользователя |
| plan_id | string | да | ID тарифа: `demo`, `start`, `pro`, `max` |

### Тарифы

| plan_id | Название |
|---------|----------|
| `demo` | Бесплатный |
| `start` | Начальный |
| `pro` | Профессиональный |
| `max` | Максимальный |

### Response

**Успех (200):**
```json
{
  "success": true,
  "email": "user@example.com",
  "plan_id": "pro"
}
```

**Пользователь не найден (404):**
```json
{
  "success": false,
  "error": "User not found"
}
```

**Неверные данные (400):**
```json
{
  "success": false,
  "error": "Invalid plan_id. Valid: demo, start, pro, max"
}
```

### Пример cURL

```bash
curl -X POST https://api.weba-ai.com/api/plan.php \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "plan_id": "pro"}'
```

