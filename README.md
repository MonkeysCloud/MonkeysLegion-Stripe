# MonkeysLegion Stripe Integration

First-class Stripe Provider integration package for the MonkeysLegion PHP framework.

## Service Provider

The package automatically registers the following services:

- `StripeClient` - Main Stripe API client
- `HttpClient` - PSR-7 HTTP client for Stripe requests
- `ServiceContainer` - Service container for dependency injection

PSR-11: psr/container: ^2.0 - Container Interface
PSR-18: psr/http-client: ^1.0 - HTTP Client Interface
PSR-17: psr/http-factory: ^1.1 - HTTP Factories Interface
PSR-3: psr/log: ^3.0 - Logger Interface

## Requirements

- PHP 8.4 or higher
- MonkeysLegion Core ^1.0
- Stripe PHP SDK ^17.3

## Installation

Install the package via Composer:

```bash
composer require monkeyscloud/monkeyslegion-stripe
```

### Publish Configuration

Publish the configuration file to your project:

```bash
php vendor/monkeyscloud/monkeyslegion-stripe/publish.php
```

This will copy the Stripe configuration file to your project's `config/` directory.

### Manual Registration

If you need to manually register the service provider:

```php
use MonkeysLegion\Stripe\Provider\StripeServiceProvider;
use MonkeysLegion\Stripe\Service\ServiceContainer;

'stripe' => (function () {
        $c = ServiceContainer::getInstance();
        return (new StripeServiceProvider($c))->register();
    })(),
```

## Key Generation Helper

Use the included key helper to generate webhook endpoint secrets:

```bash
php vendor/monkeyscloud/monkeyslegion-stripe/bin/key-helper.php
```