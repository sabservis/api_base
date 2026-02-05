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

## Client IP Detection

```php
$request->getClientIp();  // Vrací skutečnou IP klienta
```

**Za reverse proxy** je nutné nakonfigurovat `trustedProxies` (viz HTTPS Detection). Bez konfigurace vrací `REMOTE_ADDR` (IP proxy), s konfigurací parsuje `X-Forwarded-For` header a vrací skutečnou klientskou IP.

**SECURITY:** Bez `trustedProxies` je `X-Forwarded-For` ignorován, aby útočník nemohl spoofovat svou IP adresu.

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
