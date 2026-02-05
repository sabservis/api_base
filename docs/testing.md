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
        $response->assertJsonPath('name', 'John');
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
```

## Headers a autentizace

```php
// Přidání headeru
$client->withHeader('X-Custom', 'value');

// Bearer token
$client->withToken('your-jwt-token');

// Basic auth
$client->withBasicAuth('username', 'password');

// Chainování
$response = $this->client
    ->withToken($token)
    ->withHeader('X-Request-ID', 'test-123')
    ->get('/api/users');
```

## TestResponse assertions

### Status kódy

```php
$response->assertOk();              // 200
$response->assertCreated();         // 201
$response->assertNoContent();       // 204
$response->assertBadRequest();      // 400
$response->assertUnauthorized();    // 401
$response->assertForbidden();       // 403
$response->assertNotFound();        // 404
$response->assertUnprocessable();   // 422
$response->assertStatus(418);       // Konkrétní kód
```

### JSON assertions

```php
// Kontrola struktury
$response->assertJsonHasKey('id');
$response->assertJsonHasKeys(['id', 'name', 'email']);
$response->assertJsonMissingKey('password');

// Kontrola hodnot
$response->assertJsonPath('name', 'John');
$response->assertJsonPath('roles.0', 'admin');

// Kontrola počtu
$response->assertJsonCount(10);           // Root array
$response->assertJsonCount(2, 'roles');   // Nested

// Získání dat
$data = $response->json();
$name = $response->json('name');
$firstRole = $response->json('roles.0');
```

### Headers

```php
$response->assertHeader('Content-Type', 'application/json');
$response->assertHeaderContains('Content-Type', 'json');
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
    $response->assertJsonPath('fields.name.0', 'Name is required');
}
```

## Testování file upload

```php
public function testAvatarUpload(): void
{
    $response = $this->client
        ->withFile('avatar', '/path/to/test.jpg', 'image/jpeg')
        ->post('/api/users/1/avatar');

    $response->assertOk();
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
