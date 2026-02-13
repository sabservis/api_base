# OpenAPI

Knihovna automaticky generuje OpenAPI 3.0.3 specifikaci z PHP atributů.

## Endpoint

Specifikace je dostupná na:

```
GET /api/openapi.json
```

## Operation metadata

HTTP atributy (`#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`) podporují:

```php
#[Get(
    path: '/users/{id}',
    summary: 'Get user by ID',                              // krátký popis
    description: 'Returns user details including profile.', // detailní popis (Markdown)
    operationId: 'getUser',                                 // ID pro generované klienty
    deprecated: true,                                       // označení jako deprecated
)]
public function get(int $id): UserDto
```

| Parametr | Popis |
|----------|-------|
| `path` | URL pattern (e.g., `/users/{id}`) |
| `summary` | Krátký popis (zobrazí se v přehledu) |
| `description` | Detailní popis, podporuje Markdown |
| `operationId` | Unikátní ID pro generované API klienty |
| `deprecated` | Označí endpoint jako deprecated |

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

### List s oneOf (polymorfní typy)

Pro list response s více možnými typy použij pole v `listRef`:

```php
#[Response(listRef: [ArticleDto::class, VideoDto::class])]                  // pole s oneOf
#[Response(listRef: [ArticleDto::class, VideoDto::class], withMeta: true)]  // s pagination
```

Vygenerovaná OpenAPI spec:

```yaml
responses:
  200:
    content:
      application/json:
        schema:
          type: object
          required: [data, meta]
          properties:
            data:
              type: array
              items:
                oneOf:
                  - $ref: '#/components/schemas/ArticleDto'
                  - $ref: '#/components/schemas/VideoDto'
            meta:
              type: object
              properties:
                total: { type: integer }
                limit: { type: integer }
                offset: { type: integer }
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

### Property - kompletni reference

`#[Property]` atribut nastavuje OpenAPI metadata pro DTO property.
Lze ho použít jak na klasické public property, tak na promoted constructor parametry:

```php
use Pocta\DataMapper\Validation\Length;
use Pocta\DataMapper\Validation\NotBlank;
use Sabservis\Api\Attribute\OpenApi\Property;

final class LoginRequestDto
{
    public function __construct(
        #[Property(description: 'Username', maxLength: 100)]
        #[NotBlank]
        #[Length(max: 100)]
        public readonly string $username,
        #[Property(description: 'Password', maxLength: 255, writeOnly: true)]
        #[NotBlank]
        #[Length(max: 255)]
        public readonly string $password,
    ) {
    }
}
```

Pro promoted parametry se používají stejné argumenty atributu jako pro běžné property.

```php
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Items;
use Sabservis\Api\Attribute\OpenApi\AdditionalProperties;

class ProductDto
{
    // Zakladni
    #[Property(description: 'Product name', example: 'Laptop')]
    public string $name;

    // Validacni constraints
    #[Property(type: 'string', minLength: 1, maxLength: 255, pattern: '^[a-zA-Z0-9 ]+$')]
    public string $title;

    #[Property(minimum: 0, maximum: 999999)]
    public float $price;

    // Enum - class-string nebo pole hodnot
    #[Property(enum: ProductStatus::class)]
    public string $status;

    #[Property(enum: ['small', 'medium', 'large'])]
    public string $size;

    // Metadata
    #[Property(title: 'Product ID', description: 'Unique identifier', readOnly: true)]
    public int $id;

    #[Property(deprecated: true, description: 'Use newField instead')]
    public string $oldField;

    #[Property(writeOnly: true)]
    public string $password;

    #[Property(nullable: true, default: null)]
    public ?string $note;

    // Pole
    #[Property(type: 'array', items: new Items(type: 'string'))]
    public array $tags;

    #[Property(type: 'array', items: new Items(ref: VariantDto::class))]
    public array $variants;

    // Reference na jiny DTO
    #[Property(ref: CategoryDto::class)]
    public CategoryDto $category;

    // Alias - jiny nazev v JSON nez v PHP
    #[Property(property: 'display_name')]
    public string $displayName;

    // Mapa (dynamicke klice)
    #[Property(type: 'object', additionalProperties: new AdditionalProperties(type: 'string'))]
    public array $metadata;

    // Required override - non-nullable property jako optional
    #[Property(required: false)]
    public string $optional;
}
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `property` | `string` | Alias - jiny nazev v JSON (default: nazev PHP property) |
| `title` | `string` | Nazev pro dokumentaci |
| `description` | `string` | Popis property |
| `type` | `string` | OpenAPI typ (`string`, `integer`, `number`, `boolean`, `array`, `object`) |
| `format` | `string` | Format (`email`, `uri`, `uuid`, `date-time`, `int64`, ...) |
| `enum` | `class-string\|array` | Povolene hodnoty - BackedEnum class nebo pole |
| `minimum` / `maximum` | `int\|float` | Ciselne rozsahy |
| `minLength` / `maxLength` | `int` | Delka retezce |
| `pattern` | `string` | Regex pattern |
| `default` | `mixed` | Vychozi hodnota |
| `example` | `mixed` | Ukazkova hodnota |
| `nullable` | `bool` | Zda muze byt null |
| `deprecated` | `bool` | Oznaceni jako deprecated |
| `readOnly` | `bool` | Jen pro cteni (napr. ID) |
| `writeOnly` | `bool` | Jen pro zapis (napr. heslo) |
| `required` | `bool` | Override - `false` pro optional non-nullable property |
| `items` | `Items` | Schema polozek pole |
| `ref` | `class-string` | Reference na jiny DTO |
| `additionalProperties` | `AdditionalProperties` | Schema dynamickych klicu (mapy) |

### Schema atribut

`#[Schema]` na tride definuje metadata a kompozici:

