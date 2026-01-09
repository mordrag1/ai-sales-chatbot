# WebA AI Chatbot - API Specification v2.0

## Overview

This document describes the complete API for the WebA AI Chatbot SaaS platform, including multi-bot support, subscription plans, usage limits, and domain restrictions.

---

## Base URL

```
https://cdn.weba-ai.com/api/
```

---

## Subscription Plans

| Plan ID | Name  | Max Bots | Messages/Month | Domain Restrictions | Price |
|---------|-------|----------|----------------|---------------------|-------|
| `demo`  | Demo  | 1        | 500            | `https://weba-ai.com` only | Free |
| `start` | Start | 1        | 1,000          | None | $19/mo |
| `pro`   | Pro   | 5        | 5,000          | None | $49/mo |
| `max`   | Max   | Unlimited | Unlimited     | None | $149/mo |

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `AUTH_REQUIRED` | 401 | Authentication token required |
| `INVALID_TOKEN` | 401 | Token is invalid or expired |
| `BOT_NOT_FOUND` | 404 | Bot with specified hash/ID not found |
| `BOT_LIMIT_REACHED` | 403 | Maximum number of bots for plan reached |
| `MESSAGE_LIMIT_REACHED` | 403 | Monthly message limit exceeded |
| `DOMAIN_NOT_ALLOWED` | 403 | Widget embedded on unauthorized domain |
| `PLAN_NOT_FOUND` | 404 | Specified plan does not exist |

### Error Response Format

```json
{
  "error": {
    "code": "MESSAGE_LIMIT_REACHED",
    "message": "Message limit reached (500/500 this month). Upgrade your plan to continue.",
    "upgradeUrl": "https://weba-ai.com/dashboard"
  }
}
```

---

## Authentication

### Register User

```http
POST /api/auth.php?action=register
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "securepassword123",
  "name": "John Doe"
}
```

**Response (201):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "John Doe",
    "client_id": "abc123",
    "plan_id": "demo"
  },
  "token": "auth_token_here",
  "expires_at": "2025-02-09T12:00:00Z"
}
```

### Login

```http
POST /api/auth.php?action=login
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "securepassword123"
}
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "John Doe",
    "client_id": "abc123",
    "plan_id": "start"
  },
  "token": "new_auth_token",
  "expires_at": "2025-02-09T12:00:00Z"
}
```

### Verify Token

```http
POST /api/auth.php?action=verify
X-Auth-Token: your_auth_token
```

**Response (200):**
```json
{
  "valid": true,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "John Doe",
    "plan_id": "start"
  }
}
```

### Logout

```http
POST /api/auth.php?action=logout
X-Auth-Token: your_auth_token
```

---

## Bot Management

All bot endpoints require `X-Auth-Token` header.

### List User's Bots

```http
GET /api/bots.php
X-Auth-Token: your_auth_token
```

**Response (200):**
```json
{
  "success": true,
  "bots": [
    {
      "id": 1,
      "bot_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
      "name": "Sales Bot",
      "widget_title": "Support",
      "widget_operator_label": "Operator Online",
      "widget_welcome": "Hello! How can I help?",
      "widget_placeholder": "Type your message...",
      "widget_typing_label": "Operator typing...",
      "widget_sound_enabled": true,
      "allowed_domains": null,
      "dataset": [...],
      "n8n_webhook_url": "https://...",
      "is_active": true,
      "embed_code": "<script src=\"https://cdn.weba-ai.com/widget.php?h=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6\"></script>",
      "messages_this_month": 145,
      "created_at": "2025-01-09T10:00:00Z",
      "updated_at": "2025-01-09T12:00:00Z"
    }
  ],
  "count": 1,
  "plan": {
    "id": "start",
    "name": "Start",
    "max_bots": 1,
    "max_messages_per_month": 1000
  },
  "can_create_more": false
}
```

### Get Single Bot

```http
GET /api/bots.php?id=1
X-Auth-Token: your_auth_token
```

**Response (200):**
```json
{
  "success": true,
  "bot": { ... },
  "usage": {
    "messages_this_month": 145,
    "limit": 1000
  }
}
```

### Create Bot

```http
POST /api/bots.php
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "My New Bot",
  "widget_title": "Customer Support",
  "widget_operator_label": "Agent Online",
  "widget_welcome": "Welcome! How can I assist you today?",
  "widget_placeholder": "Type here...",
  "widget_typing_label": "Agent is typing...",
  "widget_sound_enabled": true,
  "allowed_domains": ["https://mysite.com", "https://www.mysite.com"],
  "n8n_webhook_url": "https://n8n.example.com/webhook/abc123"
}
```

**Response (201):**
```json
{
  "success": true,
  "bot": {
    "id": 2,
    "bot_hash": "q1w2e3r4t5y6u7i8o9p0a1s2d3f4g5h6",
    ...
  },
  "embed_code": "<script src=\"https://cdn.weba-ai.com/widget.php?h=q1w2e3r4t5y6u7i8o9p0a1s2d3f4g5h6\"></script>"
}
```

**Error (403) - Bot Limit Reached:**
```json
{
  "error": "Bot limit reached",
  "code": "BOT_LIMIT_REACHED",
  "current": 1,
  "limit": 1,
  "upgrade_url": "https://weba-ai.com/dashboard"
}
```

### Update Bot

```http
PUT /api/bots.php?id=1
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request Body (partial update):**
```json
{
  "name": "Updated Bot Name",
  "widget_welcome": "New welcome message!",
  "dataset": [
    {"type": "text", "content": "Training data here..."}
  ]
}
```

