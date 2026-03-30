# Primum AI

Primum AI is a Laravel 12 backend for AI-assisted health triage. It exposes a REST API for user authentication, dataset ingestion, semantic dataset parsing, AI chat, and conversation history management.

The application currently uses Ollama as its AI provider and is designed to work well with local or self-hosted models.

## Table of Contents

- Overview
- Core Features
- Tech Stack
- Architecture Summary
- Requirements
- Installation
- Environment Configuration
- Running the Application
- API Security Model
- API Reference
- AI Behavior
- Dataset Processing Flow
- Testing
- Development Notes
- Limitations and Safety Notice

## Overview

Primum AI provides a backend layer for health-triage experiences where clients can:

- register and authenticate users,
- upload CSV datasets,
- parse datasets into semantic records,
- optionally generate embeddings for semantic retrieval,
- send chat messages to an Ollama-hosted model,
- persist and resume conversations,
- retrieve past conversation history.

This repository is focused on backend and API integration. It is suitable for web or mobile frontends that need a health-oriented conversational API with conversation continuity.

## Core Features

- API key protection across all routes
- Laravel Sanctum authentication for protected chat endpoints
- Conversation persistence with automatic reuse through `conversation_id`
- Chat powered by Ollama `/api/chat`
- Lightweight backend system instruction that complements, but does not override, the model `Modelfile`
- CSV upload and synchronous parsing
- Semantic dataset normalization and structured JSON output
- Optional embedding generation through Ollama
- Conversation history retrieval per authenticated user
- JSON-first API error handling

## Tech Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- PHPUnit 11
- Ollama
- SQLite by default for local development

## Architecture Summary

At a high level, the application is split into four main areas:

1. Authentication
2. Dataset ingestion and parsing
3. AI chat orchestration
4. Conversation persistence

Key concepts:

- `ApiKeyMiddleware` is globally applied, so every request must include `X-API-KEY`.
- Protected endpoints also require a Sanctum bearer token.
- Conversations are stored in the database and reused whenever a valid `conversation_id` is sent.
- `conversationId` in camelCase is also accepted and normalized to `conversation_id`.
- Dataset uploads are parsed immediately during upload.
- Parsed dataset rows are also stored in `dataset_records` for later retrieval and optional embedding-based ranking.

## Requirements

Before running the project, make sure you have:

- PHP 8.2 or newer
- Composer
- Node.js and npm
- A working database supported by Laravel
- An accessible Ollama instance, local or remote

## Installation

Clone the repository and install dependencies:

```bash
git clone git@github.com:ggkooo/b-primum-ai.git
cd b-primum-ai
composer install
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Run the database migrations:

```bash
php artisan migrate
```

If you want a one-step local bootstrap, you can also use:

```bash
composer setup
```

## Environment Configuration

At minimum, configure the following variables in `.env`:

```env
APP_API_KEY=your_api_key_here