```php
// Zakladni metadata
#[Schema(title: 'User', description: 'User account')]
class UserDto { }

// Skryti z OpenAPI (neprojde do components/schemas)
#[Schema(hidden: true)]
class InternalDto { }

// Oznaceni jako deprecated
#[Schema(deprecated: true)]
class LegacyDto { }

// Vendor extensions
#[Schema(x: ['internal' => true, 'group' => 'admin'])]
class AdminDto { }
// Vygeneruje: x-internal: true, x-group: admin
```

**Kompozice:**

```php
// oneOf - prave jeden z typu
#[Schema(oneOf: [ArticleDto::class, VideoDto::class])]
class ContentDto { }

// anyOf - jeden nebo vice typu
#[Schema(anyOf: [EmailNotification::class, SmsNotification::class])]
class NotificationDto { }

// allOf - kombinace vsech (dedicnost/merge)
#[Schema(allOf: [BaseDto::class, ExtendedDto::class])]
class FullDto { }
```
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

| Parametr | Typ | Default | Popis |
|----------|-----|---------|-------|
| `name` | `string` | (povinny) | Nazev form field |
| `multiple` | `bool` | `false` | Vice souboru pro jedno pole |
| `required` | `bool` | `true` | Zda je soubor povinny |
| `description` | `string` | `null` | Popis pro dokumentaci |
| `allowedTypes` | `array<string>` | `null` | Povolene MIME typy (validace na dispatcheru) |

```php
// Vice souboru
#[FileUpload(name: 'documents', multiple: true, description: 'Attachments')]

// S MIME validaci
#[FileUpload(name: 'photo', allowedTypes: ['image/jpeg', 'image/png'])]

// Vice ruznych souboru (IS_REPEATABLE)
#[FileUpload(name: 'avatar')]
#[FileUpload(name: 'cover', required: false)]
```

## Multipart DTO (File Upload + Form Fields)

Pro endpointy, kde potrebujes kombinaci beznych poli a souboru v jednom pozadavku:

```php
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Property;
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Http\UploadedFile;

#[Schema]
class AddFormalityRequest
{
    #[Property(description: 'Typ nalezitosti')]
    public FormalityType $type;

    #[FileUpload(allowedTypes: ['application/pdf'])]
    public UploadedFile $file;

    #[FileUpload]
    public ?UploadedFile $thumbnail = null;  // volitelny

    #[FileUpload(multiple: true)]
    public array $attachments;               // vice souboru
}
```

Controller:
```php
#[Post(path: '/formality')]
#[RequestBody(ref: AddFormalityRequest::class)]
public function add(AddFormalityRequest $dto): array
{
    $dto->type;          // FormalityType enum
    $dto->file;          // UploadedFile
    $dto->thumbnail;     // UploadedFile|null
    $dto->attachments;   // array<UploadedFile>
}
```

