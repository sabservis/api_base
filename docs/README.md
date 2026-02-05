# Sabservis\Api Documentation

PHP knihovna pro REST API v Nette.

## Quick Start

1. [Getting Started](getting-started.md) - Instalace a konfigurace
2. [Cheatsheet](cheatsheet.md) - Kompletní CRUD příklad, copy-paste ready

## Atributy podle úrovně

### Tier 1 - Základní (90% use cases)

| Atribut | Popis |
|---------|-------|
| `#[Get(path: '/users')]` | GET endpoint |
| `#[Post(path: '/users')]` | POST endpoint |
| `#[Put(path: '/users/{id}')]` | PUT endpoint |
| `#[Delete(path: '/users/{id}')]` | DELETE endpoint |
| `#[Response(ref: Dto::class)]` | Response s DTO (default 200) |
| `#[Response(listRef: Dto::class)]` | Response se seznamem |
| `#[Response(404)]` | Error response (auto description) |
| `#[Tag(name: 'users')]` | Seskupení v dokumentaci |

**→ S těmito 7 atributy pokryješ většinu API.**

### Tier 2 - Dokumentace

| Atribut | Popis |
|---------|-------|
| `#[PathParameter(name: 'id', description: '...')]` | Popis path parametru |
| `#[QueryParameter(name: 'limit', description: '...')]` | Popis query parametru |
| `#[RequestBody(ref: CreateDto::class)]` | Request body dokumentace |
| `#[Schema]`, `#[Property]` | DTO dokumentace |

### Tier 3 - Specializované

| Atribut | Popis |
|---------|-------|
| `#[Hidden]` | Skryje endpoint z OpenAPI |
| `#[FileUpload(name: 'file')]` | File upload |
| `#[FileResponse]` | File download |
| `#[Alias('/alt-path')]` | Alternativní URL |

### Tier 4 - Expert

Pro polymorfní typy a pokročilé OpenAPI:

| Atribut | Popis |
|---------|-------|
| `Items` | Definice položek pole |
| `JsonContent` | Inline JSON schema |
| `AdditionalProperties` | Dynamické klíče (mapy) |
| `Discriminator` | Polymorfní typy |
| `OpenApiMerge` | Custom OpenAPI spec |
| `SecurityScheme` | Auth definice |

## Dokumentace

- [Controllers & Routing](controllers.md) - Definice endpointů
- [Parameters](parameters.md) - Path, query, header parametry
- [Request & Response](request-response.md) - Práce s HTTP
- [OpenAPI](openapi.md) - Generování dokumentace
- [Middleware](middleware.md) - CORS, auth, rate limiting
- [Security](security.md) - Autentizace a autorizace
- [Testing](testing.md) - Testování API
