<?php

namespace Shopware6Client;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

class Shopware6ApiClient
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $expiresAt = null;
    private HttpClient $httpClient;

    /**
     * Shopware6ApiClient constructor.
     *
     * @param string $baseUrl URL zur Shopware-Installation (z.B. https://shop.example.com)
     * @param string $clientId Client-ID der API-Zugangsdaten
     * @param string $clientSecret Client-Secret der API-Zugangsdaten
     */
    public function __construct(string $baseUrl, string $clientId, string $clientSecret)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->httpClient = new HttpClient(['base_uri' => $this->baseUrl]);
    }

    /**
     * Führt die Authentifizierung für die API durch und erhält Access- und Refresh-Token.
     *
     * @return bool True bei erfolgreicher Authentifizierung, ansonsten false
     * @throws \Exception Bei Fehlern während der Authentifizierung
     */
    public function authenticate(): bool
    {
        try {
            $response = $this->httpClient->post('/api/oauth/token', [
                'json' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $this->accessToken = $data['access_token'];
            $this->refreshToken = $data['refresh_token'];
            $this->expiresAt = time() + $data['expires_in'];

            return true;
        } catch (GuzzleException $e) {
            throw new \Exception('Authentifizierung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Aktualisiert den Access-Token mittels Refresh-Token.
     *
     * @return bool True bei erfolgreicher Token-Aktualisierung, ansonsten false
     * @throws \Exception Bei Fehlern während der Token-Aktualisierung
     */
    public function refreshAccessToken(): bool
    {
        if (!$this->refreshToken) {
            throw new \Exception('Refresh-Token ist nicht vorhanden. Bitte zuerst authenticate() aufrufen.');
        }

        try {
            $response = $this->httpClient->post('/api/oauth/token', [
                'json' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $this->accessToken = $data['access_token'];
            $this->refreshToken = $data['refresh_token'];
            $this->expiresAt = time() + $data['expires_in'];

            return true;
        } catch (GuzzleException $e) {
            throw new \Exception('Token-Aktualisierung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Prüft, ob der aktuelle Token gültig ist, und aktualisiert ihn bei Bedarf.
     *
     * @return bool True, wenn der Token gültig ist oder erfolgreich aktualisiert wurde
     * @throws \Exception Bei Fehlern während der Token-Überprüfung oder -Aktualisierung
     */
    private function ensureValidToken(): bool
    {
        if (!$this->accessToken) {
            return $this->authenticate();
        }

        // Aktualisiere Token, wenn er in weniger als 30 Sekunden abläuft
        if ($this->expiresAt && time() > ($this->expiresAt - 30)) {
            return $this->refreshAccessToken();
        }

        return true;
    }

    /**
     * Führt einen API-Request durch.
     *
     * @param string $method HTTP-Methode (GET, POST, PATCH, DELETE)
     * @param string $endpoint API-Endpunkt (z.B. '/api/product')
     * @param array $data Request-Daten (für POST, PATCH)
     * @param array $query Query-Parameter (für GET)
     * @param array $headers Zusätzliche Header
     * @return array Antwortdaten als Array
     * @throws \Exception Bei Fehlern während des API-Requests
     */
    public function request(string $method, string $endpoint, array $data = [], array $query = [], array $headers = []): array
    {
        $this->ensureValidToken();

        $endpoint = ltrim($endpoint, '/');
        $options = [
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ], $headers)
        ];

        if (!empty($data)) {
            $options['json'] = $data;
        }

        if (!empty($query)) {
            $options['query'] = $query;
        }

        try {
            $response = $this->httpClient->request($method, $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            
            // Bei 401 Unauthorized, versuche Token zu aktualisieren und erneut zu senden
            if ($statusCode === 401) {
                $this->refreshAccessToken();
                // Aktualisiere Authorization-Header
                $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
                
                try {
                    $response = $this->httpClient->request($method, $endpoint, $options);
                    return json_decode($response->getBody()->getContents(), true);
                } catch (GuzzleException $retryException) {
                    throw new \Exception('API-Request nach Token-Aktualisierung fehlgeschlagen: ' . $retryException->getMessage());
                }
            }
            
            throw new \Exception('API-Request fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Führt einen GET-Request durch.
     *
     * @param string $endpoint API-Endpunkt
     * @param array $query Query-Parameter
     * @return array Antwortdaten
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, [], $query);
    }

    /**
     * Führt einen POST-Request durch.
     *
     * @param string $endpoint API-Endpunkt
     * @param array $data Request-Daten
     * @return array Antwortdaten
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Führt einen PATCH-Request durch.
     *
     * @param string $endpoint API-Endpunkt
     * @param array $data Request-Daten
     * @return array Antwortdaten
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * Führt einen DELETE-Request durch.
     *
     * @param string $endpoint API-Endpunkt
     * @return array Antwortdaten
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Hilfsmethode zum Abrufen von Produkten.
     *
     * @param array $criteria Filterkriterien
     * @return array Liste der Produkte
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function getProducts(array $criteria = []): array
    {
        return $this->get('/api/product', ['criteria' => $criteria]);
    }

    /**
     * Hilfsmethode zum Abrufen einer Bestellung.
     *
     * @param string $orderId ID der Bestellung
     * @return array Bestelldaten
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function getOrder(string $orderId): array
    {
        return $this->get('/api/order/' . $orderId);
    }

    /**
     * Hilfsmethode zum Abrufen von Bestellungen.
     *
     * @param array $criteria Filterkriterien
     * @return array Liste der Bestellungen
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function getOrders(array $criteria = []): array
    {
        return $this->get('/api/order', ['criteria' => $criteria]);
    }

    /**
     * Hilfsmethode zum Abrufen von Kunden.
     *
     * @param array $criteria Filterkriterien
     * @return array Liste der Kunden
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function getCustomers(array $criteria = []): array
    {
        return $this->get('/api/customer', ['criteria' => $criteria]);
    }

    /**
     * Hilfsmethode zum Erstellen eines Produkts.
     *
     * @param array $productData Produktdaten
     * @return array Erstelltes Produkt
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function createProduct(array $productData): array
    {
        return $this->post('/api/product', $productData);
    }

    /**
     * Hilfsmethode zum Aktualisieren eines Produkts.
     *
     * @param string $productId Produkt-ID
     * @param array $productData Zu aktualisierende Produktdaten
     * @return array Aktualisiertes Produkt
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function updateProduct(string $productId, array $productData): array
    {
        return $this->patch('/api/product/' . $productId, $productData);
    }

    /**
     * Hilfsmethode zum Löschen eines Produkts.
     *
     * @param string $productId Produkt-ID
     * @return array Antwortdaten
     * @throws \Exception Bei Fehlern während des Requests
     */
    public function deleteProduct(string $productId): array
    {
        return $this->delete('/api/product/' . $productId);
    }
}