### Delete Bot

```http
DELETE /api/bots.php?id=1
X-Auth-Token: your_auth_token
```

**Response (200):**
```json
{
  "success": true,
  "message": "Bot deleted successfully"
}
```

---

## Plans API

### List All Plans

```http
GET /api/plans.php
```

**Response (200):**
```json
{
  "success": true,
  "plans": [
    {
      "id": "demo",
      "name": "Demo",
      "max_bots": 1,
      "max_messages_per_month": 500,
      "allowed_domains": ["https://weba-ai.com"],
      "price_monthly": 0,
      "features": {"support": "community"}
    },
    {
      "id": "start",
      "name": "Start",
      "max_bots": 1,
      "max_messages_per_month": 1000,
      "allowed_domains": null,
      "price_monthly": 19,
      "features": {"support": "email"}
    },
    {
      "id": "pro",
      "name": "Pro",
      "max_bots": 5,
      "max_messages_per_month": 5000,
      "allowed_domains": null,
      "price_monthly": 49,
      "features": {"support": "priority"}
    },
    {
      "id": "max",
      "name": "Max",
      "max_bots": null,
      "max_messages_per_month": null,
      "allowed_domains": null,
      "price_monthly": 149,
      "features": {"support": "dedicated"}
    }
  ]
}
```

### Get Current User's Plan & Usage

```http
GET /api/plans.php?current=1
X-Auth-Token: your_auth_token
```

**Response (200):**
```json
{
  "success": true,
  "plan": {
    "id": "start",
    "name": "Start",
    "max_bots": 1,
    "max_messages_per_month": 1000,
    "allowed_domains": null,
    "price_monthly": 19
  },
  "usage": {
    "messages_used": 450,
    "messages_limit": 1000,
    "messages_remaining": 550,
    "messages_percent": 45.0,
    "bots_count": 1,
    "bots_limit": 1,
    "bots_remaining": 0
  },
  "expires_at": "2025-02-09T00:00:00Z",
  "limits_reached": {
    "messages": false,
    "bots": true
  }
}
```

---

## Usage Tracking API

### Check Bot Usage

```http
GET /api/usage.php?bot_hash=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

**Response (200):**
```json
{
  "success": true,
  "bot_id": 1,
  "user_id": 1,
  "year_month": "2025-01",
  "usage": {
    "bot_messages": 145,
    "total_messages": 145,
    "limit": 1000,
    "remaining": 855,
    "limit_reached": false,
    "percent_used": 14.5
  }
}
```

### Quick Limit Check

```http
GET /api/usage.php?check=1&bot_hash=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

**Response (200):**
```json
{
  "allowed": true,
  "limit_reached": false,
  "messages_used": 145,
  "messages_limit": 1000
}
```

### Increment Message Count

```http
POST /api/usage.php
Content-Type: application/json
```

**Request Body:**
```json
{
  "bot_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
  "count": 1
}
```

**Response (200):**
```json
{
  "success": true,
  "allowed": true,
  "messages_used": 146,
  "messages_limit": 1000,
  "remaining": 854
}
```

