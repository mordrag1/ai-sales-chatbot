# Technical Specification: AI Chatbot SaaS Platform

## 1. Overview

This document describes the complete technical specification for the AI Chatbot SaaS platform. The system allows clients to create, train, and deploy AI chatbots on their websites using a simple widget integration.

### Architecture

```
┌─────────────────────┐     ┌─────────────────────┐     ┌─────────────────────┐
│   Frontend (Lovable)│────▶│   CDN Backend (PHP) │────▶│   n8n (AI Logic)    │
│   - User Dashboard  │     │   - API Endpoints   │     │   - Workflows       │
│   - Widget Settings │     │   - Widget Delivery │     │   - AI Assistants   │
│   - Dataset Mgmt    │     │   - MySQL Database  │     │   - Webhooks        │
└─────────────────────┘     └─────────────────────┘     └─────────────────────┘
```

### Base URLs

| Service | URL |
|---------|-----|
| CDN Backend API | `https://cdn.weba-ai.com/api/` |
| Widget Script | `https://cdn.weba-ai.com/widget.php` |
| n8n Webhook | `https://gicujedrotan.beget.app/webhook/` |

---

## 2. User Authentication API

All protected endpoints require authentication via `X-Auth-Token` header.

### 2.1 Register New User

**Endpoint:** `POST /api/auth.php?action=register`

**Request:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123",
  "name": "John Doe"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "user": {
    "id": 123,
    "client_id": "456789",
    "email": "user@example.com",
    "name": "John Doe",
    "widget_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "api_key": "abc123def456..."
  },
  "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_at": "2026-02-08 12:00:00"
}
```

**Errors:**
- `400` - Invalid email format / Password too short
- `409` - Email already registered

---

### 2.2 Login

**Endpoint:** `POST /api/auth.php?action=login`

**Request:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 123,
    "client_id": "456789",
    "email": "user@example.com",
    "name": "John Doe",
    "widget_hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "api_key": "abc123def456...",
    "plan_id": "starter"
  },
  "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_at": "2026-02-08 12:00:00"
}
```

**Errors:**
- `400` - Email and password required
- `401` - Invalid credentials

---

### 2.3 Verify Token

**Endpoint:** `POST /api/auth.php?action=verify`

**Headers:**
```
X-Auth-Token: your_auth_token
```

**Response (valid):**
```json
{
  "success": true,
  "valid": true,
  "user": {
    "id": 123,
    "client_id": "456789",
    "email": "user@example.com",
    "name": "John Doe"
  }
}
```

**Response (invalid):**
```json
{
  "error": "Invalid or expired token",
  "valid": false
}
```

---

### 2.4 Refresh Token

**Endpoint:** `POST /api/auth.php?action=refresh`

**Headers:**
```
X-Auth-Token: current_auth_token
```

**Response:**
```json
{
  "success": true,
  "auth_token": "new_token...",
  "expires_at": "2026-02-08 12:00:00"
}
```

---

### 2.5 Logout

**Endpoint:** `POST /api/auth.php?action=logout`

**Headers:**
```
X-Auth-Token: your_auth_token
```

**Response:**
```json
{
  "success": true,
  "message": "Logged out"
}
```

---

## 3. User Profile API

### 3.1 Get User Profile

**Endpoint:** `GET /api/user.php`

**Headers:**
```
X-Auth-Token: your_auth_token
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 123,
    "client_id": "456789",
    "email": "user@example.com",
    "name": "John Doe",
    "plan_id": "starter",
    "api_key": "abc123...",
    "widget_hash": "a1b2c3d4...",
    "dataset_count": 5,
    "created_at": "2026-01-01 00:00:00"
  }
}
```

---

### 3.2 Update User Profile

**Endpoint:** `PUT /api/user.php`

**Headers:**
```
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request:**
```json
{
  "name": "New Name",
  "password": "newPassword123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Profile updated"
}
```

---

### 3.3 Get Widget Settings

**Endpoint:** `GET /api/user.php?action=widget`

**Headers:**
```
X-Auth-Token: your_auth_token
```

**Response:**
```json
{
  "success": true,
  "widget": {
    "hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "title": "Support Assistant",
    "operator_label": "Operator Online",
    "welcome": "Hello! How can I help you?",
    "placeholder": "Type your message...",
    "typing_label": "Operator typing...",
    "sound_enabled": true
  },
  "embed_code": "<script src=\"https://cdn.weba-ai.com/widget.php?h=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6\"></script>"
}
```

---

### 3.4 Update Widget Settings

**Endpoint:** `PUT /api/user.php?action=widget`

**Headers:**
```
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request:**
```json
{
  "title": "My AI Assistant",
  "operator_label": "AI Assistant",
  "welcome": "Welcome! I'm your AI assistant. How can I help?",
  "placeholder": "Ask me anything...",
  "typing_label": "Thinking...",
  "sound_enabled": false
}
```

