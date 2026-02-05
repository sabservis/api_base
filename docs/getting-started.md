# Getting Started

## Instalace

```bash
composer require sabservis/api
```

## Konfigurace

Zaregistruj extension v Nette DI:

```neon
# config.neon
extensions:
    api: Sabservis\Api\DI\ApiExtension

api:
    debug: %debugMode%

    router:
        basePath: /api

    # Volitelné - vlastní middleware
    middlewares:
        - App\Api\Middleware\AuthMiddleware

    # Volitelné - vlastní serializer/validator
    serializer: Sabservis\Api\Mapping\Serializer\DataMapperSerializer()
    validator: App\Api\SymfonyValidator()
```

## Entrypoint

Vytvoř vstupní bod aplikace:

```php
// www/index.php

use App\Bootstrap;
use Sabservis\Api\Application\ApiApplication;

require __DIR__ . '/../vendor/autoload.php';

Bootstrap::boot()
    ->createContainer()
    ->getByType(ApiApplication::class)
    ->run();
```

## První Controller

```php
<?php

namespace App\Api\Controller;

use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Tag;
use Sabservis\Api\UI\Controller\Controller;

#[Tag(name: 'health', description: 'Health checks')]
class HealthController implements Controller
{
    #[Get(path: '/health')]
    public function check(): array
    {
        return ['status' => 'ok'];
    }
}
```

Zaregistruj controller jako službu:

```neon
services:
    - App\Api\Controller\HealthController
```

## Ověření

```bash
curl http://localhost/api/health
# {"status":"ok"}

curl http://localhost/api/openapi.json
# OpenAPI specifikace
```

## Další kroky

1. **[Cheatsheet](cheatsheet.md)** - Kompletní CRUD příklad, copy-paste ready
2. [Controllers & Routing](controllers.md) - Definice endpointů
3. [Parameters](parameters.md) - Path a query parametry
4. [OpenAPI](openapi.md) - Dokumentace API