OLLAMA_API_KEY=
OLLAMA_MODEL=llama3.1
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_AUTH_HEADER=x-api-key
OLLAMA_VERIFY_SSL=true
OLLAMA_CA_BUNDLE=
OLLAMA_TIMEOUT=0
OLLAMA_CONNECT_TIMEOUT=0
OLLAMA_GENERATE_EMBEDDINGS=false
```

### Important Environment Notes

- `APP_API_KEY` is required for every API request.
- `OLLAMA_TIMEOUT=0` means no response timeout limit.
- `OLLAMA_CONNECT_TIMEOUT=0` means no connection timeout limit.
- `OLLAMA_AUTH_HEADER` supports different authentication strategies expected by your Ollama gateway.
- `OLLAMA_GENERATE_EMBEDDINGS=true` enables embedding generation during dataset parsing and semantic retrieval.

### Ollama Configuration Reference

| Variable | Description |
| --- | --- |
| `OLLAMA_API_KEY` | Optional API key forwarded to the Ollama gateway |
| `OLLAMA_MODEL` | Chat model used for `/api/chat` |
| `OLLAMA_EMBEDDING_MODEL` | Model used for embeddings |
| `OLLAMA_BASE_URL` | Base URL for the Ollama server or gateway |
| `OLLAMA_AUTH_HEADER` | Auth strategy: `x-api-key`, `bearer`, or `both` |
| `OLLAMA_VERIFY_SSL` | Enables/disables TLS certificate validation |
| `OLLAMA_CA_BUNDLE` | Optional custom CA bundle path |
| `OLLAMA_TIMEOUT` | Chat response timeout in seconds; `0` means unlimited |
| `OLLAMA_CONNECT_TIMEOUT` | Connection timeout in seconds; `0` means unlimited |
| `OLLAMA_GENERATE_EMBEDDINGS` | Enables embedding generation during dataset parsing |

## Running the Application

Start the API server:

```bash
php artisan serve
```

For a full local development stack, you can run:

```bash
composer dev
```

That command starts:

- the Laravel development server,
- a queue listener,
- Laravel Pail logs,
- the Vite development process.

### Health Check

Laravel health route:

```http
GET /up
```

## API Security Model

The API uses two layers of protection.

### 1. Global API Key

Every route requires:

```http
X-API-KEY: <APP_API_KEY>
```

If the key is missing or invalid, the API returns:

```json
{
  "message": "Unauthorized: Invalid or missing API Key"
}
```

### 2. Sanctum Bearer Token

Protected chat endpoints also require:

```http
Authorization: Bearer <access_token>
```

Protected routes:

- `POST /api/chat`
- `GET /api/conversations`
- `GET /api/conversations/{id}`

## API Reference

All API responses are JSON.

### Standard Success Envelope

```json
{
  "status": "success",
  "message": "Optional message",
  "data": {}
}
```

### Standard Application Error Envelope

```json
{
  "status": "error",
  "message": "Description"
}
```

### Validation Error Envelope

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Validation message"]
  }
}
```

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
- `email`: required, string, email, max 255, unique in `users`
- `password`: required, string, minimum 8 characters, confirmed

Success response: `201 Created`

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

### POST `/api/login`

Authenticates a user and returns a Sanctum token.

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

Success response: `200 OK`

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
      "email": "john@example.com"
    }
  }
}
```

Invalid credentials response: `401 Unauthorized`

```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```

### POST `/api/datasets/upload`

Uploads and parses a dataset synchronously.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Content-Type: multipart/form-data`

Form-data fields:

- `dataset`: required file, `csv` or `txt`, maximum 10 MB

Success response: `200 OK`

```json
{
  "status": "success",
  "message": "Dataset uploaded and parsed successfully",
  "data": {
    "id": 1,
    "filename": "disease_dataset.csv",
    "path": "datasets/abc123.csv",
    "parsed_path": "parsed/xyz456.json",
    "metadata": {
      "source": "disease_dataset.csv",
      "total_records": 100
    },
    "size": 20514
  }
}
```

Common errors:

- `422` invalid or missing file
- `500` upload succeeded but parsing failed

### POST `/api/datasets/{id}/parse`

Re-parses an already uploaded dataset.

Headers:

- `X-API-KEY: <APP_API_KEY>`

Path parameter:

- `id`: integer dataset id

Success response: `200 OK`

```json
{
  "status": "success",
  "message": "Dataset parsed successfully",
  "data": {
    "id": 1,
    "parsed_path": "parsed/xyz456.json",
    "metadata": {
      "source": "disease_dataset.csv",
      "total_records": 100
    }
  }
}
```

Common errors:

- `404` dataset not found
- `500` parsing failed

### POST `/api/chat`

Sends a message to the AI provider and persists both the user message and model response.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Authorization: Bearer <access_token>`
- `Content-Type: application/json`

Request body for a new conversation:

```json
{
  "message": "I have a severe headache and nausea."
}
```

Request body for an existing conversation:

```json
{
  "message": "Now I also feel pressure behind my right eye.",
  "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf"
}
```

CamelCase is also accepted:

```json
{
  "message": "The pain is getting worse.",
  "conversationId": "019cdf4e-fcc8-7020-bdd0-82e59d496adf"
}
```

Validation rules:

- `message`: required, string
- `conversation_id`: nullable, UUID, must exist in `conversations.id`

Success response: `200 OK`

```json
{
  "status": "success",
  "data": {
    "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
    "response": "Possible causes include ..."
  }
}
```

Common errors:

- `401` missing or invalid API key
- `401` missing or invalid bearer token
- `404` conversation not found or not owned by the authenticated user
- `422` invalid payload
- `502` provider communication error
- `504` upstream AI timeout returned by the provider gateway

Important chat behavior:

- If no `conversation_id` is sent, a new conversation is created.
- If `conversation_id` is present, the new message is appended to that conversation.
- The response always returns the effective `conversation_id`.
- Clients should persist the returned `conversation_id` and reuse it for follow-up messages.

### GET `/api/conversations`

Returns all conversations for the authenticated user ordered by `last_message_at` descending.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Authorization: Bearer <access_token>`

