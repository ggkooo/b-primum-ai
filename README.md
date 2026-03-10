# Primum AI - Health Diagnosis AI

This project is part of the **Integrated Project** (PI) course at university. The goal is to develop an AI capable of providing health pre-diagnoses based on user-provided symptoms, using specialized datasets.

## 🚀 Features

- [x] API Key Authentication (`X-API-KEY`).
- [ ] Health Dataset Integration (In development).
- [ ] Diagnosis AI Engine (In development).
- [ ] API Routes for Symptoms and Diagnoses (In development).

## 🛠️ Tech Stack

- **Framework**: [Laravel 12](https://laravel.com)
- **Language**: PHP 8.2+
- **Database**: SQLite (Initial setup)

## 🔐 API Authentication

All project routes are protected by a security layer. The `X-API-KEY` header is mandatory for every request.

### How to use:

- **Header**: `X-API-KEY`
- **Value**: Your key generated in the `.env` file (e.g., `bb490c0ecd1...`)

#### Example with cURL:
```bash
curl -H "X-API-KEY: your_api_key_here" http://localhost:8000/
```

## 📡 API Endpoints

### User Registration
`POST /api/register`

Used to register a new user in the platform.

#### Payload:
```json
{
    "name": "Giordano Bruno",
    "email": "giordanoberwig@proton.me",
    "password": "12345678",
    "password_confirmation": "12345678"
}
```

#### Success Response (201 Created):
```json
{
    "status": "success",
    "message": "User registered successfully",
    "data": {
        "user": {
            "name": "Giordano Bruno",
            "email": "giordanoberwig@proton.me",
            "updated_at": "2026-03-10T17:08:44.000000Z",
            "created_at": "2026-03-10T17:08:44.000000Z",
            "id": 1
        }
    }
}
```

#### Error Responses:

**Validation Error (422 Unprocessable Entity):**
```json
{
    "message": "The email has already been taken.",
    "errors": {
        "email": [
            "The email has already been taken."
        ]
    }
}
```

**Unauthorized (401 Unauthorized):**
```json
{
    "message": "Unauthorized: Invalid or missing API Key"
}
```


## ⚙️ Installation and Setup

1. **Clone the repository:**
   ```bash
   git clone git@github.com:ggkooo/b-primum-ai.git
   cd primum-ai
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment:**
   - Copy `.env.example` to `.env`.
   - Generate application key: `php artisan key:generate`.
   - Add your `APP_API_KEY` to the `.env` file.

4. **Start the Server:**
   ```bash
   php artisan serve
   ```

## 🧪 Testing

To ensure security is working correctly, you can run the automated tests:

```bash
php artisan test tests/Feature/ApiKeyTest.php
```

---
*Developed by [Giordano Berwig] for the Integrated Project.*
