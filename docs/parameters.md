# Parameters & Validation

## Automatická inference

Parametry se automaticky inferují z method signature:

```php
#[Get(path: '/users/{id}')]
public function get(int $id, int $limit = 20, int $offset = 0): array
// id z path, limit a offset z query (mají default → optional)
```

## Explicitní definice

Pro popis nebo nestandardní nastavení použij atributy:

```php
use Sabservis\Api\Attribute\OpenApi\PathParameter;
use Sabservis\Api\Attribute\OpenApi\QueryParameter;
use Sabservis\Api\Attribute\OpenApi\HeaderParameter;
use Sabservis\Api\Attribute\OpenApi\CookieParameter;

#[Get(path: '/users/{id}')]
#[PathParameter(name: 'id', type: 'int', description: 'User ID')]
#[QueryParameter(name: 'include', type: 'string', required: false)]
#[HeaderParameter(name: 'X-API-Key', type: 'string')]
public function get(int $id, ?string $include, string $apiKey): UserDto
```

## Podporované typy

| PHP typ | Příklad hodnoty | Poznámka |
|---------|-----------------|----------|
| `int` | `123` | |
| `float` | `3.14` | |
| `string` | `hello` | |
| `bool` | `true`, `1`, `yes` | Podporuje různé formáty |
| `array` | `?ids[]=1&ids[]=2` | |
| `DateTimeImmutable` | `2024-01-15`, `2024-01-15T10:30:00` | ISO 8601 |
| `BackedEnum` | `active`, `1` | String nebo int enum |

## Request Body

DTO se automaticky injektuje jako parametr metody:

```php
#[Post(path: '/users')]
#[RequestBody(ref: CreateUserDto::class, required: true)]
public function create(CreateUserDto $input): UserDto
{
    // $input je automaticky deserializovaný a validovaný
    return $this->users->create($input);
}

#[Put(path: '/users/{id}')]
public function update(int $id, UpdateUserDto $input): UserDto
{
    // $id z path, $input z request body
    return $this->users->update($id, $input);
}
```

Alternativně přes ApiRequest (pro větší kontrolu):

```php
#[Post(path: '/users')]
public function create(ApiRequest $request): ApiResponse
{
    $dto = $request->getTypedEntity(CreateUserDto::class);
    // ...
}
```

### DTO validace

S `igorpocta/data-mapper`:

```php
use Pocta\DataMapper\Validation\NotBlank;
use Pocta\DataMapper\Validation\Email;
use Pocta\DataMapper\Validation\Length;

class CreateUserDto
{
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $name;

    #[NotBlank]
    #[Email]
    public string $email;
}
```

Konfigurace serializeru s validací:

```neon
api:
    serializer: Sabservis\Api\Mapping\Serializer\DataMapperSerializer(
        Pocta\DataMapper\MapperOptions::strict()
    )
```

## Validační chyby

Při nevalidních datech se vrací `422 Unprocessable Entity`:

```json
{
    "error": "Validation failed",
    "code": 422,
    "fields": {
        "email": ["Invalid email format"],
        "name": ["Name is required", "Name must be at least 2 characters"]
    }
}
```

## Query Parameters DTO (zkratka)

Pro dokumentaci query parametrů z DTO:

```php
#[Get(path: '/users', queryParametersRef: UserFilterDto::class)]
public function list(ApiRequest $request): array
{
    $query = $request->getQueryParam('query');
    $status = $request->getQueryParam('status');
    // ...
}
```

## Více viz

- [Request & Response](request-response.md)
- [OpenAPI](openapi.md)