**Response:**
```json
{
  "success": true,
  "message": "Widget settings updated"
}
```

---

## 4. Dataset Management API

The dataset is the training data for the AI assistant. It contains text content that the AI uses to answer questions.

### 4.1 Get Dataset

**Endpoint:** `GET /api/dataset.php?client_id={client_id}`

**Response:**
```json
{
  "success": true,
  "client_id": "456789",
  "dataset": [
    {
      "id": "item_abc123",
      "type": "text",
      "title": "Product Information",
      "content": "Our product features include...",
      "char_count": 1500,
      "created_at": "2026-01-05 10:00:00"
    },
    {
      "id": "item_def456",
      "type": "file",
      "title": "FAQ Document",
      "content": "Q: How do I...?\nA: You can...",
      "char_count": 3200,
      "created_at": "2026-01-06 14:30:00"
    }
  ],
  "count": 2
}
```

---

### 4.2 Add Item to Dataset

**Endpoint:** `POST /api/dataset.php`

**Request:**
```json
{
  "client_id": "456789",
  "item": {
    "title": "Company Information",
    "content": "We are a technology company specializing in..."
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Item(s) added",
  "count": 3
}
```

---

### 4.3 Update Dataset Item

**Endpoint:** `PUT /api/dataset.php`

**Request:**
```json
{
  "client_id": "456789",
  "index": 0,
  "item": {
    "id": "item_abc123",
    "type": "text",
    "title": "Updated Product Information",
    "content": "Our updated product features include...",
    "char_count": 1800,
    "created_at": "2026-01-05 10:00:00"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Item updated",
  "index": 0
}
```

---

### 4.4 Delete Dataset Item

**Endpoint:** `DELETE /api/dataset.php`

**Request:**
```json
{
  "client_id": "456789",
  "index": 0
}
```

**Response:**
```json
{
  "success": true,
  "message": "Item deleted",
  "count": 2
}
```

---

### 4.5 Dataset Query (for n8n)

Returns dataset with user question in a format ready for AI processing.

**Endpoint:** `POST /api/dataset-query.php`

**Request:**
```json
{
  "client_id": "456789",
  "question": "What products do you offer?"
}
```

**Response (text/plain):**
```
dataset:

*** [
    {
        "id": "item_abc123",
        "title": "Product Information",
        "content": "Our product features include..."
    }
]

question: *** What products do you offer?
```

---

## 5. File Upload API

Upload text files to add to the dataset.

### 5.1 Upload Text File

**Endpoint:** `POST /api/upload.php`

**Headers:**
```
X-Auth-Token: your_auth_token
Content-Type: multipart/form-data
```

**Form Data:**
```
file: (TXT/CSV/MD/JSON file, max 5MB)
title: Optional title for the content
```

**Allowed file types:**
- `.txt` - Plain text
- `.csv` - CSV data
- `.md` - Markdown
- `.json` - JSON data

**Response:**
```json
{
  "success": true,
  "message": "Content added to dataset",
  "item": {
    "id": "item_xyz789",
    "type": "file",
    "title": "uploaded_document",
    "content": "File content here...",
    "char_count": 5000,
    "created_at": "2026-01-08 12:00:00"
  },
  "dataset_count": 4
}
```

---

### 5.2 Upload Plain Text

**Endpoint:** `POST /api/upload.php`

**Headers:**
```
X-Auth-Token: your_auth_token
Content-Type: application/json
```

**Request:**
```json
{
  "title": "Company Description",
  "text": "Our company was founded in 2020 and specializes in AI solutions..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Content added to dataset",
  "item": {
    "id": "item_abc123",
    "type": "text",
    "title": "Company Description",
    "content": "Our company was founded...",
    "char_count": 100,
    "created_at": "2026-01-08 12:00:00"
  },
  "dataset_count": 5
}
```

---

## 6. Widget Integration

### 6.1 Embed Code

Each user gets a unique embed code:

```html
<script src="https://cdn.weba-ai.com/widget.php?h=YOUR_WIDGET_HASH"></script>
```

The widget automatically:
- Loads in the bottom-right corner
- Connects to n8n for AI responses
- Stores chat history locally
- Plays notification sounds
- Works on all devices (responsive)

