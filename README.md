# SAB Servis API Base

Based on Apitte and Nette Framework.

## API

### Setup

Register Base API using `ApiExtension` to your Nette-based application.

```neon
# config.neon

extensions:
    api: Sabservis\Api\DI\ApiExtension

api:
    # Register middlewares
    middlewares:
        - App\Core\Api\Middleware\AuthenticationMiddleware
        - App\Core\Api\Middleware\AuthorizationMiddleware

    validator: App\Core\Api\Mapping\SymfonyValidator()
    router:
        basePath: /api # If our API is located in subdirectory /api
```
After that, create entrypoint to your Nette-based application. For example `www/index.php` looks like that.

```php
// www/index.php

use App\Bootstrap;
use Sabservis\Api\Application\Application;

require __DIR__ . '/../vendor/autoload.php';

Bootstrap::boot()
    ->createContainer()
    ->getByType(Application::class)
    ->run();
```
