# Cheatsheet

Rychlý přehled pro 90% use cases. Copy-paste ready.

## Kompletní CRUD Controller

```php
<?php

namespace App\Api\Controller;

use Sabservis\Api\Attribute\OpenApi\Delete;
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Put;
use Sabservis\Api\Attribute\OpenApi\RequestBody;
use Sabservis\Api\Attribute\OpenApi\Response;
use Sabservis\Api\Attribute\OpenApi\Tag;
use Sabservis\Api\Exception\Api\ClientErrorException;
use Sabservis\Api\Http\ApiResponse;
use Sabservis\Api\UI\Controller\Controller;

#[Tag(name: 'users', description: 'User management')]
class UserController implements Controller
{
    public function __construct(
        private UserRepository $users,
    ) {}

    // GET /users - seznam s pagination
    #[Get(path: '/users')]
    #[Response(listRef: UserDto::class, withMeta: true)]
    public function list(int $limit = 20, int $offset = 0): ApiResponse
    {
        $users = $this->users->findAll($limit, $offset);
        $total = $this->users->count();

        return ApiResponse::list($users, total: $total, limit: $limit, offset: $offset);
    }

    // GET /users/{id} - detail
    #[Get(path: '/users/{id}')]
    #[Response(ref: UserDto::class)]
    #[Response(404)]
    public function get(int $id): UserDto
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw new ClientErrorException('User not found', 404);
        }

        return UserDto::from($user);
    }

    // POST /users - vytvoření
    #[Post(path: '/users')]
    #[RequestBody(ref: CreateUserDto::class)]
    #[Response(201, ref: UserDto::class)]
    public function create(CreateUserDto $input): ApiResponse
    {
        $user = $this->users->create($input);

        return ApiResponse::created(UserDto::from($user));
    }

    // PUT /users/{id} - aktualizace
    #[Put(path: '/users/{id}')]
    #[RequestBody(ref: UpdateUserDto::class)]
    #[Response(ref: UserDto::class)]
    #[Response(404)]
    public function update(int $id, UpdateUserDto $input): UserDto
    {
        $user = $this->users->update($id, $input);

        return UserDto::from($user);
    }

    // DELETE /users/{id} - smazání
    #[Delete(path: '/users/{id}')]
    #[Response(204)]
    public function delete(int $id): ApiResponse
    {
        $this->users->delete($id);

        return ApiResponse::noContent();
    }
}
```

## DTO s validací

```php
<?php

use Pocta\DataMapper\Validation\Email;
use Pocta\DataMapper\Validation\Length;
use Pocta\DataMapper\Validation\NotBlank;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;

#[Schema(title: 'CreateUser')]
class CreateUserDto
{
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    #[Property(description: 'User name')]
    public string $name;

    #[NotBlank]
    #[Email]
    #[Property(description: 'Email address')]
    public string $email;
}

#[Schema(title: 'User')]
class UserDto
{
    #[Property(description: 'User ID', readOnly: true)]
    public int $id;

    #[Property(description: 'User name')]
    public string $name;

    #[Property(description: 'Email address')]
    public string $email;

    public static function from(User $user): self
    {
        $dto = new self();
        $dto->id = $user->id;
        $dto->name = $user->name;
        $dto->email = $user->email;
        return $dto;
    }
}
```

## Atributy - rychlý přehled

### HTTP metody
```php
#[Get(path: '/users')]
#[Post(path: '/users')]
#[Put(path: '/users/{id}')]
#[Delete(path: '/users/{id}')]
#[Patch(path: '/users/{id}')]
```

### Response
```php
#[Response(ref: UserDto::class)]                      // 200 + objekt (default)
#[Response(listRef: UserDto::class)]                  // 200 + pole
#[Response(listRef: UserDto::class, withMeta: true)]  // 200 + pole + pagination
#[Response(listRef: [A::class, B::class])]            // pole s oneOf
#[Response(listRef: [A::class, B::class], withMeta: true)]  // oneOf + pagination
#[Response(201, ref: UserDto::class)]                 // 201 + objekt
#[Response(404)]                                      // 404 (auto description)
```

