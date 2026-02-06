# Sabservis API

Moderní PHP REST API framework pro Nette s automatickou OpenAPI dokumentací.

[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-1258_passed-brightgreen.svg)](tests/)
[![Assertions](https://img.shields.io/badge/assertions-2817-blue.svg)](tests/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level_max-brightgreen.svg)](https://phpstan.org/)
[![Code Style](https://img.shields.io/badge/code_style-Slevomat_CS-blue.svg)](https://github.com/slevomat/coding-standard)

## Features

- **Deklarativní routing** - Definuj endpointy pomocí PHP 8 atributů
- **OpenAPI 3.0** - Automatické generování dokumentace
- **Type-safe** - Plná podpora typovaných parametrů, enumů, DTO
- **Middleware** - Rozšiřitelný middleware pipeline (CORS, rate limiting, auth)
- **Validace** - Vestavěná validace requestů s detailními chybami
- **File Uploads** - Server-side MIME validace, filename sanitizace, symlink ochrana
- **Security** - Rate limiting, request size limits, path traversal ochrana

## Quick Start

```bash
composer require sabservis/api
```

```neon
# config.neon
extensions:
    api: Sabservis\Api\DI\ApiExtension

api:
    debug: %debugMode%
    maxRequestBodySize: 10485760  # 10MB limit (DoS ochrana)
    trustedProxies:               # Za reverse proxy (nginx, Cloudflare)
        - 10.0.0.0/8
        - 172.16.0.0/12
    router:
        basePath: /api
```

```php
// www/index.php
Bootstrap::boot()
    ->createContainer()
    ->getByType(ApiApplication::class)
    ->run();
```

```php
#[Tag(name: 'users')]
class UserController implements Controller
{
    #[Get(path: '/users/{id}')]
    public function get(int $id): UserDto
    {
        return $this->users->find($id);
    }

    #[Post(path: '/users')]
    #[RequestBody(ref: CreateUserDto::class)]
    public function create(ApiRequest $request): ApiResponse
    {
        $dto = $request->getEntity();
        return ApiResponse::created($this->users->create($dto));
    }
}
```

## File Uploads

Bezpečné nahrávání souborů s automatickou MIME validací:

```php
#[Post(path: '/documents')]
#[FileUpload(name: 'file', allowedTypes: ['application/pdf', 'image/jpeg', 'image/png'])]
public function upload(ApiRequest $request): ApiResponse
{
    $file = $request->getUploadedFile('file');

    // MIME type je automaticky validován pomocí finfo (magic bytes)
    // Klientský Content-Type header je ignorován (nelze spoofovat)

    // Bezpečný přesun - vytvoří adresář, sanitizuje název, nepřepíše existující
    $path = $file->moveToDirectory('/uploads');

    return ApiResponse::created(['filename' => basename($path)]);
}
```

**Bezpečnostní funkce:**
- Server-side MIME detekce (`finfo`) - klient nemůže spoofovat typ souboru
- Automatická filename sanitizace - ochrana proti path traversal (`../`)
- Symlink ochrana - volitelné blokování symlinků
- Validace `allowedTypes` na úrovni dispatcheru (415 Unsupported Media Type)

```php
// Manuální validace v kontroleru
$file->getValidatedContentType();              // Server-side MIME type
$file->isAllowedType(['application/pdf']);     // true/false
$file->assertAllowedType(['application/pdf']); // throws exception
$file->getSanitizedName();                     // Bezpečný filename

// Bezpečný přesun souboru
$file->moveTo($path);                          // Nepřepíše existující (safe default)
$file->moveTo($path, overwrite: true);         // Explicitní přepsání
$file->moveToDirectory($dir);                  // Auto: vytvoří dir, sanitizuje název
$file->moveToDirectory($dir, 'custom.pdf');    // Vlastní název (sanitizovaný)
```

## Documentation

| Téma | Popis |
|------|-------|
| [Getting Started](docs/getting-started.md) | Instalace, konfigurace, první endpoint |
| [Controllers & Routing](docs/controllers.md) | Definice endpointů, HTTP metody, path parametry |
| [Request & Response](docs/request-response.md) | Práce s ApiRequest a ApiResponse |
| [Parameters & Validation](docs/parameters.md) | Query, path, header parametry, validace |
| [OpenAPI](docs/openapi.md) | Automatická dokumentace, Schema atributy |
| [Middleware](docs/middleware.md) | Vestavěné middleware, vlastní middleware |
| [Security](docs/security.md) | Rate limiting, file security, best practices |
| [Testing](docs/testing.md) | ApiTestClient, testování controllerů |

## Requirements

- PHP 8.1+
- Nette DI 3.2+
- Symfony Cache 6.4+

## License

[MIT](LICENSE)
