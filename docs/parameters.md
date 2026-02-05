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

## Parameter-level example, style, explode

Parametry podporují `example`, `style` a `explode` přímo na úrovni parametru (bez schema):

```php
#[Get(path: '/search')]
#[QueryParameter(name: 'query', required: false, schema: new Schema(type: 'string'), example: 'laptop')]
#[QueryParameter(name: 'ids', style: 'form', explode: true, schema: new Schema(type: 'array'))]
public function search(?string $query = null): array
```

| Parametr | Popis |
|----------|-------|
| `example` | Ukázkový hodnota parametru (zobrazí se v OpenAPI spec na úrovni parametru) |
| `style` | Způsob serializace (`form`, `simple`, `label`, `matrix`, `spaceDelimited`, `pipeDelimited`, `deepObject`) |
| `explode` | Zda se pole/objekt rozloží na jednotlivé parametry |

`example` uvnitř `schema` se zobrazí ve schema objektu, `example` na parametru se zobrazí na úrovni samotného parametru.

## Pokročilé OpenAPI schema

Pro detailní OpenAPI constraints použij `schema` parametr:

```php
use Sabservis\Api\Attribute\OpenApi\Schema;

#[Get(path: '/items')]
#[QueryParameter(name: 'offset', schema: new Schema(type: 'integer', minimum: 0))]
#[QueryParameter(name: 'limit', schema: new Schema(type: 'integer', minimum: 1, maximum: 100))]
#[QueryParameter(name: 'status', schema: new Schema(type: 'string', enum: ['active', 'inactive', 'pending']))]
public function list(int $offset = 0, int $limit = 20, ?string $status = null): array
```

Schema podporuje:
- `type`, `format` - datový typ a formát
- `enum` - povolené hodnoty (pole nebo `BackedEnum::class`)
- `minimum`, `maximum` - číselné rozsahy
- `minLength`, `maxLength` - délka řetězce
- `minItems`, `maxItems` - počet položek pole
- `pattern` - regex pattern
- `nullable`, `default`, `example`
- `title`, `description` - popis pro dokumentaci
- `deprecated` - označení jako deprecated
- `readOnly`, `writeOnly` - omezení čtení/zápisu

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
