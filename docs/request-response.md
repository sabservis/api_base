# Request & Response

## ApiRequest

Immutable objekt reprezentující HTTP request.

### Základní metody

```php
public function action(ApiRequest $request): ApiResponse
{
    // HTTP metoda
    $method = $request->getMethod();  // GET, POST, ...

    // URL
    $path = $request->getPath();      // /api/users/123
    $uri = $request->getRawUri();     // Plná URI s query string

    // Headers (case-insensitive)
    $contentType = $request->getHeader('Content-Type');
    $hasAuth = $request->hasHeader('Authorization');

    // Query parametry
    $limit = $request->getQueryParam('limit', 20);
    $allQuery = $request->getQueryParams();

    // Cookies
    $session = $request->getCookie('session_id');

    // HTTPS detekce (podporuje X-Forwarded-Proto)
    $isSecure = $request->isSecured();
}
```

### Request body

```php
// Surový obsah
$raw = $request->getContents();

// JSON jako array
$data = $request->getJsonBody();

// Deserializovaný DTO (nastavený dispatcherem)
if ($request->hasEntity()) {
    $dto = $request->getEntity();
}

// Type-safe přístup k entitě
$user = $request->getTypedEntity(CreateUserDto::class);
```

### Attributes

```php
// Nastavení attribute
$request = $request->withAttribute('user', $user);

// Čtení attribute
$user = $request->getAttribute('user');
$user = $request->getAttribute('user', $default);
```

### File uploads

```php
// Jeden soubor
if ($request->hasUploadedFile('avatar')) {
    $file = $request->getUploadedFile('avatar');
    $file->moveTo('/uploads/' . $file->getName());
}

// Více souborů
$files = $request->getUploadedFiles('documents');
foreach ($files as $file) {
    if ($file->isOk()) {
        $file->moveTo('/uploads/' . $file->getName());
    }
}
```

## ApiResponse

Immutable objekt reprezentující HTTP response.

### Static factories

```php
// 200 OK s objektem
return ApiResponse::ok($userDto);

// 201 Created
return ApiResponse::created($userDto);

// 204 No Content
return ApiResponse::noContent();

// Seznam bez meta
return ApiResponse::list($items);

// Seznam s pagination
return ApiResponse::list($items, total: 100, limit: 20, offset: 0);
```

### Ruční sestavení

```php
$response = new ApiResponse();

// Status
$response = $response->withStatus(201);
$response = $response->withStatus(400, 'Custom Reason');

// Headers
$response = $response->withHeader('X-Custom', 'value');

// Body
$response = $response->writeBody('raw content');
$response = $response->writeJsonBody(['key' => 'value']);

// Objekt pro serializaci
$response = $response->withObject($dto);
```

### FileResponse

Pro stahování souborů:

```php
use Sabservis\Api\Http\FileResponse;

// Ze souboru
return FileResponse::fromPath('/path/to/file.pdf');

// S custom názvem
return FileResponse::fromPath('/path/to/file.pdf', 'report.pdf');

// Inline zobrazení (ne stažení)
return FileResponse::fromPath('/path/to/image.png')->inline();

// Z obsahu v paměti
return FileResponse::fromContent($pdfData, 'report.pdf', 'application/pdf');
```

## Více viz

- [Parameters](parameters.md) - Parametry requestu
- [Controllers](controllers.md) - Definice endpointů