### Parametry (jen když potřebuješ popis)
```php
#[PathParameter(name: 'id', type: 'int', description: 'User ID')]
#[QueryParameter(name: 'limit', type: 'int', description: 'Max items')]
#[HeaderParameter(name: 'X-API-Key', type: 'string')]
#[CookieParameter(name: 'session', type: 'string')]
```

### Request body
```php
#[RequestBody(ref: CreateUserDto::class)]
```

### Organizace
```php
#[Tag(name: 'users', description: 'User management')]  // na controller
#[Hidden]                                               // skryje z OpenAPI
#[Security([['Bearer' => []]])]                         // auth requirement
#[Security([])]                                         // verejny endpoint
#[Authorize(activity: 'users.read', authorizer: UsersAuthorizer::class)] // runtime autorizace
```

### Property constraints
```php
#[Property(description: 'Name', example: 'John')]          // zakladni
#[Property(minLength: 1, maxLength: 255)]                  // delka
#[Property(minimum: 0, maximum: 999)]                      // cislo
#[Property(pattern: '^\d{3}-\d{4}$')]                      // regex
#[Property(enum: StatusEnum::class)]                        // enum
#[Property(nullable: true, default: null)]                  // nullable
#[Property(readOnly: true)]                                 // jen cteni
#[Property(writeOnly: true)]                                // jen zapis
#[Property(deprecated: true)]                               // deprecated
#[Property(property: 'json_name')]                          // alias
```

`#[Property]` funguje i na promoted constructor parametrech (immutable DTO):
```php
public function __construct(
    #[Property(description: 'Username')]
    public readonly string $username,
) {}
```

### Pole a mapy
```php
#[Property(type: 'array', items: new Items(type: 'string'))]          // pole stringu
#[Property(type: 'array', items: new Items(ref: Dto::class))]         // pole DTO
#[Property(type: 'object', additionalProperties: new AdditionalProperties(type: 'string'))]  // mapa
#[Property(type: 'object', additionalProperties: new AdditionalProperties(ref: Dto::class))] // mapa DTO
```

### Soubory
```php
#[FileUpload(name: 'file')]                                        // upload
#[FileUpload(name: 'docs', multiple: true)]                        // vice souboru
#[FileUpload(name: 'photo', allowedTypes: ['image/jpeg'])]         // s MIME validaci
#[FileResponse(contentType: 'application/pdf', filename: 'r.pdf')] // download
```

### Pokrocile
```php
#[Discriminator(propertyName: 'type', mapping: [...])]  // polymorfni typy
#[OpenApiMerge(['deprecated' => true])]                  // custom OpenAPI spec
#[Alias('/v2/users')]                                    // alternativni URL
#[ExternalDocumentation(url: 'https://...')]             // externi docs
```

### Inline content s priklady
```php
// Jednoduchy priklad
#[Response(200, content: new JsonContent(ref: Dto::class, example: ['id' => 1]))]

// Pojmenovane priklady
#[Response(200, content: new JsonContent(
    ref: Dto::class,
    examples: [
        new Examples(example: 'ok', summary: 'Success', value: ['id' => 1]),
        new Examples(example: 'empty', summary: 'Empty', value: null),
    ],
))]
```

## Response helpers

```php
return ApiResponse::ok($data);           // 200
return ApiResponse::created($data);      // 201
return ApiResponse::noContent();         // 204

return ApiResponse::list($items);                                    // plain array
return ApiResponse::list($items, total: 100, limit: 20, offset: 0);  // s pagination
```

## Exceptions

```php
throw new ClientErrorException('Not found', 404);
throw new ClientErrorException('Invalid data', 400);
throw (new ValidationException('Invalid request data'))
    ->withFields(['email' => ['Invalid email']]);
```

## Konfigurace (config.neon)

```neon
extensions:
    api: Sabservis\Api\DI\ApiExtension

api:
    debug: %debugMode%
    router:
        basePath: /api
    serializer: Sabservis\Api\Mapping\Serializer\DataMapperSerializer(
        Pocta\DataMapper\MapperOptions::strict()
    )
```
