# Dataset API Documentation

Base URL: `https://cdn.weba-ai.com/api/`

## Overview

The Dataset API allows you to manage user-specific data stored in the `dataset` field. This field stores a JSON array that can contain any structured data for AI assistant context.

---

## 1. Get Dataset

Retrieve the full dataset for a user.

### Request

```
GET /api/dataset.php?client_id={client_id}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| client_id | string | Yes | User's client ID |
| api_key | string | No | API key for authentication (can also be sent as X-API-Key header) |

### Example Request

```bash
curl "https://cdn.weba-ai.com/api/dataset.php?client_id=1"
```

### Example Response

```json
{
    "success": true,
    "client_id": "1",
    "dataset": [
        {"title": "Product A", "description": "Description of product A", "price": 100},
        {"title": "Product B", "description": "Description of product B", "price": 200}
    ],
    "count": 2
}
```

---

## 2. Add Item(s) to Dataset

Add one or multiple items to the dataset.

### Request

```
POST /api/dataset.php
Content-Type: application/json
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| client_id | string | Yes | User's client ID |
| item | object | No* | Single item to add |
| items | array | No* | Array of items to add |

*Either `item` or `items` is required.

### Example: Add Single Item

```bash
curl -X POST "https://cdn.weba-ai.com/api/dataset.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "1",
    "item": {
      "title": "New Product",
      "description": "Product description",
      "price": 150
    }
  }'
```

### Example: Add Multiple Items

```bash
curl -X POST "https://cdn.weba-ai.com/api/dataset.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "1",
    "items": [
      {"title": "Product X", "price": 100},
      {"title": "Product Y", "price": 200}
    ]
  }'
```

### Response

```json
{
    "success": true,
    "message": "Item(s) added",
    "count": 5
}
```

---

## 3. Update Item in Dataset

Update an item at a specific index.

### Request

```
PUT /api/dataset.php
Content-Type: application/json
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| client_id | string | Yes | User's client ID |
| index | integer | Yes | Index of item to update (0-based) |
| item | object | Yes | New item data |

### Example

```bash
curl -X PUT "https://cdn.weba-ai.com/api/dataset.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "1",
    "index": 0,
    "item": {
      "title": "Updated Product",
      "description": "Updated description",
      "price": 175
    }
  }'
```

### Response

```json
{
    "success": true,
    "message": "Item updated",
    "index": 0
}
```

---

## 4. Delete Item from Dataset

Delete an item at a specific index.

### Request

```
DELETE /api/dataset.php
Content-Type: application/json
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| client_id | string | Yes | User's client ID |
| index | integer | Yes | Index of item to delete (0-based) |

### Example

```bash
curl -X DELETE "https://cdn.weba-ai.com/api/dataset.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "1",
    "index": 2
  }'
```

### Response

```json
{
    "success": true,
    "message": "Item deleted",
    "count": 4
}
```

---

## 5. Dataset Query (Text Format)

Get dataset with a question in a special text format, useful for AI prompts.

### Request

```
POST /api/dataset-query.php
Content-Type: application/json
```

or

```
GET /api/dataset-query.php?client_id={client_id}&question={question}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| client_id | string | Yes | User's client ID |
| question | string | Yes | Question text to include |
| format | string | No | Output format: `text` (default) or `json` |

### Example: Text Format (Default)

```bash
curl -X POST "https://cdn.weba-ai.com/api/dataset-query.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "1",
    "question": "What products do you have under $150?"
  }'
```

### Response (Text Format)

```
dataset:

*** [
    {
        "title": "Product A",
        "description": "Description of product A",
        "price": 100
    },
    {
        "title": "Product B",
        "description": "Description of product B",
        "price": 200
    }
]

question: *** What products do you have under $150?
```

### Example: JSON Format

```bash
curl -X POST "https://cdn.weba-ai.com/api/dataset-query.php" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "1",
    "question": "What products do you have under $150?",
    "format": "json"
  }'
```

### Response (JSON Format)

```json
{
    "success": true,
    "client_id": "1",
    "dataset": [
        {"title": "Product A", "description": "Description of product A", "price": 100},
        {"title": "Product B", "description": "Description of product B", "price": 200}
    ],
    "question": "What products do you have under $150?"
}
```

---

## Authentication

All endpoints support optional API key authentication:

1. **Header**: `X-API-Key: your_api_key`
2. **Parameter**: `api_key=your_api_key`

If an API key is provided, it must match the user's `api_key` in the database.

---

## Error Responses

All endpoints return errors in JSON format:

```json
{
    "error": "Error message"
}
```

### Common Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 400 | Bad Request - Missing required parameters |
| 403 | Forbidden - Invalid API key |
| 404 | Not Found - User not found |
| 405 | Method Not Allowed |
| 500 | Internal Server Error |

---

## n8n Integration

### Using Dataset Query in n8n

1. **HTTP Request Node** to get dataset with question:
   - Method: POST
   - URL: `https://cdn.weba-ai.com/api/dataset-query.php`
   - Body Content Type: JSON
   - Body:
     ```json
     {
       "client_id": "{{ $json.body.clientId }}",
       "question": "{{ $json.body.message }}"
     }
     ```

2. The response text can be passed directly to an AI node as context.

### Example n8n Workflow

```
Webhook → HTTP Request (dataset-query) → AI Node → HTTP Request (push response)
```

