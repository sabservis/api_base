# SAB Servis API Base

Based on Apitte and Nette Framework.

## API

### Setup

Register Base API using `ApiExtension` to your Nette-based application.

```neon
# config.neon

extensions:
    api: Sabservis\Api\DI\ApiExtension

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
