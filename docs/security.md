# Security

## Request Size Limit

Ochrana proti DoS útokům pomocí `maxRequestBodySize` v DI konfiguraci:

```neon
api:
    maxRequestBodySize: 10485760  # 10MB limit
```

**Jak funguje:**
- Kontroluje `Content-Length` header **PŘED** načtením body do paměti
- Pro chunked transfer čte postupně s hard limitem
- Vrací `413 Payload Too Large` s human-readable velikostí (např. "10 MB")

Alternativně lze použít `RequestSizeLimitMiddleware` pro jemnější kontrolu.

## Rate Limiting

```neon
services:
    cache.rateLimit:
        factory: Symfony\Component\Cache\Adapter\RedisAdapter
        arguments: ['@redis']

api:
    middlewares:
        - Sabservis\Api\Middleware\RateLimitMiddleware(
            cache: @cache.rateLimit
            maxRequests: 100
            windowSeconds: 60
            trustedProxies: ['10.0.0.0/8', '172.16.0.0/12']  # Pro správnou detekci klientské IP
        )
```

**DŮLEŽITÉ:** Za reverse proxy (nginx, Cloudflare) nastavte `trustedProxies`, jinak všichni uživatelé sdílí rate limit IP proxy serveru.

### Per-IP limity

```neon
- Sabservis\Api\Middleware\RateLimitMiddleware(
    cache: @cache.rateLimit
    maxRequests: 100
    windowSeconds: 60
    ipLimits:
        '10.0.0.1': { maxRequests: 1000 }           # Konkrétní IP
        '192.168.0.0/16': { maxRequests: 500 }      # CIDR rozsah
)
```

### PSR-6 pro atomicitu

Pro vysokou zátěž doporučujeme PSR-6 `CacheItemPoolInterface`:

```neon
services:
    cache.rateLimit:
        factory: Symfony\Component\Cache\Adapter\RedisAdapter
        arguments: ['@redis']
        # RedisAdapter implementuje CacheItemPoolInterface
```

## File Response Security

### Povolené adresáře

```php
use Sabservis\Api\Http\FileResponse;

// V bootstrap
FileResponse::setAllowedDirectories([
    '/var/www/uploads',
    '/var/www/public/files',
]);
```

S tímto nastavením `FileResponse::fromPath()` odmítne soubory mimo povolené adresáře.

### Path traversal ochrana

`FileResponse::fromPath()` automaticky:
- Kanonizuje cestu pomocí `realpath()`
- Blokuje `../` útoky
- Ověřuje že soubor existuje

### Doporučení

```php
// Nejbezpečnější - obsah v paměti
return FileResponse::fromContent($data, 'report.pdf');

// Bezpečné s allowed directories
FileResponse::setAllowedDirectories(['/uploads']);
return FileResponse::fromPath('/uploads/' . $filename);

// Nebezpečné - nikdy nedělat!
return FileResponse::fromPath($userInput);  // Path traversal!
```

## JSON Depth Limit

Ochrana proti stack overflow útokům:

- Standardní limit: **64 úrovní**
- Platí pro `ApiRequest::getJsonBody()` i `DataMapperSerializer`
- Konfigurovatelné:

```php
$serializer = new DataMapperSerializer(jsonDepth: 32);
```

## Header Injection

Knihovna automaticky sanitizuje CRLF znaky v header hodnotách:

```php
// Útok nebude fungovat
$response->withHeader('X-Custom', "value\r\nSet-Cookie: admin=true");
// Výsledek: "valueSet-Cookie: admin=true" (CRLF odstraněno)
```

## HTTPS Detection

```php
$request->isSecured();  // true pro HTTPS
```

**Za reverse proxy** je nutné nakonfigurovat `trustedProxies`:

```neon
api:
    trustedProxies:
        - 10.0.0.0/8       # interní síť
        - 172.16.0.0/12    # Docker
        - 192.168.0.0/16   # LAN
```

**SECURITY:** Bez `trustedProxies` je `X-Forwarded-Proto` header ignorován, aby útočník nemohl spoofovat HTTPS. Header je důvěryhodný pouze pokud request přichází z trusted proxy IP.

## Error Handling

V produkci (debug=false):
- Stack traces nejsou zobrazeny
- Pouze generické chybové zprávy pro server errors
- Citlivé argumenty funkcí jsou odstraněny

### Error response formát

Všechny chybové odpovědi mají konzistentní strukturu:

```json
{
    "code": 404,
    "message": "Resource not found",
    "traceId": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "context": { "resource": "user", "id": 123 }
}
```

| Pole | Popis |
|------|-------|
| `code` | HTTP status kód (vždy přítomný) |
| `message` | Chybová zpráva (vždy přítomný) |
| `traceId` | Trace ID pro korelaci s error tracking systémem (volitelné) |
| `context` | Další kontext chyby - validační detaily, debug info (volitelné) |

### Trace ID

Pro korelaci chyb se Sentry, monologem nebo jiným error tracking systémem:

