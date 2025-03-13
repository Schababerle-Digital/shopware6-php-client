# Shopware 6 API Client

A robust PHP client for the Shopware 6 Admin API with automatic authentication and token management.

## Features

- Simple authentication via client credentials
- Automatic token management with auto-refresh
- Support for all HTTP methods (GET, POST, PATCH, DELETE)
- Helper methods for common API operations
- Comprehensive error handling
- Fully tested with PHPUnit

## Installation

```bash
composer require PatrickSchababerle/shopware6-api-client
```

## Quick Start

```php
use Shopware6Client\Shopware6ApiClient;

// Initialize client
$client = new Shopware6ApiClient(
    'https://your-shop.com',
    'YOUR_CLIENT_ID',
    'YOUR_CLIENT_SECRET'
);

// Authenticate
$client->authenticate();

// Fetch products
$products = $client->getProducts(['limit' => 10]);

// Create a new product
$newProduct = $client->createProduct([
    'name' => 'My Product',
    'productNumber' => 'MP-001',
    'stock' => 100,
    'taxId' => 'YOUR_TAX_ID',
    'price' => [
        [
            'currencyId' => 'YOUR_CURRENCY_ID',
            'gross' => 19.99,
            'net' => 16.80,
            'linked' => true
        ]
    ]
]);

// Update product
$client->updateProduct($newProduct['data']['id'], [
    'stock' => 150,
    'description' => 'This is a product description'
]);

// Delete product
$client->deleteProduct($newProduct['data']['id']);
```

## Available Methods

### Main Methods

- `authenticate()`: Performs authentication
- `refreshAccessToken()`: Manually refreshes the token
- `request(string $method, string $endpoint, array $data = [], array $query = [], array $headers = [])`: Performs an API request
- `get(string $endpoint, array $query = [])`: Performs a GET request
- `post(string $endpoint, array $data = [])`: Performs a POST request
- `patch(string $endpoint, array $data = [])`: Performs a PATCH request
- `delete(string $endpoint)`: Performs a DELETE request

### Helper Methods

- `getProducts(array $criteria = [])`: Fetches products
- `getOrder(string $orderId)`: Fetches an order
- `getOrders(array $criteria = [])`: Fetches orders
- `getCustomers(array $criteria = [])`: Fetches customers
- `createProduct(array $productData)`: Creates a product
- `updateProduct(string $productId, array $productData)`: Updates a product
- `deleteProduct(string $productId)`: Deletes a product

## Examples for Filter Criteria

```php
// Fetch products with filter
$activeProducts = $client->getProducts([
    'filter' => [
        [
            'type' => 'equals',
            'field' => 'active',
            'value' => true
        ]
    ],
    'sort' => [
        [
            'field' => 'name',
            'order' => 'ASC'
        ]
    ],
    'limit' => 10,
    'page' => 1
]);

// Filter orders by date
$recentOrders = $client->getOrders([
    'filter' => [
        [
            'type' => 'range',
            'field' => 'orderDateTime',
            'parameters' => [
                'gte' => '2023-01-01T00:00:00+00:00'
            ]
        ]
    ],
    'sort' => [
        [
            'field' => 'orderDateTime',
            'order' => 'DESC'
        ]
    ]
]);
```

## Error Handling

The client throws exceptions when an error occurs. It is recommended to wrap your API requests in try-catch blocks:

```php
try {
    $client->authenticate();
    $products = $client->getProducts(['limit' => 10]);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Automatic Token Refresh

The client automatically handles token management:

1. On first use, `authenticate()` is automatically called if no token is present
2. If a token expires (or will expire in less than 30 seconds), it is automatically refreshed
3. If a 401 Unauthorized error occurs, the client attempts to refresh the token and retry the request

## Tests

The package contains comprehensive tests:

```bash
# Run unit tests
composer test

# With coverage report
composer test-coverage

# Run integration tests (requires .env file)
vendor/bin/phpunit --group integration
```

### Setting Up Integration Tests

For integration tests, copy the `.env.example` file to `.env` and fill in your Shopware API credentials.

## License

MIT

## Contributing

Contributions are welcome! Please ensure all tests pass before submitting a pull request.