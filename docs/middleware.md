# Middleware

Middleware umožňuje zpracovat request/response v pipeline.

## Vestavěné middleware

| Middleware | Priorita | Popis |
|------------|----------|-------|
| `RequestIdMiddleware` | 5 | Generuje/propaguje X-Request-ID |
| `MethodOverrideMiddleware` | 10 | X-HTTP-Method-Override header (jen u POST) |
| `LoggingMiddleware` | 10 | Request/response logging |
| `RequestSizeLimitMiddleware` | 15 | Omezení velikosti body |
| `EnforceHttpsMiddleware` | 20 | Odmítne HTTP (400) před autentizací a rate limitem |
| `CORSMiddleware` | 200 | CORS headers + preflight |
| `RateLimitMiddleware` | 450 | Rate limiting per IP |
| `BasicAuthMiddleware` | 460 | HTTP Basic Auth (za RateLimit, aby se počítaly i neúspěšné pokusy) |
| `AuditLogMiddleware` | 500 | Audit trail logging |

Nižší priorita = spustí se dříve.

## Kdy použít který middleware

| Middleware | Použij když... | Nepoužívej když... |
|------------|----------------|-------------------|
| `RequestIdMiddleware` | Trasování requestů přes logy, microservices | Jednoduchá aplikace bez distribuovaného logování |
| `MethodOverrideMiddleware` | Klienti nepodporují PUT/PATCH/DELETE (legacy browsers, firewally) | Moderní SPA/mobile aplikace |
| `RequestSizeLimitMiddleware` | Omezení velikosti PŘED načtením do RAM (DoS ochrana) | Už používáš `maxRequestBodySize` v DI (dělá totéž) |
| `CORSMiddleware` | Frontend běží na jiné doméně než API | Same-origin aplikace, server-to-server komunikace |
| `BasicAuthMiddleware` | Rychlá ochrana interních/admin endpointů, dev prostředí | Produkční user-facing API (použij JWT/OAuth) |
| `RateLimitMiddleware` | Veřejné API, ochrana před abuse, brute-force | Interní microservices za VPN |
| `EnforceHttpsMiddleware` | Produkce - vynucení HTTPS s redirectem | Dev prostředí, už řeší reverse proxy |
| `AuditLogMiddleware` | Compliance požadavky, debugging, analytics | Vysoký traffic bez potřeby logování |

### Příklady kombinací

**Veřejné REST API (SPA frontend):**
```neon
api:
    middlewares:
        - Sabservis\Api\Middleware\RequestIdMiddleware      # trasování
        - Sabservis\Api\Middleware\CORSMiddleware           # cross-origin
        - Sabservis\Api\Middleware\RateLimitMiddleware      # ochrana před abuse
        - App\Middleware\JwtAuthMiddleware                  # vlastní auth
```

**Interní admin API:**
```neon
api:
    middlewares:
        - Sabservis\Api\Middleware\EnforceHttpsMiddleware   # bezpečnost
        - Sabservis\Api\Middleware\BasicAuthMiddleware      # jednoduchá ochrana
        - Sabservis\Api\Middleware\AuditLogMiddleware       # audit trail
```

## Konfigurace

```neon
api:
    middlewares:
        # Jednoduše - priorita z #[MiddlewarePriority], jinak 10
        - App\Middleware\AuthMiddleware

        # S argumenty - priorita z #[MiddlewarePriority]
        - App\Middleware\CacheMiddleware(ttl: 60)

        # S explicitní prioritou (přebije atribut)
        - { class: App\Middleware\CacheMiddleware(ttl: 60), priority: 100 }

        # Odkaz na službu - priorita MUSÍ být explicitní
        - { class: @myMiddleware, priority: 100 }
```

U odkazu na službu (`@myMiddleware`) nelze při kompilaci zjistit třídu, takže `#[MiddlewarePriority]` se nepřečte. Bez `priority` dostane middleware výchozí prioritu 10.

## CORS

```neon
api:
    cors:
        enabled: true
        allowedOrigins:
            - https://example.com
            - https://app.example.com
        allowedMethods: [GET, POST, PUT, PATCH, DELETE, OPTIONS]
        allowedHeaders: [Content-Type, Authorization, X-Requested-With]
        allowCredentials: false
        maxAge: 3600  # 1 hour - lower values safer during rolling deployments
        exposedHeaders: []
```

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
            ipLimits:
                '10.0.0.0/8': { maxRequests: 1000 }  # Interní síť
        )
```

## Method Override

`X-HTTP-Method-Override` se uplatní jen u **POST** requestu (stejně jako Symfony/Laravel). GET, HEAD a ostatní metody se nepřepíší - cache, proxy a WAF považují GET za bezpečný, `GET` + override `DELETE` by jejich pravidla obešel.

```neon
api:
    middlewares:
        - Sabservis\Api\Middleware\MethodOverrideMiddleware
        # Staré chování (override i z GET) - nedoporučeno
        - Sabservis\Api\Middleware\MethodOverrideMiddleware(overridableMethods: [POST, GET])
```

## Request Size Limit

```neon
api:
    middlewares:
        - Sabservis\Api\Middleware\RequestSizeLimitMiddleware(maxBodySize: 10485760)
```

## Request ID

Automaticky generuje nebo propaguje `X-Request-ID`:

```php
public function action(ApiRequest $request): ApiResponse
{
    $requestId = $request->getAttribute('requestId');
    $this->logger->info('Processing', ['request_id' => $requestId]);
}
```

## Vlastní middleware

```php
use Sabservis\Api\Attribute\Core\MiddlewarePriority;
use Sabservis\Api\Http\ApiRequest;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\Middleware\Middleware;

#[MiddlewarePriority(100)]
class AuthMiddleware implements Middleware
{
    public function __invoke(
        ApiRequest $request,
        ApiResponse $response,
        callable $next,
    ): ApiResponse
    {
        $token = $request->getHeader('Authorization');

        if (!$this->isValid($token)) {
            throw new ClientErrorException('Unauthorized', 401);
        }

        $user = $this->getUser($token);
        $request = $request->withAttribute('user', $user);

        return $next($request, $response);
    }
}
```

## Audit Logging

Pro compliance požadavky:

```neon
services:
    - Sabservis\Api\Middleware\AuditLogMiddleware(@Psr\Log\LoggerInterface)
```

Loguje: method, path, status, duration, user_id, IP, user-agent.

## Více viz

- [Security](security.md)
- [Getting Started](getting-started.md)
