<?php

namespace Shopware6Client\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase;
use Shopware6Client\Shopware6ApiClient;

class Shopware6ApiClientTest extends TestCase
{
    private $clientId = 'test_client_id';
    private $clientSecret = 'test_client_secret';
    private $baseUrl = 'https://test-shop.example.com';
    
    private $container = [];
    
    /**
     * Erstellt einen Mock-Client mit vordefinierten Antworten
     * 
     * @param array $responses Array von Response-Objekten
     * @return Shopware6ApiClient
     */
    private function createMockClient(array $responses): Shopware6ApiClient
    {
        $this->container = [];
        $history = Middleware::history($this->container);
        
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        
        $httpClient = new HttpClient([
            'handler' => $handlerStack,
            'base_uri' => $this->baseUrl
        ]);
        
        $client = new Shopware6ApiClient($this->baseUrl, $this->clientId, $this->clientSecret);
        
        // Injiziere den HTTP-Client über Reflection, da er private ist
        $reflectionProperty = new \ReflectionProperty(Shopware6ApiClient::class, 'httpClient');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($client, $httpClient);
        
        return $client;
    }
    
    /**
     * Testet die Authentifizierungsmethode
     */
    public function testAuthenticate()
    {
        $client = $this->createMockClient([
            new Response(200, [], json_encode([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 600
            ]))
        ]);
        
        $result = $client->authenticate();
        
        $this->assertTrue($result);
        $this->assertCount(1, $this->container);
        $request = $this->container[0]['request'];
        
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/oauth/token', $request->getUri()->getPath());
        
        $requestBody = json_decode($request->getBody(), true);
        $this->assertEquals($this->clientId, $requestBody['client_id']);
        $this->assertEquals($this->clientSecret, $requestBody['client_secret']);
        $this->assertEquals('client_credentials', $requestBody['grant_type']);
    }
    
    /**
     * Testet die Token-Aktualisierungsmethode
     */
    public function testRefreshAccessToken()
    {
        // Zuerst authentifizieren
        $client = $this->createMockClient([
            new Response(200, [], json_encode([
                'access_token' => 'initial_access_token',
                'refresh_token' => 'initial_refresh_token',
                'expires_in' => 600
            ])),
            // Dann Token aktualisieren
            new Response(200, [], json_encode([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 600
            ]))
        ]);
        
        $client->authenticate();
        $result = $client->refreshAccessToken();
        
        $this->assertTrue($result);
        $this->assertCount(2, $this->container);
        $refreshRequest = $this->container[1]['request'];
        
        $this->assertEquals('POST', $refreshRequest->getMethod());
        $this->assertEquals('/api/oauth/token', $refreshRequest->getUri()->getPath());
        
        $requestBody = json_decode($refreshRequest->getBody(), true);
        $this->assertEquals($this->clientId, $requestBody['client_id']);
        $this->assertEquals($this->clientSecret, $requestBody['client_secret']);
        $this->assertEquals('refresh_token', $requestBody['grant_type']);
        $this->assertEquals('initial_refresh_token', $requestBody['refresh_token']);
    }
    
    /**
     * Testet die GET-Methode
     */
    public function testGet()
    {
        $expectedResponse = [
            'data' => [
                ['id' => 'product-1', 'name' => 'Test Product 1'],
                ['id' => 'product-2', 'name' => 'Test Product 2']
            ],
            'total' => 2
        ];
        
        $client = $this->createMockClient([
            // Authentifizierung
            new Response(200, [], json_encode([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 600
            ])),
            // GET-Antwort
            new Response(200, [], json_encode($expectedResponse))
        ]);
        
        $client->authenticate();
        $result = $client->get('/api/product', ['limit' => 10]);
        
        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $this->container);
        $getRequest = $this->container[1]['request'];
        