```neon
services:
    - Sabservis\Api\ErrorHandler\ErrorResponseBuilder
        setup:
            - setTraceIdProvider([@sentryBridge, 'getTraceId'])
```

```php
// Nebo v kódu
$builder->setTraceIdProvider(fn () => Sentry\SentrySdk::getCurrentHub()->getSpan()?->getTraceId());
```

Pokud provider vrátí `null`, pole `traceId` se v response neobjeví.

### Context filtrování

Kontextová data jsou automaticky sanitizována - klíče odpovídající citlivým vzorům (`password`, `token`, `secret`, `api_key` atd.) jsou odstraněny.

Pro vlastní filtrování:

```php
$builder->setContextFilter(function (array $context): array {
    unset($context['internal_code']);
    return $context;
});
```

Pro úplné vypnutí kontextu:

```php
$builder->disableContext();
```

## Client IP Detection

```php
$request->getClientIp();  // Vrací skutečnou IP klienta
```

**Za reverse proxy** je nutné nakonfigurovat `trustedProxies` (viz HTTPS Detection). Bez konfigurace vrací `REMOTE_ADDR` (IP proxy), s konfigurací parsuje `X-Forwarded-For` header a vrací skutečnou klientskou IP.

**SECURITY:** Bez `trustedProxies` je `X-Forwarded-For` ignorován, aby útočník nemohl spoofovat svou IP adresu.

## Runtime Authorizers

Kromě middleware můžeš definovat autorizaci přímo na endpointu pomocí `#[Authorize]`.
Authorizer je služba z DI, která dostane `ApiRequest`, `Endpoint` a `activity`.

```php
use Sabservis\Api\Attribute\Core\Authorize;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Security\Authorizer;

final class InvoiceAuthorizer implements Authorizer
{
    public function isAllowed(ApiRequest $request, Endpoint $endpoint, string $activity): bool
    {
        $user = $request->getAttribute('user');
        return $user !== null && in_array($activity, $user->permissions, true);
    }
}

final class InvoiceController implements Controller
{
    #[Get(path: '/invoices/{id}')]
    #[Authorize(activity: 'invoice.read', authorizer: InvoiceAuthorizer::class)]
    public function getInvoice(int $id): array { /* ... */ }
}
```

Registrace v DI:

```neon
services:
    - App\Security\InvoiceAuthorizer
```

### Kombinace pravidel (AND)

- Pokud má endpoint více `#[Authorize]`, musí projít **všechny**.
- Pokud už máš existující autorizaci (middleware / vlastní kontrola) a zároveň `#[Authorize]`, musí projít **obě**.
- Při zamítnutí vrací knihovna `403 Forbidden`.

### Dědičnost z rodičovské třídy

`#[Authorize]` se dědí z rodičovských tříd. Pokud definuješ autorizaci na abstraktním controlleru, všechny child controllery ji automaticky zdědí.

```php
#[Authorize(activity: 'trades', authorizer: TradeAuthorizer::class)]
abstract class BaseTradeController implements Controller {}

// Zdědí autorizaci 'trades' automaticky:
final class TradeSubmitController extends BaseTradeController
{
    #[Post(path: '/trades')]
    public function submit(TradeDto $input): TradeDto { }
}
```

## OpenAPI Security

Definování security requirements pro OpenAPI dokumentaci:

### Controller-level security

```php
use Sabservis\Api\Attribute\OpenApi\Security;

#[Security([['Bearer' => []]])]
class UserController implements Controller
{
    // Všechny metody vyžadují Bearer token
    #[Get(path: '/users')]
    public function list(): array { }

    // Veřejný endpoint - přepíše controller security
    #[Get(path: '/users/public')]
    #[Security([])]
    public function publicList(): array { }
}
```

### Inline security v Operation

```php
// Security přímo v HTTP method atributu
#[Get(path: '/health', security: [])]
public function health(): array { }

#[Get(path: '/admin', security: [['Admin' => []]])]
public function admin(): array { }
```

### Více security schémat (OR vztah)

```php
// Povolí Bearer NEBO ApiKey
#[Security([['Bearer' => []], ['ApiKey' => []]])]
public function data(): array { }
```

### Priorita

1. `#[Security]` atribut na metodě (nejvyšší)
2. `security` parametr v `#[Get]`/`#[Post]` atributu
3. `#[Security]` atribut na controlleru
4. Globální security z OpenApiConfig

**Tip:** `security: []` (prázdné pole) znamená veřejný endpoint bez autentizace.

## Best Practices

1. **Za reverse proxy nastavte `trustedProxies`** - pro správnou detekci HTTPS a klientské IP
2. **Nastavte `maxRequestBodySize`** - ochrana proti memory DoS útokům
3. **Vždy nastavte allowed directories** pro `FileResponse`
4. **Používejte rate limiting** v produkci
5. **Používejte HTTPS** v produkci
6. **Logujte requesty** pomocí `AuditLogMiddleware`
7. **Validujte vstup** pomocí DTO s validačními atributy

## Více viz

- [Middleware](middleware.md)
- [Parameters](parameters.md)