**Klicova pravidla:**
- Required/optional se odvozuje z PHP typu (`UploadedFile` = required, `?UploadedFile` = optional)
- `allowedTypes` a dalsi validace z `#[FileUpload]` atributu
- `name` parametr je volitelny - bez nej se pouzije nazev property
- Nelze kombinovat `#[FileUpload]` na metode a DTO s FileUpload properties na stejnem endpointu
- Framework automaticky generuje `multipart/form-data` misto `application/json`

## File Download

```php
use Sabservis\Api\Attribute\OpenApi\FileResponse;

#[Get(path: '/files/{id}/download')]
#[FileResponse(contentType: 'application/pdf', filename: 'document.pdf')]
public function download(int $id): FileResponse
```

| Parametr | Typ | Default | Popis |
|----------|-----|---------|-------|
| `contentType` | `string` | `application/octet-stream` | MIME typ souboru |
| `filename` | `string` | `null` | Ukazkovy nazev souboru |
| `description` | `string` | `null` | Popis response |
| `response` | `int` | `200` | HTTP status kod |

```php
// Custom status code a popis
#[FileResponse(contentType: 'application/zip', response: 201, description: 'Generated archive')]
```

## Tagy

```php
use Sabservis\Api\Attribute\OpenApi\Tag;

#[Tag(name: 'users', description: 'User management')]
class UserController implements Controller
{
    #[Get(path: '/users')]
    public function list(): array { }  // Dědí tag 'users'

    #[Get(path: '/users/stats')]
    #[Tag(name: 'statistics')]         // Přepíše - má pouze 'statistics'
    public function stats(): array { }
}
```

**Pravidlo:** Pokud metoda má vlastní `#[Tag]`, přepíše tagy z controlleru. Jinak dědí.

## Security

Viz [Security dokumentace](security.md#openapi-security).

```php
use Sabservis\Api\Attribute\OpenApi\Security;

#[Security([['Bearer' => []]])]
class UserController implements Controller
{
    #[Get(path: '/users')]
    public function list(): array { }  // Dědí Bearer

    #[Get(path: '/public')]
    #[Security([])]                    // Přepíše - veřejný endpoint
    public function publicList(): array { }
}
```

**Pravidlo:** Pokud metoda má vlastní `#[Security]`, přepíše security z controlleru. Prázdné pole `[]` = veřejný endpoint.

## Default error response

Všechny endpointy automaticky obsahují `default` response s odkazem na `ErrorResponse` schema. To zajišťuje, že OpenAPI dokumentace vždy popisuje strukturu chybových odpovědí.

Vygenerovaná spec:

```yaml
responses:
  200:
    description: OK
  default:
    description: Error response
    content:
      application/json:
        schema:
          $ref: '#/components/schemas/ErrorResponse'
```

### Vypnutí

```php
new OpenApiConfig(
    includeDefaultErrorResponse: false,
)
```

### Vlastní default response

Pokud endpoint definuje vlastní `default` response, automatický se nepřidá:

```php
#[Response(response: 'default', description: 'Custom error', ref: MyErrorDto::class)]
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

**Items - kompletni reference:**

| Parametr | Typ | Popis |
|----------|-----|-------|
| `ref` | `class-string` | Reference na DTO |
| `type` | `string` | OpenAPI typ (`string`, `integer`, `number`, ...) |
| `format` | `string` | Format (`email`, `uuid`, `date-time`, ...) |
| `enum` | `class-string\|array` | Povolene hodnoty |
| `minimum` / `maximum` | `int\|float` | Ciselne rozsahy |
| `minLength` / `maxLength` | `int` | Delka retezce |
| `example` | `mixed` | Ukazkova hodnota |
| `oneOf` / `anyOf` / `allOf` | `array` | Kompozice - class-stringy nebo Schema instance |

```php
// Pole s validaci polozek
#[Property(type: 'array', items: new Items(type: 'integer', minimum: 1, maximum: 1000))]
public array $ids;

// Pole s enum
#[Property(type: 'array', items: new Items(enum: StatusEnum::class))]
public array $statuses;

// Pole s oneOf (polymorfni polozky)
#[Property(type: 'array', items: new Items(oneOf: [ArticleDto::class, VideoDto::class]))]
public array $feed;

// Pole s allOf (kombinovane polozky)
#[Property(type: 'array', items: new Items(allOf: [BaseDto::class, ExtensionDto::class]))]
public array $items;
```

### AdditionalProperties (mapy)

Pro objekty s dynamickymi klici (slovniky/mapy):

```php
use Sabservis\Api\Attribute\OpenApi\AdditionalProperties;

// Mapa string -> string
#[Property(type: 'object', additionalProperties: new AdditionalProperties(type: 'string'))]
public array $labels;
// { "cs": "Ahoj", "en": "Hello" }

// Mapa string -> integer
#[Property(type: 'object', additionalProperties: new AdditionalProperties(type: 'integer'))]
public array $scores;
// { "math": 95, "english": 87 }

// Mapa string -> DTO
#[Property(type: 'object', additionalProperties: new AdditionalProperties(ref: TranslationDto::class))]
public array $translations;
// { "cs": { "title": "...", "text": "..." }, "en": { ... } }

// Mapa string -> pole
#[Property(type: 'object', additionalProperties: new AdditionalProperties(
    type: 'array',
    items: new Items(type: 'string'),
))]
public array $tagGroups;
// { "colors": ["red", "blue"], "sizes": ["S", "M"] }
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `type` | `string` | OpenAPI typ hodnot |
| `format` | `string` | Format hodnot |
| `description` | `string` | Popis |
| `items` | `Items` | Schema polozek (pokud `type: 'array'`) |
| `ref` | `class-string` | Reference na DTO (pro hodnotove objekty) |

