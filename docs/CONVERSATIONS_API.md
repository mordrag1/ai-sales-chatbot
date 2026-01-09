# Conversations API Specification

## Overview

API для получения и управления диалогами (чатами) конкретного бота. Позволяет просматривать все диалоги, фильтровать и получать полную историю переписки.

---

## Base URL

```
https://cdn.weba-ai.com/api/
```

---

## Authentication

Все запросы требуют заголовок `X-Auth-Token` с действующим токеном авторизации пользователя.

```http
X-Auth-Token: your_auth_token_here
```

---

## Endpoints

### 1. Получить список всех диалогов бота

```http
GET /api/conversations.php?bot_hash={bot_hash}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `bot_hash` | string | ✅ Yes | Уникальный 32-символьный хеш бота |
| `limit` | integer | No | Количество записей (default: 50, max: 100) |
| `offset` | integer | No | Смещение для пагинации (default: 0) |
| `sort` | string | No | Сортировка: `newest` (default) или `oldest` |

#### Example Request

```bash
curl -X GET "https://cdn.weba-ai.com/api/conversations.php?bot_hash=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6&limit=20&sort=newest" \
  -H "X-Auth-Token: your_token_here"
```

#### Success Response (200)

```json
{
  "success": true,
  "bot_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
  "conversations": [
    {
      "id": 123,
      "user_id": "v-lxyz123-abc456",
      "message_count": 8,
      "last_message": {
        "role": "assistant",
        "text": "Thank you for your interest! Our pricing starts at...",
        "timestamp": 1736438400000
      },
      "page_url": "https://example.com/pricing",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)...",
      "last_message_at": "2025-01-09T14:00:00Z",
      "created_at": "2025-01-09T10:30:00Z"
    },
    {
      "id": 122,
      "user_id": "v-mno789-def012",
      "message_count": 3,
      "last_message": {
        "role": "user",
        "text": "How do I integrate the chatbot?",
        "timestamp": 1736434800000
      },
      "page_url": "https://example.com/docs",
      "user_agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)...",
      "last_message_at": "2025-01-09T13:00:00Z",
      "created_at": "2025-01-09T12:45:00Z"
    }
  ],
  "pagination": {
    "total": 156,
    "limit": 20,
    "offset": 0,
    "has_more": true
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `conversations` | array | Массив диалогов |
| `conversations[].id` | integer | ID диалога в базе |
| `conversations[].user_id` | string | Уникальный ID посетителя |
| `conversations[].message_count` | integer | Количество сообщений в диалоге |
| `conversations[].last_message` | object | Последнее сообщение (preview) |
| `conversations[].last_message.role` | string | `user` или `assistant` |
| `conversations[].last_message.text` | string | Текст (обрезан до 100 символов) |
| `conversations[].last_message.timestamp` | integer | Unix timestamp в миллисекундах |
| `conversations[].page_url` | string | URL страницы где был начат диалог |
| `conversations[].user_agent` | string | User-Agent браузера посетителя |
| `conversations[].last_message_at` | string | ISO 8601 дата последнего сообщения |
| `conversations[].created_at` | string | ISO 8601 дата создания диалога |
| `pagination.total` | integer | Общее количество диалогов |
| `pagination.limit` | integer | Текущий лимит |
| `pagination.offset` | integer | Текущее смещение |
| `pagination.has_more` | boolean | Есть ли ещё записи |

---

### 2. Получить конкретный диалог (полная история)

```http
GET /api/conversations.php?bot_hash={bot_hash}&user_id={user_id}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `bot_hash` | string | ✅ Yes | Уникальный 32-символьный хеш бота |
| `user_id` | string | ✅ Yes | ID посетителя (например `v-lxyz123-abc456`) |

#### Example Request

```bash
curl -X GET "https://cdn.weba-ai.com/api/conversations.php?bot_hash=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6&user_id=v-lxyz123-abc456" \
  -H "X-Auth-Token: your_token_here"
```

#### Success Response (200)

```json
{
  "success": true,
  "conversation": {
    "id": 123,
    "bot_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "client_id": "513724",
    "user_id": "v-lxyz123-abc456",
    "dialog": [
      {
        "role": "assistant",
        "text": "Hello! How can I help you today?",
        "id": "1736430000-abc123",
        "timestamp": 1736430000000
      },
      {
        "role": "user",
        "text": "What is your pricing?",
        "id": "1736430060-def456",
        "timestamp": 1736430060000
      },
      {
        "role": "assistant",
        "text": "Our pricing starts at $19/month for the Start plan...",
        "id": "1736430065-ghi789",
        "timestamp": 1736430065000
      }
    ],
    "message_count": 3,
    "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "ip_address": "192.168.1.100",
    "referrer": "https://google.com",
    "page_url": "https://example.com/pricing",
    "last_message_at": "2025-01-09T14:01:05Z",
    "created_at": "2025-01-09T14:00:00Z"
  }
}
```

#### Dialog Message Fields

| Field | Type | Description |
|-------|------|-------------|
| `dialog[].role` | string | Роль: `user` или `assistant` |
| `dialog[].text` | string | Полный текст сообщения |
| `dialog[].id` | string | Уникальный ID сообщения |
| `dialog[].timestamp` | integer | Unix timestamp в миллисекундах |

---

## Error Responses

### 401 Unauthorized

```json
{
  "error": "Authentication required",
  "code": "AUTH_REQUIRED"
}
```

```json
{
  "error": "Invalid or expired token",
  "code": "INVALID_TOKEN"
}
```

### 400 Bad Request

```json
{
  "error": "bot_hash is required"
}
```

### 404 Not Found

```json
{
  "error": "Bot not found or access denied",
  "code": "BOT_NOT_FOUND"
}
```

```json
{
  "error": "Conversation not found",
  "code": "CONVERSATION_NOT_FOUND"
}
```

### 405 Method Not Allowed

```json
{
  "error": "Method not allowed"
}
```

---

## Usage Examples

### JavaScript/TypeScript

```typescript
const API_BASE = 'https://cdn.weba-ai.com/api';

class ConversationsClient {
  constructor(private token: string) {}

  async listConversations(botHash: string, options?: {
    limit?: number;
    offset?: number;
    sort?: 'newest' | 'oldest';
  }) {
    const params = new URLSearchParams({
      bot_hash: botHash,
      ...(options?.limit && { limit: String(options.limit) }),
      ...(options?.offset && { offset: String(options.offset) }),
      ...(options?.sort && { sort: options.sort }),
    });

    const response = await fetch(`${API_BASE}/conversations.php?${params}`, {
      headers: { 'X-Auth-Token': this.token },
    });

    if (!response.ok) throw new Error('Failed to fetch conversations');
    return response.json();
  }

  async getConversation(botHash: string, userId: string) {
    const params = new URLSearchParams({
      bot_hash: botHash,
      user_id: userId,
    });

    const response = await fetch(`${API_BASE}/conversations.php?${params}`, {
      headers: { 'X-Auth-Token': this.token },
    });

    if (!response.ok) throw new Error('Failed to fetch conversation');
    return response.json();
  }
}

// Usage
const client = new ConversationsClient('your_auth_token');

// Get all conversations for a bot
const { conversations, pagination } = await client.listConversations(
  'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
  { limit: 20, sort: 'newest' }
);

// Get specific conversation
const { conversation } = await client.getConversation(
  'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
  'v-lxyz123-abc456'
);

console.log(conversation.dialog); // Full chat history
```

### Python

```python
import requests

API_BASE = 'https://cdn.weba-ai.com/api'
TOKEN = 'your_auth_token'

headers = {'X-Auth-Token': TOKEN}

# List all conversations
response = requests.get(
    f'{API_BASE}/conversations.php',
    params={
        'bot_hash': 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
        'limit': 20,
        'sort': 'newest'
    },
    headers=headers
)
data = response.json()

for conv in data['conversations']:
    print(f"User: {conv['user_id']}, Messages: {conv['message_count']}")

# Get specific conversation
response = requests.get(
    f'{API_BASE}/conversations.php',
    params={
        'bot_hash': 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
        'user_id': 'v-lxyz123-abc456'
    },
    headers=headers
)
conversation = response.json()['conversation']

for msg in conversation['dialog']:
    print(f"[{msg['role']}]: {msg['text']}")
```

### cURL

```bash
# List conversations
curl -X GET "https://cdn.weba-ai.com/api/conversations.php?bot_hash=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6&limit=10" \
  -H "X-Auth-Token: your_token"

# Get specific conversation  
curl -X GET "https://cdn.weba-ai.com/api/conversations.php?bot_hash=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6&user_id=v-lxyz123-abc456" \
  -H "X-Auth-Token: your_token"
```

---

## Pagination Example

Для получения всех диалогов используйте пагинацию:

```typescript
async function getAllConversations(botHash: string) {
  const allConversations = [];
  let offset = 0;
  const limit = 100;
  
  while (true) {
    const { conversations, pagination } = await client.listConversations(botHash, {
      limit,
      offset,
    });
    
    allConversations.push(...conversations);
    
    if (!pagination.has_more) break;
    offset += limit;
  }
  
  return allConversations;
}
```

---

## Rate Limits

- Максимум 100 запросов в минуту на токен
- Максимум 100 записей за один запрос (параметр `limit`)

---

## Security Notes

1. Токен авторизации привязан к пользователю
2. Пользователь может получить только диалоги своих ботов
3. IP-адреса посетителей доступны только владельцу бота
4. Токены истекают — используйте refresh при необходимости

---

## Changelog

### v1.0 (2025-01-09)
- Initial release
- List conversations with pagination
- Get full conversation dialog
- Authentication via X-Auth-Token

