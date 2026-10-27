# Parameters & Validation

## Automatická inference

Parametry se automaticky inferují z method signature:

```php
#[Get(path: '/users/{id}')]
public function get(int $id, int $limit = 20, int $offset = 0): array
// id z path, limit a offset z query (mají default → optional)
// Nullable bez defaultu (?int $limit) je také optional - chybějící hodnota → null
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
public function get(
    int $id,
    ?string $include,
    #[HeaderParameter(name: 'X-API-Key')] string $apiKey,
    #[CookieParameter(name: 'session')] ?string $sessionId,
): UserDto
```

Atribut na metodě se do argumentu dosadí podle **stejného jména** (`#[CookieParameter(name: 'session')]` + `string $session`, u hlaviček bez ohledu na velikost písmen). Když se jméno liší (`X-API-Key` → `$apiKey`), dej atribut přímo na argument. Povinnost se pak odvodí ze signatury (`string` → povinný, `?string` / default → volitelný), pokud ji neurčíš přes `required:`.

Názvy path proměnných mohou obsahovat pomlčku (`/users/{user-id}`) - do argumentu je naváže `#[PathParameter(name: 'user-id')] int $userId`.

Hodnoty path parametrů jsou URL-dekódované (`/users/john%20doe` → `'john doe'`, `+` zůstává `+`, `%2F` → `/` uvnitř jednoho segmentu).

Bez `type:` se typ převezme z argumentu metody se stejným jménem (`#[PathParameter(name: 'id', description: 'User ID')]` + `int $id` → `int`). Explicitní `type:` nebo `schema` s typem má přednost. Pokud argument se stejným jménem neexistuje, použije se `string`.

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

Validace request DTO je zapnutá automaticky (`DataMapperEntityValidator`). Stačí přidat validační atributy z `pocta/data-mapper`:

```php
use Pocta\DataMapper\Validation\NotBlank;
use Pocta\DataMapper\Validation\Email;
use Pocta\DataMapper\Validation\Length;
use Pocta\DataMapper\Validation\Valid;

class CreateUserDto
{
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $name;

    #[NotBlank]
    #[Email]
    public string $email;

    #[Valid]
    public AddressDto $address;  // rekurzivní validace nested objektu
}
```

Neinicializované properties s validačními atributy automaticky vrací chybu `"This field is required."`.

Vypnutí validace:

```neon
api:
    validator: null
```

Vlastní validátor (implementuje `Sabservis\Api\Mapping\Validator\EntityValidator`):

```neon
api:
    validator: App\Api\Validator\MyValidator()
```

## Validační chyby

Při nevalidních datech se vrací `422 Unprocessable Entity`:

```json
{
    "message": "Invalid request data",
    "code": 422,
    "context": {
        "validation": {
            "client.companyIdentificationNumber": [
                "This value is too long. It should have 12 characters or less."
            ]
        }
    }
}
```

Validační klíče jsou cesty k fieldům:
- nested objekt: `client.companyIdentificationNumber`
- pole: `client.addresses[1].companyIdentificationNumber`

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