`AdditionalProperties` lze pouzit i na `JsonContent`:

```php
#[Response(
    response: 200,
    content: new JsonContent(
        type: 'object',
        additionalProperties: new AdditionalProperties(type: 'string'),
    ),
)]
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

Pro polymorfní typy s určením konkrétního typu podle hodnoty property:

```php
use Sabservis\Api\Attribute\OpenApi\Schema;
use Sabservis\Api\Attribute\OpenApi\Discriminator;

// Definice konkrétních typů
class ArticleDto
{
    public string $type;
    public string $title;
    public string $content;
}

class VideoDto
{
    public string $type;
    public string $title;
    public string $videoUrl;
}

// Polymorfní typ s discriminatorem
#[Schema(oneOf: [ArticleDto::class, VideoDto::class])]
#[Discriminator(propertyName: 'type', mapping: [
    'article' => ArticleDto::class,
    'video' => VideoDto::class,
])]
class ContentDto
{
}
```

Vygenerovaná OpenAPI spec:

```yaml
ContentDto:
  oneOf:
    - $ref: '#/components/schemas/ArticleDto'
    - $ref: '#/components/schemas/VideoDto'
  discriminator:
    propertyName: type
    mapping:
      article: '#/components/schemas/ArticleDto'
      video: '#/components/schemas/VideoDto'
```

**Poznámky:**
- `Discriminator` funguje pouze s `oneOf` nebo `anyOf` kompozicí v `Schema`
- Bez kompozice se `Discriminator` ignoruje
- `mapping` je volitelný - bez něj klient musí sám určit typ podle `propertyName`

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

// Bearer JWT
#[SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT token in Authorization header',
)]
class UserController implements Controller { }

// API Key
#[SecurityScheme(
    securityScheme: 'apiKey',
    type: 'apiKey',
    name: 'X-API-Key',
    in: 'header',
)]
class ApiController implements Controller { }

// OpenID Connect
#[SecurityScheme(
    securityScheme: 'openId',
    type: 'openIdConnect',
    openIdConnectUrl: 'https://auth.example.com/.well-known/openid-configuration',
)]
class OidcController implements Controller { }
```

| Parametr | Typ | Popis |
|----------|-----|-------|
| `securityScheme` | `string` | Nazev schematu (reference v `#[Security]`) |
| `type` | `string` | `http`, `apiKey`, `oauth2`, `openIdConnect` |
| `description` | `string` | Popis |
| `name` | `string` | Nazev headeru/query/cookie (pro `apiKey`) |
| `in` | `string` | `header`, `query`, `cookie` (pro `apiKey`) |
| `scheme` | `string` | HTTP scheme (`bearer`, `basic`) |
| `bearerFormat` | `string` | Format tokenu (`JWT`) |
| `openIdConnectUrl` | `string` | Discovery URL (pro `openIdConnect`) |

### Server Variables

Pro URL templating s dynamickými částmi:

```php
use Sabservis\Api\Attribute\OpenApi\Server;
use Sabservis\Api\Attribute\OpenApi\ServerVariable;

#[Server(
    url: 'https://{environment}.api.example.com/v{version}',
    description: 'API server',
    variables: [
        new ServerVariable(name: 'environment', default: 'prod', enum: ['prod', 'staging', 'dev']),
        new ServerVariable(name: 'version', default: '1', description: 'API version'),
    ]
)]
class ApiController implements Controller
```

