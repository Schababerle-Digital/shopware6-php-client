<?php

namespace Shopware6Client\Tests;

use PHPUnit\Framework\TestCase;
use Shopware6Client\Shopware6ApiClient;

/**
 * Diese Tests führen echte API-Requests durch und sind daher standardmäßig deaktiviert.
 *
 * Um diese Tests auszuführen, erstelle eine .env-Datei mit den folgenden Werten:
 * SHOPWARE_URL=https://dein-shop.de
 * SHOPWARE_CLIENT_ID=dein-client-id
 * SHOPWARE_CLIENT_SECRET=dein-client-secret
 *
 * Führe die Tests dann mit: vendor/bin/phpunit --group integration
 */
class IntegrationTest extends TestCase
{
    private $client;
    private $baseUrl;
    private $clientId;
    private $clientSecret;

    protected function setUp(): void
    {
        // Lade .env Datei wenn vorhanden
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }

        // Skip-Tests falls keine Umgebungsvariablen vorhanden sind
        if (!getenv('SHOPWARE_URL') || !getenv('SHOPWARE_CLIENT_ID') || !getenv('SHOPWARE_CLIENT_SECRET')) {
            $this->markTestSkipped('Integration tests require SHOPWARE_URL, SHOPWARE_CLIENT_ID, and SHOPWARE_CLIENT_SECRET environment variables.');
        }

        $this->baseUrl = getenv('SHOPWARE_URL');
        $this->clientId = getenv('SHOPWARE_CLIENT_ID');
        $this->clientSecret = getenv('SHOPWARE_CLIENT_SECRET');

        $this->client = new Shopware6ApiClient(
            $this->baseUrl,
            $this->clientId,
            $this->clientSecret
        );

        // Authentifizieren
        $this->client->authenticate();
    }

    /**
     * @group integration
     */
    public function testAuthentication()
    {
        // Authentifizierung erfolgt bereits in setUp()
        // Wenn keine Ausnahme geworfen wurde, war die Authentifizierung erfolgreich
        $this->assertTrue(true);
    }

    /**
     * @group integration
     */
    public function testGetProducts()
    {
        $products = $this->client->getProducts(['limit' => 5]);

        $this->assertArrayHasKey('data', $products);
        $this->assertLessThanOrEqual(5, count($products['data']));
    }

    /**
     * @group integration
     */
    public function testGetOrders()
    {
        $orders = $this->client->getOrders(['limit' => 5]);

        $this->assertArrayHasKey('data', $orders);
        $this->assertLessThanOrEqual(5, count($orders['data']));
    }

    /**
     * @group integration
     */
    public function testGetCustomers()
    {
        $customers = $this->client->getCustomers(['limit' => 5]);

        $this->assertArrayHasKey('data', $customers);
        $this->assertLessThanOrEqual(5, count($customers['data']));
    }

    /**
     * @group integration
     */
    public function testCreateUpdateDeleteProduct()
    {
        // Produkt erstellen
        $productNumber = 'TEST-' . uniqid();
        $productData = [
            'name' => 'Test-Produkt ' . $productNumber,
            'productNumber' => $productNumber,
            'stock' => 100,
            'taxId' => null, // Muss mit einer gültigen SteuerID ersetzt werden
            'price' => [
                [
                    'currencyId' => null, // Muss mit einer gültigen WährungsID ersetzt werden
                    'gross' => 19.99,
                    'net' => 16.80,
                    'linked' => true
                ]
            ],
            'active' => true
        ];

        // Zuerst Tax-ID und Currency-ID holen
        $taxes = $this->client->get('/api/tax', ['limit' => 1]);
        if (isset($taxes['data'][0]['id'])) {
            $productData['taxId'] = $taxes['data'][0]['id'];
        }

        $currencies = $this->client->get('/api/currency', ['limit' => 1]);
        if (isset($currencies['data'][0]['id'])) {
            $productData['price'][0]['currencyId'] = $currencies['data'][0]['id'];
        }

        if (!$productData['taxId'] || !$productData['price'][0]['currencyId']) {
            $this->markTestSkipped('Cannot create product without valid tax and currency IDs.');
        }

        // Jetzt Produkt erstellen
        $createdProduct = $this->client->createProduct($productData);

        $this->assertArrayHasKey('data', $createdProduct);
        $this->assertNotEmpty($createdProduct['data']['id']);
        $productId = $createdProduct['data']['id'];

        // Produkt aktualisieren
        $updateData = [
            'stock' => 200,
            'description' => 'Dies ist eine Beschreibung für das Testprodukt.'
        ];

        $updatedProduct = $this->client->updateProduct($productId, $updateData);

        $this->assertArrayHasKey('data', $updatedProduct);
        $this->assertEquals(200, $updatedProduct['data']['stock']);
        $this->assertEquals('Dies ist eine Beschreibung für das Testprodukt.', $updatedProduct['data']['description']);

        // Produkt löschen
        $this->client->deleteProduct($productId);

        // Versuchen, das gelöschte Produkt erneut abzurufen (sollte einen Fehler geben)
        try {
            $this->client->get('/api/product/' . $productId);
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('API-Request fehlgeschlagen', $e->getMessage());
        }
    }
}