# OpenAPI

Knihovna automaticky generuje OpenAPI 3.0.3 specifikaci z PHP atributů.

## Endpoint

Specifikace je dostupná na:

```
GET /api/openapi.json
```

## Response dokumentace

```php
#[Get(path: '/users/{id}')]
#[Response(ref: UserDto::class)]
#[Response(404)]
public function get(int $id): UserDto
```

### Response typy

```php
#[Response(ref: UserDto::class)]                      // 200 + objekt (default)
#[Response(listRef: UserDto::class)]                  // 200 + pole
#[Response(listRef: UserDto::class, withMeta: true)]  // 200 + pole + pagination
#[Response(201, ref: UserDto::class)]                 // 201 + objekt
#[Response(404)]                                      // 404 (auto description)
#[Response(404, description: 'Custom message')]       // 404 + custom popis
```

## Schema pro DTO

```php
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Attribute\OpenApi\Property;

#[Schema(title: 'User')]
class UserDto
{
    #[Property(description: 'User ID', readOnly: true)]
    public int $id;

    #[Property(description: 'Email address', format: 'email')]
    public string $email;

    #[Property(description: 'User status')]
    public UserStatus $status;  // enum - automaticky rozpoznán
}
```

## File Upload

```php
use Sabservis\Api\Attribute\OpenApi\FileUpload;

#[Post(path: '/users/{id}/avatar')]
#[FileUpload(name: 'avatar', description: 'User avatar image')]
public function uploadAvatar(int $id, ApiRequest $request): ApiResponse
{
    $file = $request->getUploadedFile('avatar');
    // ...
}
```

## File Download

```php
use Sabservis\Api\Attribute\OpenApi\FileResponse;

#[Get(path: '/files/{id}/download')]
#[FileResponse(filename: 'document.pdf', contentType: 'application/pdf')]
public function download(int $id): FileResponse
```

## Skrytí z dokumentace

```php
use Sabservis\Api\Attribute\OpenApi\Hidden;

#[Get(path: '/internal/debug')]
#[Hidden]
public function debug(): array
```

---

## Pokročilé

### Pole v DTO

```php
use Sabservis\Api\Attribute\OpenApi\Items;

#[Property(type: 'array', items: new Items(type: 'string'))]
public array $tags;

#[Property(type: 'array', items: new Items(ref: RoleDto::class))]
public array $roles;
```

### Polymorfní typy (oneOf/anyOf)

```php
use Sabservis\Api\Attribute\OpenApi\JsonContent;
use Sabservis\Api\Attribute\OpenApi\Items;

#[Response(
    response: 200,
    content: new JsonContent(
        type: 'array',
        items: new Items(oneOf: [ArticleDto::class, VideoDto::class]),
    ),
)]
public function feed(): array
```

### Discriminator

```php
use Sabservis\Api\Attribute\OpenApi\Discriminator;

#[Schema]
#[Discriminator(propertyName: 'type', mapping: [
    'article' => ArticleDto::class,
    'video' => VideoDto::class,
])]
abstract class ContentDto
{
    public string $type;
}
```

### Custom OpenAPI spec

```php
use Sabservis\Api\Attribute\OpenApi\OpenApiMerge;

#[Get(path: '/legacy')]
#[OpenApiMerge([
    'deprecated' => true,
    'externalDocs' => ['url' => 'https://docs.example.com'],
])]
public function legacy(): LegacyDto
```

### Security Schemes

```php
use Sabservis\Api\Attribute\OpenApi\SecurityScheme;

#[SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
class UserController implements Controller
```

## Více viz

- [Cheatsheet](cheatsheet.md) - Kompletní příklad
- [Controllers](controllers.md)
- [Parameters](parameters.md)