Success response: `200 OK`

```json
{
  "status": "success",
  "data": {
    "conversations": [
      {
        "id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
        "user_id": 1,
        "title": "I have a severe headache and nausea...",
        "last_message_at": "2026-03-11T12:00:00.000000Z",
        "created_at": "2026-03-11T11:59:00.000000Z",
        "updated_at": "2026-03-11T12:00:00.000000Z"
      }
    ]
  }
}
```

### GET `/api/conversations/{id}`

Returns a conversation and its messages in chronological order.

Headers:

- `X-API-KEY: <APP_API_KEY>`
- `Authorization: Bearer <access_token>`

Path parameter:

- `id`: UUID conversation id

Success response: `200 OK`

```json
{
  "status": "success",
  "data": {
    "conversation": {
      "id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
      "user_id": 1,
      "title": "I have a severe headache and nausea...",
      "last_message_at": "2026-03-11T12:00:00.000000Z",
      "messages": [
        {
          "id": 1,
          "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
          "role": "user",
          "content": "I have a severe headache and nausea."
        },
        {
          "id": 2,
          "conversation_id": "019cdf4e-fcc8-7020-bdd0-82e59d496adf",
          "role": "model",
          "content": "Possible causes include ..."
        }
      ]
    }
  }
}
```

## AI Behavior

The backend integrates with Ollama through `app/Services/OllamaService.php`.

Current behavior:

- the user message is always sent to Ollama,
- existing conversation history is sent along with the request,
- the backend also sends a lightweight `system` message,
- that system instruction is designed to complement the model `Modelfile`, not replace it.

The current intent of the backend instruction is to encourage:

- useful diagnostic hypotheses,
- short justifications,
- initial guidance,
- red-flag awareness,
- fewer unnecessary follow-up questions.

If you want the model to strictly follow a certain structure, the most authoritative place to enforce that is still the Ollama model `Modelfile`.

## Dataset Processing Flow

When a dataset is uploaded:

1. the raw file is stored under `storage/app/datasets`,
2. the CSV is parsed,
3. each row is transformed into a semantic description,
4. optional embeddings are generated,
5. a parsed JSON artifact is stored under `storage/app/parsed`,
6. row-level semantic records are stored in `dataset_records`.

This allows two retrieval modes:

- simple aggregation from parsed dataset artifacts,
- embedding-assisted ranking when embeddings are enabled.

## Frontend Integration Example

Recommended chat lifecycle:

1. Register or log in.
2. Persist the returned bearer token.
3. Start the first chat message without `conversation_id`.
4. Save `data.conversation_id` from the response.
5. Reuse that same identifier for every follow-up message.
6. Use `GET /api/conversations` to render the conversation list.
7. Use `GET /api/conversations/{id}` to load a full thread.

Minimal frontend state:

```ts
type ChatState = {
  accessToken: string;
  currentConversationId: string | null;
};
```

## Testing

Available Composer commands:

```bash
composer test
composer test:all
composer test:unit
composer test:feature
```

You can also run specific tests directly:

```bash
php artisan test --filter=ChatFlowTest
php artisan test --filter=OllamaServiceTest
```

## Development Notes

- API responses are forced to JSON for `/api/*` routes.
- The API key middleware is globally appended in `bootstrap/app.php`.
- Chat provider failures are converted into structured application errors.
- Dataset upload is currently synchronous, even though queue-related tooling still exists in the project.
- `last_message_at` is updated when a user continues an existing conversation.

## Limitations and Safety Notice

This project is an academic AI-assisted triage system.

It is not a substitute for:

- professional medical diagnosis,
- emergency care,
- licensed clinical judgment.

Any triage-oriented frontend built on top of this API should clearly communicate that the system is informational and assistive only.