# Primum AI - Health Triage API

Primum AI is a Laravel-based backend for AI-assisted health triage. It allows clients to:

- register and authenticate users,
- upload and parse health datasets,
- send chat messages to an AI model,
- persist and retrieve conversation history.

This README is written for frontend and API integration.

## Overview

- Framework: Laravel 12
- Language: PHP 8.2+
- Auth: API key (`X-API-KEY`) + Laravel Sanctum (Bearer token on protected routes)
- AI provider: Gemini (configured through environment variables)
- Conversation identifier: UUID (`conversation_id`)

## Quick Start

1. Clone and install dependencies:

```bash
git clone git@github.com:ggkooo/b-primum-ai.git
cd b-primum-ai
composer install
npm install
```

2. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure at least these variables in `.env`:

```env
APP_API_KEY=your_api_key_here
GEMINI_API_KEY=your_gemini_key_here
GEMINI_MODEL=gemini-1.5-flash
```

4. Prepare database and run app:

```bash
php artisan migrate
php artisan serve
```

5. Optional but recommended for background parsing:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

## Authentication

### 1. API Key (required on all routes)

Every request must include:

```http
X-API-KEY: <APP_API_KEY>
```

If missing or invalid, the API returns:

```json
{
    "message": "Unauthorized: Invalid or missing API Key"
}
```

### 2. Sanctum Bearer Token (protected routes only)

Routes under `auth:sanctum` also require:

```http
Authorization: Bearer <access_token>
```

Protected routes:

- `POST /api/chat`
- `GET /api/conversations`
- `GET /api/conversations/{id}`

## Response Conventions

### Success envelope

Most successful responses use:

```json
{
    "status": "success",
    "message": "Optional message",
    "data": {}
}
```

`message` is included when relevant.

### Application error envelope

Custom application errors use:

```json
{
    "status": "error",
    "message": "Description"
}
```

### Validation error envelope

Laravel validation errors return:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field_name": ["Validation message"]
    }
}
```

## API Endpoints

### POST `/api/register`

Registers a new user.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Content-Type: application/json`

Request body:

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

Validation rules:

- `name`: required, string, max 255
- `email`: required, string, email, max 255, unique
- `password`: required, string, min 8, confirmed

Success response (`201`):

```json
{
    "status": "success",
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "created_at": "2026-03-11T12:00:00.000000Z",
            "updated_at": "2026-03-11T12:00:00.000000Z"
        }
    }
}
```

Common errors:

- `422` validation errors (for example duplicate email)
- `401` invalid/missing API key

### POST `/api/login`

Authenticates user and returns a Sanctum access token.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Content-Type: application/json`

Request body:

```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

Validation rules:

- `email`: required, email, string
- `password`: required, string

Success response (`200`):

```json
{
    "status": "success",
    "message": "Login successful",
    "data": {
        "access_token": "1|token_value_here",
        "token_type": "Bearer",
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "created_at": "2026-03-11T12:00:00.000000Z",
            "updated_at": "2026-03-11T12:00:00.000000Z"
        }
    }
}
```

Invalid credentials (`401`):

```json
{
    "status": "error",
    "message": "Invalid credentials"
}
```

### POST `/api/datasets/upload`

Uploads a CSV file and queues background parsing.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Content-Type: multipart/form-data`

Form-data:

- `dataset`: required file (`csv`/`txt`), max `10MB`

Success response (`201`):

```json
{
    "status": "success",
    "message": "Dataset uploaded successfully. Parsing started in background.",
    "data": {
        "id": 1,
        "filename": "Disease_symptom_and_patient_profile_dataset.csv",
        "path": "datasets/abc123.csv",
        "size": 20514
    }
}
```

Common errors:

- `422` invalid or missing file
- `401` invalid/missing API key

### POST `/api/datasets/{id}/parse`

Parses a previously uploaded dataset immediately.

Headers:

- `X-API-KEY: <APP_API_KEY>`

Path params:

- `id` (integer): dataset id

Success response (`200`):

```json
{
    "status": "success",
    "message": "Dataset parsed successfully",
    "data": {
        "id": 1,
        "parsed_path": "datasets/parsed_1.json",
        "metadata": {
            "total_records": 100,
            "source_filename": "Disease_symptom_and_patient_profile_dataset.csv"
        }
    }
}
```

Common errors:

- `404` dataset not found
- `500` parsing failed
- `401` invalid/missing API key

### POST `/api/chat` (protected)

Sends a user message to AI and stores conversation/messages.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Authorization: Bearer <access_token>`
- `Content-Type: application/json`

