# Testing Guide

Este projeto usa duas camadas de testes:

- `Unit`: valida regras de negocio e componentes isolados.
- `Feature`: valida fluxos HTTP, autenticacao e contratos de resposta.

## Comandos principais

```bash
composer test:all
composer test:unit
composer test:feature
```

Tambem e possivel executar direto via Artisan:

```bash
php artisan test tests/Unit tests/Feature
```

## Convencoes utilizadas

- Mocks em services externos (ex: `GeminiService`) para evitar dependencia de rede.
- `RefreshDatabase` em testes que leem/escrevem no banco.
- Header `X-API-KEY` em todas as chamadas HTTP.
- `Sanctum::actingAs(...)` para rotas autenticadas (`/api/chat`, `/api/conversations`).
