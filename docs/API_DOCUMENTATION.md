# 📡 Bitversity API Documentation

## Overview

The Bitversity API provides RESTful endpoints for interacting with the platform's core functionality. All API endpoints return JSON responses and follow standard HTTP status codes.

**Base URL**: `http://localhost/bitversity/api/`

## Authentication

Most API endpoints require user authentication via session-based authentication.

### Headers
```http
Content-Type: application/json
X-CSRF-Token: {csrf_token}
```

## Endpoints

### 🔐 Authentication API

#### POST `/api/auth.php`
Handle user authentication operations.

**Register User**
```json
{
  "action": "register",
  "full_name": "John Doe",
  "email": "john@example.com", 
  "password": "password123"
}
```

**Login User**
```json
{
  "action": "login",
  "email": "john@example.com",
  "password": "password123"
}
```

### 🛒 Shopping Cart API

#### GET `/api/cart.php`
Get user's cart contents.

#### POST `/api/add-to-cart.php`
```json
{
  "item_type": "book",
  "item_id": 1,
  "quantity": 1
}
```

#### DELETE `/api/remove-from-cart.php`
```json
{
  "cart_id": 123
}
```

### 🔍 Search API

#### GET `/api/search.php`
```http
GET /api/search.php?q=javascript&type=books&category=programming&page=1&limit=12
```

**Response:**
```json
{
  "success": true,
  "results": [],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 58
  }
}
```

### ⭐ Reviews API

#### POST `/api/reviews.php`
```json
{
  "item_type": "book",
  "item_id": 1,
  "rating": 5,
  "comment": "Excellent book!"
}
```

#### GET `/api/reviews.php?item_type=book&item_id=1`
Get reviews for specific content.

## Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

## Rate Limiting

- **Authenticated users**: 1000 requests per hour
- **Anonymous users**: 100 requests per hour

## Error Responses

```json
{
  "success": false,
  "error": "Invalid credentials",
  "code": 401
}
```