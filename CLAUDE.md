# Sabservis\Api - PHP REST API framework pro Nette

## Architektura

```
src/
├── Application/     ApiApplication - vstupní bod
├── Attribute/       PHP 8 atributy pro routing a OpenAPI
├── DI/              ApiExtension pro Nette DI
├── Dispatcher/      ApiDispatcher - orchestrace happy path
├── ErrorHandler/    ErrorHandler interface
├── Exception/       ApiException hierarchie + ErrorMessages konstanty
├── Handler/         ServiceHandler - volání controller metod
├── Http/            ApiRequest/ApiResponse (immutable), FileResponse, UploadedFile
├── Mapping/         EntitySerializer, RequestParameterMapping
├── Middleware/      CORS, RateLimit, BasicAuth, Logging, ApiMiddleware (core)
├── OpenApi/         OpenApiGenerator, SchemaBuilder
├── Router/          Router - O(1) statické, regex dynamické
├── Schema/          Endpoint (facade), RouteDefinition, HandlerDefinition, OpenApiDefinition
├── Testing/         ApiTestClient pro integrační testy
└── Utils/           IpMatcher, ByteFormatter, ClientIpResolver, HeaderSanitizer, DateTimeParser
```

## Request flow

```
ApiRequest::fromGlobals() → ApiApplication::runWith() → Middleware chain → ApiMiddleware
→ Router::match() → RequestParameterMapping → EntitySerializer::deserialize()
→ EntityValidator::validate() → ServiceHandler::handle() → EntitySerializer::serialize()
→ ApiApplication::finalize()
```

## Klíčové koncepty

**Endpoint = Facade** deleguje na: RouteDefinition, HandlerDefinition, OpenApiDefinition

**ServiceHandler injection:** `int $id` z path, `?int $limit` z query s default, `CreateDto $input` z body, `ApiRequest $request` pro plnou kontrolu

**Exception handling:**
- `ApiMiddleware` chytá exceptions, deleguje na `ErrorHandler`
- `ApiDispatcher` nechytá (kromě `EarlyReturnResponseException`)

```
ApiException (ExceptionExtra trait)
├── ClientErrorException (400-499) → ValidationException (422)
└── ServerErrorException (500-599)
```

## Middleware priority (nižší = dříve)

| Priority | Middleware |
|----------|------------|
| 5 | RequestIdMiddleware |
| 10 | MethodOverrideMiddleware |
| 15 | RequestSizeLimitMiddleware |
| 200 | CORSMiddleware |
| 250 | BasicAuthMiddleware |
| 450 | RateLimitMiddleware |
| 499 | EnforceHttpsMiddleware |
| 500 | AuditLogMiddleware |
| - | ApiMiddleware (vždy poslední) |

## ErrorMessages konstanty

Používej `ErrorMessages::NOT_FOUND`, `METHOD_NOT_ALLOWED`, `TOO_MANY_REQUESTS`, `PAYLOAD_TOO_LARGE`, `PARAMETER_REQUIRED`, `FILE_REQUIRED`, `FILE_EMPTY`, `INVALID_CONTENT_LENGTH`, `UNSUPPORTED_CONTENT_TYPE`, atd.

## Security - co framework řeší automaticky

| Oblast | Jak |
|--------|-----|
| Path traversal | `FileResponse::setAllowedDirectories()` + `realpath()` |
| File upload MIME | `$file->getValidatedContentType()` (finfo, ne klient) |
| File upload empty | 0-byte automaticky odmítnuty |
| Header injection | `HeaderSanitizer` automaticky |
| Request size | `maxRequestBodySize` v DI (PŘED načtením do RAM) |
| Content-Type | Validace před JSON deserializací |
| CORS | Blokuje `allowCredentials: true` + wildcard |
| IP/HTTPS spoofing | Bez `trustedProxies` ignoruje X-Forwarded-* |

## Testování

```bash
./vendor/bin/phpunit
./vendor/bin/phpstan analyse src/ --memory-limit=512M
```

## Známá omezení

- Pouze JSON (chybí XML, content negotiation)
- Chybí API versioning
- JWT/API key auth - implementuj sám
- Chybí i18n chybových zpráv
