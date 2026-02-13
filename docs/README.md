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
| `#[Response(listRef: [A::class, B::class])]` | Seznam s oneOf |
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
| `#[FileUpload(name: 'file')]` | File upload (s `multiple`, `allowedTypes`) |
| `#[FileUpload]` na DTO property | Multipart form data s beznymi poli + soubory |
| `#[FileResponse]` | File download (s `response`, `description`) |
| `#[Alias('/alt-path')]` | Alternativní URL |
| `#[Security]` | Auth requirement na endpoint/controller |
| `#[Authorize(activity: 'x', authorizer: X::class)]` | Runtime autorizace přes DI authorizer |
| `#[ExternalDocumentation]` | Odkaz na externí docs |

### Tier 4 - Expert

Pro polymorfní typy a pokročilé OpenAPI:

| Atribut | Popis |
|---------|-------|
| `Items` | Definice položek pole (s `oneOf`/`anyOf`/`allOf`, constraints) |
| `JsonContent` | Inline JSON schema (s `example`, `examples`, `additionalProperties`) |
| `MediaType` | Custom media type (s `example`, `examples`) |
| `AdditionalProperties` | Dynamické klíče - mapy (s `ref`, `items`) |
| `Examples` | Pojmenované příklady (s `description`, `externalValue`) |
| `Discriminator` | Polymorfní typy s type mapping |
| `Encoding` | Content-type a serializace v multipart |
| `OpenApiMerge` | Custom OpenAPI spec |
| `SecurityScheme` | Auth definice (`http`, `apiKey`, `openIdConnect`) |
| `Server` / `ServerVariable` | URL templating s proměnnými |
| `Info` / `Contact` / `License` | API metadata |

## Dokumentace

- [Controllers & Routing](controllers.md) - Definice endpointů
- [Parameters](parameters.md) - Path, query, header parametry
- [Request & Response](request-response.md) - Práce s HTTP
- [OpenAPI](openapi.md) - Generování dokumentace
- [Middleware](middleware.md) - CORS, auth, rate limiting
- [Security](security.md) - Autentizace a autorizace
- [Testing](testing.md) - Testování API