Request body (new conversation):

```json
{
    "message": "I have a severe headache and fever."
}
```

Request body (continue existing conversation):

```json
{
    "message": "Now I am also vomiting after meals.",
    "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf"
}
```

Validation rules:

- `message`: required, string
- `conversation_id`: optional, UUID, must exist in `conversations.id`

Success response (`200`):

```json
{
    "status": "success",
    "data": {
        "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
        "response": "Based on your symptoms, you should seek urgent medical care..."
    }
}
```

Important integration notes:

- If `conversation_id` is omitted, a new conversation is created.
- If `conversation_id` is sent, the message is appended to that conversation.
- Always store the returned `conversation_id` in the frontend and reuse it for follow-up messages.

Common errors:

- `401` unauthenticated (missing/invalid Bearer token)
- `401` invalid/missing API key
- `422` invalid payload (`message` missing, invalid UUID, unknown conversation)
- `404` conversation not found for current user (ownership enforcement)

### GET `/api/conversations` (protected)

Lists conversations for authenticated user ordered by `last_message_at` descending.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Authorization: Bearer <access_token>`

Success response (`200`):

```json
{
    "status": "success",
    "data": {
        "conversations": [
            {
                "id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
                "user_id": 1,
                "title": "I have a severe headache and fever...",
                "last_message_at": "2026-03-11T12:00:00.000000Z",
                "created_at": "2026-03-11T11:59:00.000000Z",
                "updated_at": "2026-03-11T12:00:00.000000Z"
            }
        ]
    }
}
```

### GET `/api/conversations/{id}` (protected)

Returns one conversation with its messages (oldest to newest).

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Authorization: Bearer <access_token>`

Path params:

- `id` (UUID): conversation id

Success response (`200`):

```json
{
    "status": "success",
    "data": {
        "conversation": {
            "id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
            "user_id": 1,
            "title": "I have a severe headache and fever...",
            "last_message_at": "2026-03-11T12:00:00.000000Z",
            "created_at": "2026-03-11T11:59:00.000000Z",
            "updated_at": "2026-03-11T12:00:00.000000Z",
            "messages": [
                {
                    "id": 1,
                    "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
                    "role": "user",
                    "content": "I have a severe headache and fever.",
                    "created_at": "2026-03-11T11:59:00.000000Z",
                    "updated_at": "2026-03-11T11:59:00.000000Z"
                },
                {
                    "id": 2,
                    "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
                    "role": "model",
                    "content": "I understand. Please monitor...",
                    "created_at": "2026-03-11T11:59:01.000000Z",
                    "updated_at": "2026-03-11T11:59:01.000000Z"
                }
            ]
        }
    }
}
```

Common errors:

- `404` conversation not found or does not belong to authenticated user
- `401` unauthenticated
- `401` invalid/missing API key

## Frontend Integration Flow

Recommended chat flow:

1. Register (`/api/register`) or login (`/api/login`).
2. Store `access_token` securely.
3. Start a conversation by calling `POST /api/chat` with only `message`.
4. Save returned `data.conversation_id` (UUID) in client state.
5. For each follow-up message, send the same `conversation_id`.
6. Use `GET /api/conversations` to show conversation list.
7. Use `GET /api/conversations/{id}` to load full history on screen.

Minimal frontend state example:

```ts
type ChatState = {
    accessToken: string;
    currentConversationId: string | null;
};
```

## Testing

Available test commands:

```bash
composer test
composer test:all
composer test:unit
composer test:feature
```

## Notes

- This project is an academic AI-assisted triage system.
- It does not replace medical diagnosis from qualified professionals.