### 6.2 Widget Customization

All customization is done via the User Dashboard or API. Settings include:
- Widget title
- Operator label
- Welcome message
- Input placeholder
- Typing indicator text
- Sound on/off

---

## 7. User Dashboard Specification (Lovable Frontend)

### 7.1 Authentication Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Login     │────▶│   Verify    │────▶│  Dashboard  │
│   Page      │     │   Token     │     │   (main)    │
└─────────────┘     └─────────────┘     └─────────────┘
       │
       ▼
┌─────────────┐
│  Register   │
│   Page      │
└─────────────┘
```

**Token Storage:**
- Store `auth_token` in `localStorage`
- On page load, verify token via `POST /api/auth.php?action=verify`
- If invalid, redirect to login

---

### 7.2 Dashboard Sections

#### 7.2.1 Overview Section
- Display `client_id`
- Display current `plan_id`
- Show embed code with copy button
- Quick stats: dataset items count, chat sessions count

#### 7.2.2 Training Data Section (Dataset Management)

**UI Components:**

1. **Add Text Block**
   - Title input field
   - Large textarea for content
   - "Add" button → `POST /api/dataset.php`

2. **Upload File**
   - File input (accepts .txt, .csv, .md, .json)
   - Title input (optional)
   - "Upload" button → `POST /api/upload.php`

3. **Dataset List**
   - Table/cards showing all items:
     - Title
     - Type (text/file)
     - Character count
     - Created date
     - Edit button → opens modal
     - Delete button → `DELETE /api/dataset.php`

4. **Edit Modal**
   - Title input
   - Content textarea
   - Save button → `PUT /api/dataset.php`

**File Upload Implementation:**

```javascript
// Frontend code example
async function uploadFile(file, title) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('title', title);
  
  const response = await fetch('https://cdn.weba-ai.com/api/upload.php', {
    method: 'POST',
    headers: {
      'X-Auth-Token': localStorage.getItem('auth_token')
    },
    body: formData
  });
  
  return response.json();
}
```

**Text Add Implementation:**

```javascript
async function addTextContent(title, content) {
  const response = await fetch('https://cdn.weba-ai.com/api/dataset.php', {
    method: 'POST',
    headers: {
      'X-Auth-Token': localStorage.getItem('auth_token'),
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      client_id: localStorage.getItem('client_id'),
      item: {
        title: title,
        content: content
      }
    })
  });
  
  return response.json();
}
```

#### 7.2.3 Widget Settings Section

**UI Components:**

1. **Preview Panel**
   - Live preview of widget appearance
   - Updates in real-time as settings change

2. **Settings Form**
   - Title input
   - Operator label input
   - Welcome message textarea
   - Placeholder input
   - Typing label input
   - Sound enabled toggle

3. **Embed Code Panel**
   - Read-only textarea with embed code
   - Copy button

**Implementation:**

```javascript
async function updateWidgetSettings(settings) {
  const response = await fetch('https://cdn.weba-ai.com/api/user.php?action=widget', {
    method: 'PUT',
    headers: {
      'X-Auth-Token': localStorage.getItem('auth_token'),
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(settings)
  });
  
  return response.json();
}
```

#### 7.2.4 Profile Section

- Name input
- Email (read-only)
- Change password
- API key display (masked, with reveal button)

---

### 7.3 Frontend State Management

**Recommended State Structure:**

```typescript
interface AppState {
  // Auth
  isAuthenticated: boolean;
  authToken: string | null;
  
  // User
  user: {
    id: number;
    client_id: string;
    email: string;
    name: string;
    plan_id: string;
    widget_hash: string;
    api_key: string;
  } | null;
  
  // Widget
  widgetSettings: {
    title: string;
    operator_label: string;
    welcome: string;
    placeholder: string;
    typing_label: string;
    sound_enabled: boolean;
  };
  
  // Dataset
  dataset: Array<{
    id: string;
    type: 'text' | 'file';
    title: string;
    content: string;
    char_count: number;
    created_at: string;
  }>;
  
  // UI
  isLoading: boolean;
  error: string | null;
}
```

---

### 7.4 API Integration Helper

**Recommended API wrapper:**

```typescript
// api.ts
const API_BASE = 'https://cdn.weba-ai.com/api';

class APIClient {
  private token: string | null = null;
  
  setToken(token: string) {
    this.token = token;
    localStorage.setItem('auth_token', token);
  }
  
  getToken(): string | null {
    if (!this.token) {
      this.token = localStorage.getItem('auth_token');
    }
    return this.token;
  }
  
  async request(endpoint: string, options: RequestInit = {}) {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...options.headers as Record<string, string>
    };
    
    if (this.getToken()) {
      headers['X-Auth-Token'] = this.getToken()!;
    }
    
    const response = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers
    });
    
    if (response.status === 401) {
      // Token expired, redirect to login
      this.token = null;
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
      throw new Error('Unauthorized');
    }
    
    return response.json();
  }
  
  // Auth
  async register(email: string, password: string, name: string) {
    return this.request('/auth.php?action=register', {
      method: 'POST',
      body: JSON.stringify({ email, password, name })
    });
  }
  
  async login(email: string, password: string) {
    return this.request('/auth.php?action=login', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  }
  
  async verifyToken() {
    return this.request('/auth.php?action=verify', { method: 'POST' });
  }
  
  async logout() {
    return this.request('/auth.php?action=logout', { method: 'POST' });
  }
  
  // User
  async getProfile() {
    return this.request('/user.php');
  }
  
  async updateProfile(data: { name?: string; password?: string }) {
    return this.request('/user.php', {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }
  
  // Widget
  async getWidgetSettings() {
    return this.request('/user.php?action=widget');
  }
  
  async updateWidgetSettings(settings: Record<string, any>) {
    return this.request('/user.php?action=widget', {
      method: 'PUT',
      body: JSON.stringify(settings)
    });
  }
  
  // Dataset
  async getDataset(clientId: string) {
    return this.request(`/dataset.php?client_id=${clientId}`);
  }
  
  async addDatasetItem(clientId: string, item: { title: string; content: string }) {
    return this.request('/dataset.php', {
      method: 'POST',
      body: JSON.stringify({ client_id: clientId, item })
    });
  }
  
  async updateDatasetItem(clientId: string, index: number, item: Record<string, any>) {
    return this.request('/dataset.php', {
      method: 'PUT',
      body: JSON.stringify({ client_id: clientId, index, item })
    });
  }
  
  async deleteDatasetItem(clientId: string, index: number) {
    return this.request('/dataset.php', {
      method: 'DELETE',
      body: JSON.stringify({ client_id: clientId, index })
    });
  }
  
  // Upload
  async uploadFile(file: File, title?: string) {
    const formData = new FormData();
    formData.append('file', file);
    if (title) formData.append('title', title);
    
    const response = await fetch(`${API_BASE}/upload.php`, {
      method: 'POST',
      headers: {
        'X-Auth-Token': this.getToken() || ''
      },
      body: formData
    });
    
    return response.json();
  }
}

export const api = new APIClient();
```

---

## 8. n8n Integration

### 8.1 Webhook Configuration

**Webhook URL:** `https://gicujedrotan.beget.app/webhook/a60472fc-b4e1-4e83-92c4-75c648b9dd80`

**Incoming Data Structure:**
```json
{
  "clientId": "456789",
  "userId": "user_abc123",
  "message": "User's question here",
  "botId": "456789"
}
```

### 8.2 n8n Workflow Structure

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Webhook   │────▶│  HTTP Req   │────▶│   AI Node   │────▶│  HTTP Req   │
│   Trigger   │     │  (Dataset)  │     │  (OpenAI)   │     │  (Push)     │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
```

### 8.3 Step 1: Webhook Trigger

- Method: POST
- Responds immediately (no wait)

### 8.4 Step 2: Get Dataset

**HTTP Request Node:**
- Method: POST
- URL: `https://cdn.weba-ai.com/api/dataset-query.php`
- Body:
```json
{
  "client_id": "{{ $json.body.clientId }}",
  "question": "{{ $json.body.message }}"
}
```

### 8.5 Step 3: AI Processing

**OpenAI Node:**
- Model: GPT-4 or GPT-3.5
- System prompt includes dataset from previous step
- User message: `{{ $json.body.message }}`

### 8.6 Step 4: Push Response

**HTTP Request Node:**
- Method: POST
- URL: `https://cdn.weba-ai.com/api/push.php`
- Body:
```json
{
  "client_id": "{{ $('Webhook').item.json.body.clientId }}",
  "user_id": "{{ $('Webhook').item.json.body.userId }}",
  "message": "{{ $json.choices[0].message.content }}"
}
```

---

## 9. Database Schema

### users Table

```sql
CREATE TABLE `users` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(32) NOT NULL,
  `widget_hash` CHAR(32) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NULL,
  `auth_token` CHAR(64) NULL,
  `token_expires_at` TIMESTAMP NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `plan_id` VARCHAR(64) NOT NULL DEFAULT 'demo',
  `api_key` CHAR(32) NOT NULL,
  `widget_title` VARCHAR(128) NOT NULL DEFAULT 'Support',
  `widget_operator_label` VARCHAR(128) NOT NULL DEFAULT 'Operator Online',
  `widget_welcome` TEXT NULL,
  `widget_placeholder` VARCHAR(128) NOT NULL DEFAULT 'Type your message...',
  `widget_typing_label` VARCHAR(128) NOT NULL DEFAULT 'Operator typing...',
  `widget_sound_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `dataset` JSON NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_client` (`client_id`),
  UNIQUE KEY `uq_users_hash` (`widget_hash`),
  UNIQUE KEY `uq_users_email` (`email`),
  INDEX `idx_users_auth_token` (`auth_token`, `token_expires_at`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### conversations Table

```sql
CREATE TABLE `conversations` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` VARCHAR(32) NOT NULL,
  `client_id` VARCHAR(32) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `dialog` JSON NULL,
  `user_agent` VARCHAR(255) NULL,
  `ip_address` VARCHAR(45) NULL,
  `referrer` VARCHAR(255) NULL,
  `page_url` VARCHAR(255) NULL,
  `message_count` INT unsigned NOT NULL DEFAULT 0,
  `last_message_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conversations_user_client` (`client_id`, `user_id`),
  INDEX `idx_conversations_last_message` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### pending_messages Table

```sql
CREATE TABLE `pending_messages` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(32) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `message_role` VARCHAR(32) NOT NULL DEFAULT 'assistant',
  `message_text` TEXT NOT NULL,
  `delivered` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pending_user` (`client_id`, `user_id`, `delivered`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 10. Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad Request - Missing/invalid parameters |
| 401 | Unauthorized - Invalid/expired token |
| 403 | Forbidden - Invalid API key |
| 404 | Not Found - Resource doesn't exist |
| 405 | Method Not Allowed |
| 409 | Conflict - Resource already exists |
| 500 | Internal Server Error |

### Error Response Format

```json
{
  "error": "Human-readable error message"
}
```

---

## 11. Security Considerations

1. **Token Expiry**: Auth tokens expire after 30 days
2. **Password Hashing**: bcrypt with default cost
3. **CORS**: All origins allowed (for widget)
4. **File Upload**: Limited to 5MB, text-only formats
5. **SQL Injection**: PDO prepared statements
6. **XSS**: Content sanitization in widget

---

## 12. Deployment Checklist

### Backend (cdn.weba-ai.com)

1. [ ] Upload all PHP files
2. [ ] Create `.env` file with database credentials
3. [ ] Run SQL migrations
4. [ ] Test all API endpoints
5. [ ] Verify widget loads correctly

### Frontend (Lovable)

1. [ ] Implement login/register pages
2. [ ] Implement dashboard layout
3. [ ] Implement dataset management UI
4. [ ] Implement file upload
5. [ ] Implement widget settings
6. [ ] Test full flow end-to-end

### n8n

1. [ ] Create webhook workflow
2. [ ] Configure dataset-query HTTP request
3. [ ] Configure AI node with proper prompts
4. [ ] Configure push HTTP request
5. [ ] Test complete chat flow

---

## 13. API Endpoints Summary

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth.php?action=register` | No | Register new user |
| POST | `/api/auth.php?action=login` | No | Login |
| POST | `/api/auth.php?action=verify` | Yes | Verify token |
| POST | `/api/auth.php?action=refresh` | Yes | Refresh token |
| POST | `/api/auth.php?action=logout` | Yes | Logout |
| GET | `/api/user.php` | Yes | Get profile |
| PUT | `/api/user.php` | Yes | Update profile |
| GET | `/api/user.php?action=widget` | Yes | Get widget settings |
| PUT | `/api/user.php?action=widget` | Yes | Update widget settings |
| GET | `/api/dataset.php?client_id=X` | Optional | Get dataset |
| POST | `/api/dataset.php` | Optional | Add to dataset |
| PUT | `/api/dataset.php` | Optional | Update dataset item |
| DELETE | `/api/dataset.php` | Optional | Delete dataset item |
| POST | `/api/dataset-query.php` | No | Get dataset for AI |
| POST | `/api/upload.php` | Yes | Upload file |
| GET | `/api/poll.php?client_id=X&user_id=Y` | No | Poll for messages |
| POST | `/api/push.php` | No | Push message (n8n) |
| GET | `/widget.php?h=HASH` | No | Get widget script |

---

*Document Version: 1.0*
*Last Updated: January 8, 2026*