**Error (403) - Limit Reached:**
```json
{
  "error": {
    "code": "MESSAGE_LIMIT_REACHED",
    "message": "Message limit reached (1000/1000 this month). Upgrade your plan to continue.",
    "upgradeUrl": "https://weba-ai.com/dashboard"
  },
  "allowed": false,
  "messages_used": 1000,
  "messages_limit": 1000
}
```

---

## Dataset Management

### Get Bot Dataset

```http
GET /api/dataset.php?client_id=abc123
X-API-Key: your_api_key
```

or with bot_id for new system:

```http
GET /api/dataset.php?bot_id=1
X-Auth-Token: your_auth_token
```

**Response (200):**
```json
{
  "success": true,
  "client_id": "abc123",
  "dataset": [
    "You are an AI sales assistant for WebA AI Chatbot...",
    {"type": "faq", "question": "What is WebA?", "answer": "..."}
  ],
  "count": 2
}
```

### Add to Dataset

```http
POST /api/dataset.php
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request Body:**
```json
{
  "bot_id": 1,
  "items": [
    "New training text...",
    {"type": "faq", "question": "...", "answer": "..."}
  ]
}
```

### Update Dataset Item

```http
PUT /api/dataset.php
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request Body:**
```json
{
  "bot_id": 1,
  "index": 0,
  "item": "Updated training text..."
}
```

### Delete Dataset Item

