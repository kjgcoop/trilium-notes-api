# trilium-notes-api

Simple API to talk to a Trilium Notes instance. This is [php-trilium-next-api](https://github.com/kjgcoop/php-trilium-next-api/) with a facelift.

## Usage

No config file needed - construct the client directly with your endpoint and API key:

```php
use Kjgcoop\TriliumApi\TriliumApi;

$api = new TriliumApi(
    'https://your-trilium-domain/etapi/',
    'your-etapi-key'
);
```

The endpoint must use `https://` - the API key is sent as a plain `Authorization` header, so the constructor rejects an `http://` endpoint.

### Logging

Pass a PSR-3 `LoggerInterface` as the third constructor argument to capture request failures. Without one, failures are only raised as exceptions.

```php
$api = new TriliumApi($endpoint, $apiKey, $logger);
```

### Custom HTTP client

A fourth, optional argument accepts a Guzzle `ClientInterface`, useful for tests or custom HTTP configuration (proxies, custom timeouts, etc.). If omitted, a default client with a 10-second timeout is used.

```php
$api = new TriliumApi($endpoint, $apiKey, $logger, $httpClient);
```
