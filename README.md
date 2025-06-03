# MonkeysLegion Stripe Integration

[![PHP Version](https://img.shields.io/badge/php-8.4%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

First-class Stripe integration package for the MonkeysLegion PHP framework, providing PSR-compliant HTTP clients and service container integration.

## Features

- **PSR-Compliant**: Built with PSR standards for maximum compatibility
- **Service Container Integration**: Automatic dependency injection
- **Configuration Management**: Environment-based configuration with merging support
- **HTTP Client Abstraction**: PSR-18 HTTP client implementation
- **Key Management**: Built-in tools for managing Stripe API keys and webhook secrets

## Requirements

- PHP 8.4 or higher
- MonkeysLegion Core ^1.0
- Stripe PHP SDK ^17.3

## PSR Standards Compliance

This package implements the following PSR standards:

- **PSR-11**: Container Interface (`psr/container: ^2.0`)
- **PSR-18**: HTTP Client Interface (`psr/http-client: ^1.0`)
- **PSR-17**: HTTP Factories Interface (`psr/http-factory: ^1.1`)
- **PSR-3**: Logger Interface (`psr/log: ^3.0`)

## Installation

Install the package via Composer:

```bash
composer require monkeyscloud/monkeyslegion-stripe
```

## Configuration

### Publish Configuration File

Publish the configuration file to your project:

```bash
php vendor/monkeyscloud/monkeyslegion-stripe/publish.php
```

This copies the Stripe configuration file to your project's `config/stripe.php` directory.

### Environment Variables

Configure your Stripe settings using environment variables:

```env
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_API_VERSION=2025-04-30
STRIPE_CURRENCY=usd
```

### Configuration Structure

The configuration file supports the following options:

```php
return [
    'secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    'webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
    'api_version'     => getenv('STRIPE_API_VERSION') ?: '2025-04-30',
    'currency'        => getenv('STRIPE_CURRENCY') ?: 'usd',
];
```

## Service Registration

### Automatic Registration

The package automatically registers the following services in the ServiceContainer:

- `stripe_http_client` - Dedicated HTTP client configured for Stripe API requests
- `StripeClient` - Main Stripe API client with injected HTTP client

(Register Stripe Service Provider In Your app.php First)

### Manual Registration

If you need to manually register the service provider:

```php
use MonkeysLegion\Stripe\Provider\StripeServiceProvider;
use MonkeysLegion\Stripe\Service\ServiceContainer;

$container = ServiceContainer::getInstance();
$provider = new StripeServiceProvider($container);
$provider->register();
```

## Usage

### Basic HTTP Client Usage

```php
use MonkeysLegion\Stripe\Service\ServiceContainer;
use Psr\Http\Client\ClientExceptionInterface;

try {
    $container = ServiceContainer::getInstance();
    $stripeClient = $container->get('StripeClient');
    
    // Use the client to send PSR-7 requests
    $response = $stripeClient->sendRequest($request);
    // Process $response
} catch (ClientExceptionInterface $e) {
    // Handle HTTP client errors
    echo 'Request failed: ' . $e->getMessage();
}
```

### Service Container Integration

```php
use MonkeysLegion\Stripe\Service\ServiceContainer;

$container = ServiceContainer::getInstance();

// Get the Stripe HTTP client
$httpClient = $container->get('stripe_http_client');

// Get the main Stripe client
$stripeClient = $container->get('StripeClient');
```

## Key Management

The package includes a comprehensive key management utility for generating and managing Stripe API keys and webhook secrets.

### Generate New Keys

```bash
# Generate webhook secret
php vendor/monkeyscloud/monkeyslegion-stripe/bin/key-helper.php generate webhook

# Generate secret key placeholder
php vendor/monkeyscloud/monkeyslegion-stripe/bin/key-helper.php generate secret

# Generate publishable key placeholder
php vendor/monkeyscloud/monkeyslegion-stripe/bin/key-helper.php generate publishable
```

### Rotate Existing Keys

```bash
php vendor/monkeyscloud/monkeyslegion-stripe/bin/key-helper.php rotate webhook
```

### Validate Keys

```bash
php vendor/monkeyscloud/monkeyslegion-stripe/bin/key-helper.php validate webhook
```

### Show Current Keys

```bash
php vendor/monkeyscloud/monkeyslegion-stripe/bin/key-helper.php show webhook
```

## Architecture

### Service Container

The package uses a singleton ServiceContainer that manages service instances and factories:

- **Lazy Loading**: Services are instantiated only when first requested
- **Singleton Pattern**: Ensures single instances of services
- **Factory Support**: Services can be registered with factory callables

### HTTP Client

The HttpClient implements PSR-18 ClientInterface:

- **Guzzle Integration**: Uses Guzzle HTTP client under the hood
- **PSR-7 Compatibility**: Accepts and returns PSR-7 messages
- **Exception Handling**: Converts Guzzle exceptions to PSR-18 exceptions

### Configuration Merging

The configuration system supports merging vendor defaults with application overrides:

- **Vendor Defaults**: Package provides sensible defaults
- **Application Overrides**: Your project can override any configuration
- **Deep Merging**: Nested arrays are merged recursively