```http
DELETE /api/dataset.php
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request Body:**
```json
{
  "bot_id": 1,
  "index": 0
}
```

---

## Widget Embedding

### Embed Code

```html
<script src="https://cdn.weba-ai.com/widget.php?h=YOUR_BOT_HASH"></script>
```

### Widget Behavior

1. **Domain Check**: Widget verifies if current domain is allowed based on plan/bot settings
2. **Message Limits**: Each user message is counted against monthly limit
3. **Error Banner**: When limit is reached or domain is not allowed, a red banner appears with upgrade link
4. **Developer Link**: Footer shows "Powered by WebA AI" with link to https://weba-ai.com/

### Error Banner Display

When widget is blocked due to limits or domain restrictions:

```
┌─────────────────────────────────────┐
│ ⚠️ Message limit reached            │
│ (500/500 this month).               │
│ Upgrade Plan                        │
└─────────────────────────────────────┘
```

---

## Database Schema

### Users Table

```sql
CREATE TABLE users (
  id INT unsigned PRIMARY KEY AUTO_INCREMENT,
  client_id VARCHAR(32) UNIQUE NOT NULL,
  email VARCHAR(191) UNIQUE NOT NULL,
  password_hash VARCHAR(255),
  name VARCHAR(128),
  plan_id VARCHAR(64) DEFAULT 'demo',
  plan_expires_at TIMESTAMP NULL,
  auth_token VARCHAR(64),
  token_expires_at TIMESTAMP NULL,
  api_key CHAR(32),
  status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Plans Table

```sql
CREATE TABLE plans (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  max_bots INT unsigned DEFAULT 1,
  max_messages_per_month INT unsigned NULL,  -- NULL = unlimited
  allowed_domains JSON NULL,                  -- NULL = any domain
  price_monthly DECIMAL(10, 2) DEFAULT 0.00,
  features JSON NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Bots Table

```sql
CREATE TABLE bots (
  id INT unsigned PRIMARY KEY AUTO_INCREMENT,
  user_id INT unsigned NOT NULL,
  bot_hash CHAR(32) UNIQUE NOT NULL,
  name VARCHAR(128) DEFAULT 'My Bot',
  widget_title VARCHAR(128) DEFAULT 'Support',
  widget_operator_label VARCHAR(128) DEFAULT 'Operator Online',
  widget_welcome TEXT NULL,
  widget_placeholder VARCHAR(128) DEFAULT 'Type your message...',
  widget_typing_label VARCHAR(128) DEFAULT 'Operator typing...',
  widget_sound_enabled TINYINT(1) DEFAULT 1,
  allowed_domains JSON NULL,
  dataset JSON NULL,
  n8n_webhook_url VARCHAR(512) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Message Usage Table

```sql
CREATE TABLE message_usage (
  id INT unsigned PRIMARY KEY AUTO_INCREMENT,
  user_id INT unsigned NOT NULL,
  bot_id INT unsigned NOT NULL,
  year_month CHAR(7) NOT NULL,  -- Format: YYYY-MM
  message_count INT unsigned DEFAULT 0,
  last_message_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (user_id, bot_id, year_month),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
);
```

---

## n8n Integration

### Webhook Request from Widget

When user sends message, widget calls your n8n webhook:

```json
{
  "botId": 1,
  "botHash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
  "clientId": "abc123",
  "userId": "v-lxyz123-abc456",
  "message": "Hello, I have a question about pricing"
}
```

### Getting Dataset in n8n

To get the training data for AI context:

```http
POST /api/dataset-query.php
Content-Type: application/json
```

**Request:**
```json
{
  "client_id": "abc123",
  "question": "What is your pricing?"
}
```

**Response (text/plain):**
```
dataset:

*** You are an AI sales assistant for WebA AI Chatbot...

question: *** What is your pricing?
```

### Pushing Response from n8n

```http
POST /api/push.php
Content-Type: application/json
```

**Request:**
```json
{
  "clientId": "abc123",
  "userId": "v-lxyz123-abc456",
  "text": "Our pricing starts at $19/month for the Start plan...",
  "role": "assistant"
}
```

---

## Frontend Integration (Lovable)

### TypeScript Client Example

```typescript
const API_BASE = 'https://cdn.weba-ai.com/api';

interface Bot {
  id: number;
  bot_hash: string;
  name: string;
  widget_title: string;
  messages_this_month: number;
  embed_code: string;
}

interface PlanUsage {
  messages_used: number;
  messages_limit: number | null;
  bots_count: number;
  bots_limit: number | null;
}

class WebAClient {
  private token: string;

  constructor(token: string) {
    this.token = token;
  }

  private async request(endpoint: string, options: RequestInit = {}) {
    const response = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-Auth-Token': this.token,
        ...options.headers,
      },
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.error?.message || data.error || 'Request failed');
    }
    
    return data;
  }

  async listBots(): Promise<{ bots: Bot[]; can_create_more: boolean }> {
    return this.request('/bots.php');
  }

  async createBot(bot: Partial<Bot>): Promise<{ bot: Bot; embed_code: string }> {
    return this.request('/bots.php', {
      method: 'POST',
      body: JSON.stringify(bot),
    });
  }

  async updateBot(id: number, updates: Partial<Bot>): Promise<{ bot: Bot }> {
    return this.request(`/bots.php?id=${id}`, {
      method: 'PUT',
      body: JSON.stringify(updates),
    });
  }

  async deleteBot(id: number): Promise<void> {
    return this.request(`/bots.php?id=${id}`, { method: 'DELETE' });
  }

  async getPlanUsage(): Promise<{ plan: any; usage: PlanUsage }> {
    return this.request('/plans.php?current=1');
  }

  async updateDataset(botId: number, dataset: any[]): Promise<void> {
    return this.request('/bots.php?id=' + botId, {
      method: 'PUT',
      body: JSON.stringify({ dataset }),
    });
  }

  async uploadFile(file: File): Promise<{ content: string }> {
    const formData = new FormData();
    formData.append('file', file);
    
    const response = await fetch(`${API_BASE}/upload.php`, {
      method: 'POST',
      headers: { 'X-Auth-Token': this.token },
      body: formData,
    });
    
    return response.json();
  }
}
```

---

## Deployment Checklist

1. **Run SQL Migration:**
   ```bash
   mysql -u aicdn -p aicdn < sql/migrate_multi_bot_plans.sql
   ```

2. **Verify Plans Data:**
   ```sql
   SELECT * FROM plans;
   ```

3. **Test Bot Creation:**
   - Create a bot
   - Get embed code
   - Test on allowed domain

4. **Test Limits:**
   - Send messages until limit reached
   - Verify error banner appears
   - Verify upgrade link works

5. **Test Domain Restrictions (Demo Plan):**
   - Try embedding on non-weba-ai.com domain
   - Verify error banner shows

---

## Changelog

### v2.0 (2025-01-09)
- Added multi-bot support
- Added subscription plans with limits
- Added domain restrictions for demo plan
- Added message usage tracking
- Added error banners for limit violations
- Added developer link in widget footer
- Updated all APIs for new architecture

