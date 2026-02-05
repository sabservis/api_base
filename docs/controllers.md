# Controllers & Routing

## Základní controller

Controller musí implementovat `Controller` interface:

```php
use Sabservis\Api\Attribute\OpenApi\Get;
use Sabservis\Api\Attribute\OpenApi\Post;
use Sabservis\Api\Attribute\OpenApi\Put;
use Sabservis\Api\Attribute\OpenApi\Delete;
use Sabservis\Api\Attribute\OpenApi\Tag;
use Sabservis\Api\UI\Controller\Controller;

#[Tag(name: 'users', description: 'User management')]
class UserController implements Controller
{
    public function __construct(
        private UserRepository $users,
    ) {}

    #[Get(path: '/users')]
    public function list(): array
    {
        return $this->users->findAll();
    }

    #[Get(path: '/users/{id}')]
    public function get(int $id): UserDto
    {
        return $this->users->find($id);
    }

    #[Post(path: '/users')]
    public function create(CreateUserDto $input): ApiResponse
    {
        return ApiResponse::created($this->users->create($input));
    }

    #[Put(path: '/users/{id}')]
    public function update(int $id, UpdateUserDto $input): ApiResponse
    {
        return ApiResponse::ok($this->users->update($id, $input));
    }

    #[Delete(path: '/users/{id}')]
    public function delete(int $id): ApiResponse
    {
        $this->users->delete($id);
        return ApiResponse::noContent();
    }
}
```

## HTTP metody

Dostupné atributy pro HTTP metody:

| Atribut | HTTP metoda |
|---------|-------------|
| `#[Get]` | GET |
| `#[Post]` | POST |
| `#[Put]` | PUT |
| `#[Patch]` | PATCH |
| `#[Delete]` | DELETE |
| `#[Head]` | HEAD |
| `#[Options]` | OPTIONS |

## Path parametry

Parametry v URL se automaticky injektují do metody:

```php
#[Get(path: '/users/{id}')]
public function get(int $id): UserDto  // $id se automaticky převede na int

#[Get(path: '/users/{userId}/posts/{postId}')]
public function getPost(int $userId, int $postId): PostDto
```

Podporované typy:
- `int`, `float`, `string`, `bool`
- `DateTimeImmutable`, `DateTime`
- `BackedEnum` (string nebo int)

## Alias routy

Endpoint může mít více URL:

```php
#[Get(path: '/employees/{id}')]
#[Alias('/contacts/{id}')]
#[Alias('/people/{id}')]
public function get(int $id): EmployeeDto
```

## Return typy

Controller metoda může vracet:

```php
// DTO objekt - automaticky serializován na JSON
#[Get(path: '/users/{id}')]
public function get(int $id): UserDto

// Array - automaticky serializován na JSON
#[Get(path: '/users')]
public function list(): array

// ApiResponse - plná kontrola nad odpovědí
#[Post(path: '/users')]
public function create(ApiRequest $request): ApiResponse
{
    return ApiResponse::created($user);
}
```

## Tagy

Seskupení endpointů v OpenAPI dokumentaci:

```php
#[Tag(name: 'users', description: 'User management')]
class UserController implements Controller
{
    // Všechny metody budou mít tag "users"
}
```

## Více viz

- [Request & Response](request-response.md)
- [Parameters](parameters.md)
- [OpenAPI](openapi.md)
