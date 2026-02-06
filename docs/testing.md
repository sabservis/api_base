# Testing

## ApiTestClient

Knihovna poskytuje `ApiTestClient` pro testování API:

```php
use PHPUnit\Framework\TestCase;
use Sabservis\Api\Testing\ApiTestClient;

class UserControllerTest extends TestCase
{
    private ApiTestClient $client;

    protected function setUp(): void
    {
        $container = Bootstrap::boot()->createContainer();
        $app = $container->getByType(ApiApplication::class);
        $this->client = new ApiTestClient($app);
    }

    public function testGetUser(): void
    {
        $response = $this->client->get('/api/users/1');

        $response->assertOk();
        $response->assertJsonHasKey('id');

        $data = $response->json();
        self::assertIsArray($data);
        self::assertSame('John', $data['name'] ?? null);
    }

    public function testCreateUser(): void
    {
        $response = $this->client->postJson('/api/users', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonHasKey('id');
    }
}
```

## HTTP metody

```php
$client->get('/api/users');
$client->post('/api/users', $body);
$client->postJson('/api/users', ['name' => 'John']);
$client->put('/api/users/1', $body);
$client->putJson('/api/users/1', ['name' => 'John']);
$client->patch('/api/users/1', $body);
$client->patchJson('/api/users/1', ['name' => 'John']);
$client->delete('/api/users/1');
$client->request('POST', '/api/users', '{}', ['content-type' => 'application/json']);
```

## Headers a autentizace

```php
// Výchozí headery pro všechny requesty
$client->withHeaders([
    'x-custom' => 'value',
    'x-request-id' => 'test-123',
]);

// Bearer token (Authorization header)
$client->withToken('your-jwt-token');

// Chainování
$response = $this->client
    ->withToken($token)
    ->withHeaders(['x-request-id' => 'test-123'])
    ->get('/api/users', ['accept' => 'application/json']);
```

## TestResponse assertions

### Status kódy

```php
$response->assertOk();              // 200
$response->assertCreated();         // 201
$response->assertNoContent();       // 204
$response->assertUnauthorized();    // 401
$response->assertForbidden();       // 403
$response->assertNotFound();        // 404
$response->assertUnprocessable();   // 422
$response->assertStatus(400);       // Libovolný konkrétní kód
```

### JSON assertions

```php
// Přesná shoda JSON payloadu
$response->assertJson(['id' => 1, 'name' => 'John']);

// Kontrola podmnožiny klíčů/hodnot
$response->assertJsonContains(['id' => 1]);

// Kontrola existence klíče
$response->assertJsonHasKey('id');

// Získání dat
$data = $response->json();
self::assertIsArray($data);
self::assertSame('John', $data['name'] ?? null);
```

### Headers

```php
$response->assertHeader('Content-Type', 'application/json');
$response->assertHeaderExists('X-Request-ID');

$contentType = $response->getHeaderLine('Content-Type');
self::assertStringContainsString('application/json', $contentType);
```

## Testování validace

```php
public function testValidationErrors(): void
{
    $response = $this->client->postJson('/api/users', [
        'name' => '',  // Required
        'email' => 'invalid',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonHasKey('fields');

    $payload = $response->json();
    self::assertIsArray($payload);
    self::assertSame('Name is required', $payload['fields']['name'][0] ?? null);
}
```

## Testování middleware

```php
public function testRateLimiting(): void
{
    // Vyčerpej limit
    for ($i = 0; $i < 100; $i++) {
        $this->client->get('/api/users');
    }

    // Další request by měl selhat
    $response = $this->client->get('/api/users');
    $response->assertStatus(429);
}
```

## Tips

1. **Izolované testy** - Každý test by měl být nezávislý
2. **Testovací databáze** - Používej in-memory nebo transakce
3. **Factory pattern** - Pro vytváření testovacích dat
4. **Assertions first** - Piš assertions před implementací

## Více viz

- [Controllers](controllers.md)
- [Request & Response](request-response.md)