Nebo v konfiguraci:

```php
new OpenApiConfig(
    servers: [
        [
            'url' => 'https://{env}.api.example.com',
            'variables' => [
                'env' => ['default' => 'prod', 'enum' => ['prod', 'staging']],
            ],
        ],
    ],
)
```

### File Upload Encoding

Pro specifikaci content-type a serializace v multipart/form-data:

```php
use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Attribute\OpenApi\Encoding;

#[Post(path: '/documents')]
#[FileUpload(name: 'file')]
#[FileUpload(name: 'metadata')]
#[Encoding(property: 'file', contentType: 'application/pdf')]
#[Encoding(property: 'metadata', contentType: 'application/json')]
public function upload(ApiRequest $request): ApiResponse
```

Encoding podporuje: `contentType`, `headers`, `style`, `explode`, `allowReserved`.

### Inline example

Pro jednoduchy priklad primo na urovni schema:

```php
use Sabservis\Api\Attribute\OpenApi\JsonContent;

// Priklad ve schema (singular)
#[Response(
    response: 200,
    content: new JsonContent(
        ref: UserDto::class,
        example: ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
    ),
)]
public function getUser(int $id): UserDto
```

`example` (singular) se zapise do schema. Pro vice pojmenovanych prikladu na urovni MediaType pouzij `examples` (plural).

### Multiple Examples

Pro pojmenované příklady v response/request:

```php
use Sabservis\Api\Attribute\OpenApi\Examples;
use Sabservis\Api\Attribute\OpenApi\JsonContent;

#[Response(
    response: 200,
    content: new JsonContent(
        ref: UserDto::class,
        examples: [
            new Examples(example: 'active', summary: 'Active user', value: ['id' => 1, 'status' => 'active']),
            new Examples(example: 'inactive', summary: 'Inactive user', value: ['id' => 2, 'status' => 'inactive']),
        ]
    )
)]
public function getUser(int $id): UserDto
```

Lze použít i s `MediaType`:

```php
use Sabservis\Api\Attribute\OpenApi\MediaType;

#[Response(
    response: 200,
    content: new MediaType(
        example: ['ok' => true],  // singular - jednoduchy priklad
    )
)]

#[Response(
    response: 200,
    content: new MediaType(
        examples: [               // plural - pojmenovane priklady
            new Examples(example: 'success', summary: 'Success', value: ['ok' => true]),
            new Examples(example: 'error', summary: 'Error', value: ['ok' => false]),
        ]
    )
)]
```

**Examples - kompletni parametry:**

| Parametr | Typ | Popis |
|----------|-----|-------|
| `example` | `string` | Nazev prikladu (klic v mape) |
| `summary` | `string` | Kratky popis |
| `description` | `string` | Detailni popis (Markdown) |
| `value` | `mixed` | Hodnota prikladu |
| `externalValue` | `string` | URL na externi priklad (alternativa k `value`) |

### External Documentation

```php
use Sabservis\Api\Attribute\OpenApi\ExternalDocumentation;

#[ExternalDocumentation(url: 'https://docs.example.com/users', description: 'User API docs')]
class UserController implements Controller { }

// Nebo na metode
#[Get(path: '/users')]
#[ExternalDocumentation(url: 'https://docs.example.com/users/list')]
public function list(): array
```

### API Info, Contact, License

Metadata pro cele API (na hlavnim controlleru nebo application trida):

```php
use Sabservis\Api\Attribute\OpenApi\Info;
use Sabservis\Api\Attribute\OpenApi\Contact;
use Sabservis\Api\Attribute\OpenApi\License;

#[Info(
    title: 'My API',
    version: '2.0',
    description: 'REST API for my application',
    termsOfService: 'https://example.com/terms',
    contact: new Contact(name: 'API Support', email: 'support@example.com', url: 'https://example.com'),
    license: new License(name: 'MIT', url: 'https://opensource.org/licenses/MIT'),
)]
class ApiController implements Controller { }
```

### Parameter - allowEmptyValue

Pro query parametry, ktere mohou mit prazdnou hodnotu:

```php
#[QueryParameter(name: 'search', allowEmptyValue: true)]
// Povoli ?search= (prazdny retezec)
```

## Více viz

- [Cheatsheet](cheatsheet.md) - Kompletní příklad
- [Controllers](controllers.md)
- [Parameters](parameters.md)