        $this->assertEquals('GET', $getRequest->getMethod());
        $this->assertEquals('/api/product', $getRequest->getUri()->getPath());
        $this->assertEquals('limit=10', $getRequest->getUri()->getQuery());
        $this->assertEquals('Bearer test_access_token', $getRequest->getHeaderLine('Authorization'));
    }
    
    /**
     * Testet die POST-Methode
     */
    public function testPost()
    {
        $productData = [
            'name' => 'New Product',
            'productNumber' => 'NP-001',
            'stock' => 100
        ];
        
        $expectedResponse = [
            'data' => [
                'id' => 'new-product-id',
                'name' => 'New Product',
                'productNumber' => 'NP-001',
                'stock' => 100
            ]
        ];
        
        $client = $this->createMockClient([
            // Authentifizierung
            new Response(200, [], json_encode([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 600
            ])),
            // POST-Antwort
            new Response(200, [], json_encode($expectedResponse))
        ]);
        
        $client->authenticate();
        $result = $client->post('/api/product', $productData);
        
        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $this->container);
        $postRequest = $this->container[1]['request'];
        
        $this->assertEquals('POST', $postRequest->getMethod());
        $this->assertEquals('/api/product', $postRequest->getUri()->getPath());
        
        $requestBody = json_decode($postRequest->getBody(), true);
        $this->assertEquals($productData, $requestBody);
        $this->assertEquals('Bearer test_access_token', $postRequest->getHeaderLine('Authorization'));
    }
    
    /**
     * Testet die PATCH-Methode
     */
    public function testPatch()
    {
        $productId = 'product-to-update';
        $updateData = [
            'name' => 'Updated Product Name',
            'stock' => 150
        ];
        
        $expectedResponse = [
            'data' => [
                'id' => $productId,
                'name' => 'Updated Product Name',
                'stock' => 150
            ]
        ];
        
        $client = $this->createMockClient([
            // Authentifizierung
            new Response(200, [], json_encode([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 600
            ])),
            // PATCH-Antwort
            new Response(200, [], json_encode($expectedResponse))
        ]);
        
        $client->authenticate();
        $result = $client->patch('/api/product/' . $productId, $updateData);
        
        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $this->container);
        $patchRequest = $this->container[1]['request'];
        
        $this->assertEquals('PATCH', $patchRequest->getMethod());
        $this->assertEquals('/api/product/' . $productId, $patchRequest->getUri()->getPath());
        
        $requestBody = json_decode($patchRequest->getBody(), true);
        $this->assertEquals($updateData, $requestBody);
        $this->assertEquals('Bearer test_access_token', $patchRequest->getHeaderLine('Authorization'));
    }
    
    /**
     * Testet die DELETE-Methode
     */
    public function testDelete()
    {
        $productId = 'product-to-delete';
        $expectedResponse = [];
        
        $client = $this->createMockClient([
            // Authentifizierung
            new Response(200, [], json_encode([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 600
            ])),
            // DELETE-Antwort
            new Response(204, [], json_encode($expectedResponse))
        ]);
        
        $client->authenticate();
        $result = $client->delete('/api/product/' . $productId);
        
        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $this->container);
        $deleteRequest = $this->container[1]['request'];
        
        $this->assertEquals('DELETE', $deleteRequest->getMethod());
        $this->assertEquals('/api/product/' . $productId, $deleteRequest->getUri()->getPath());
        $this->assertEquals('Bearer test_access_token', $deleteRequest->getHeaderLine('Authorization'));
    }
    
    /**
     * Testet den automatischen Token-Refresh bei 401-Fehlern
     */
    public function testAutoTokenRefreshOn401()
    {
        $client = $this->createMockClient([
            // Initiale Authentifizierung
            new Response(200, [], json_encode([
                'access_token' => 'initial_access_token',
                'refresh_token' => 'initial_refresh_token',
                'expires_in' => 600
            ])),
            // 401 Unauthorized beim GET-Request
            new Response(401, [], json_encode([
                'errors' => [
                    [
                        'code' => 'FRAMEWORK__INVALID_OAUTH_ACCESS_TOKEN',
                        'status' => '401',
                        'title' => 'Unauthorized'
                    ]
                ]
            ])),
            // Token-Refresh
            new Response(200, [], json_encode([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 600
            ])),
            // Erneuter GET-Request, diesmal erfolgreich
            new Response(200, [], json_encode([
                'data' => [
                    ['id' => 'product-1', 'name' => 'Test Product 1']
                ],
                'total' => 1
            ]))
        ]);
        
        $client->authenticate();
        $result = $client->get('/api/product');
        
        $this->assertCount(4, $this->container);
        
        // Überprüfe, ob der zweite Request ein 401 zurückgab
        $secondRequest = $this->container[1]['request'];
        $this->assertEquals('GET', $secondRequest->getMethod());
        $this->assertEquals('Bearer initial_access_token', $secondRequest->getHeaderLine('Authorization'));
        
        // Überprüfe, ob der dritte Request den Token aktualisiert hat
        $thirdRequest = $this->container[2]['request'];
        $this->assertEquals('POST', $thirdRequest->getMethod());
        $this->assertEquals('/api/oauth/token', $thirdRequest->getUri()->getPath());
        
        // Überprüfe, ob der vierte Request den neuen Token verwendet hat
        $fourthRequest = $this->container[3]['request'];
        $this->assertEquals('GET', $fourthRequest->getMethod());
        $this->assertEquals('Bearer new_access_token', $fourthRequest->getHeaderLine('Authorization'));
        
        // Überprüfe, ob das Ergebnis korrekt ist
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(1, count($result['data']));
    }
    
    /**
     * Testet die Hilfsmethode getProducts
     */
    public function testGetProducts()
    {
        $expectedResponse = [
            'data' => [
                ['id' => 'product-1', 'name' => 'Test Product 1'],
                ['id' => 'product-2', 'name' => 'Test Product 2']
            ],
            'total' => 2
        ];
        
        $client = $this->createMockClient([
            // Authentifizierung
            new Response(200, [], json_encode([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 600
            ])),
            // GET-Antwort
            new Response(200, [], json_encode($expectedResponse))
        ]);
        
        $client->authenticate();
        $result = $client->getProducts(['limit' => 10]);
        
        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $this->container);
        $getRequest = $this->container[1]['request'];
        
        $this->assertEquals('GET', $getRequest->getMethod());
        $this->assertEquals('/api/product', $getRequest->getUri()->getPath());
    }
    
    /**
     * Testet das Verhalten bei einem Fehler während der Authentifizierung
     */
    public function testAuthenticationError()
    {
        $client = $this->createMockClient([
            new Response(401, [], json_encode([
                'errors' => [
                    [
                        'code' => 'FRAMEWORK__OAUTH_AUTHENTICATION_FAILED',
                        'status' => '401',
                        'title' => 'Authentication failed'
                    ]
                ]
            ]))
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Authentifizierung fehlgeschlagen');
        
        $client->authenticate();
    }
}
