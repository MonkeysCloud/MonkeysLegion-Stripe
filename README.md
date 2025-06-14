# MonkeysLegion Stripe Integration

[![PHP Version](https://img.shields.io/badge/php-8.4%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

First-class Stripe integration package for the MonkeysLegion PHP framework, providing PSR-compliant HTTP clients and service container integration.

## Documentation
All usage, configuration, and API references can be found in the [official Monkeys Legion Stripe package documentation](https://monkeyslegion.com/docs/packages/stripe).


## 🚀 Quick Start

```bash
# Install the package
composer require monkeyscloud/monkeyslegion-stripe

# Publish the configuration file
php vendor/monkeyscloud/monkeyslegion-stripe/publish.php

# Set up your Stripe keys interactively
php vendor/bin/key-helper set

# Validate your configuration
php vendor/bin/key-helper validate

# Test webhook signature verification
php vendor/bin/key-helper webhook:test
```

## Features

- **PSR-Compliant**: Built with PSR standards for maximum compatibility
- **Service Container Integration**: Automatic dependency injection
- **Configuration Management**: Environment-based configuration with merging support
- **HTTP Client Abstraction**: PSR-18 HTTP client implementation
- **Key Management**: Built-in tools for managing Stripe API keys and webhook secrets
- **Webhook Testing**: Comprehensive webhook signature validation testing

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

The package includes a comprehensive key management utility for generating, validating, and managing Stripe API keys and webhook secrets. All operations are performed through the command line interface.

### Command Reference

```bash
php vendor/bin/key-helper [COMMAND] [OPTIONS]

Available Commands:
  generate [KEY_TYPE]          # Generate and save a new key
  set [KEY_NAME] [VALUE]       # Set a specific key or enter interactive mode
  rotate [KEY_TYPE]            # Rotate (replace) an existing key
  validate [KEY_TYPE]          # Validate keys (all if no type specified)
  show [KEY_TYPE]              # Display current key value
  list                         # List all Stripe/webhook keys
  webhook:test                 # Test webhook secret validation

Key Types:
  secret                       # STRIPE_SECRET_KEY
  publishable                  # STRIPE_PUBLISHABLE_KEY  
  webhook                      # STRIPE_WEBHOOK_SECRET
```

### Generate New Keys

#### Generate Default App Key
```bash
# Generates STRIPE_APP_KEY (64-character hex string)
php vendor/bin/key-helper generate
```

#### Generate Specific Key Types
```bash
# Generate webhook secret placeholder
php vendor/bin/key-helper generate webhook

# Generate secret key placeholder  
php vendor/bin/key-helper generate secret

# Generate publishable key placeholder
php vendor/bin/key-helper generate publishable
```

### Set Keys

#### Set Individual Keys
```bash
# Set a specific key value
php vendor/bin/key-helper set STRIPE_SECRET_KEY sk_test_your_key_here

# Set webhook secret
php vendor/bin/key-helper set STRIPE_WEBHOOK_SECRET whsec_your_secret_here
```

#### Interactive Setup Mode
```bash
# Enter interactive mode to set all Stripe keys
php vendor/bin/key-helper set

# Interactive prompts for:
# - STRIPE_PUBLISHABLE_KEY
# - STRIPE_SECRET_KEY  
# - STRIPE_WEBHOOK_SECRET
# - STRIPE_API_VERSION
# Press Enter to skip any key
```

### Validate Keys

#### Validate All Keys
```bash
# Validates all Stripe keys and shows comprehensive status
php vendor/bin/key-helper validate

# Example output:
# ✅ STRIPE_SECRET_KEY (secret): VALID
# ✅ STRIPE_PUBLISHABLE_KEY (publishable): VALID  
# ⚠️  STRIPE_WEBHOOK_SECRET (webhook): NOT SET
# ✅ STRIPE_API_VERSION: VALID
```

## Webhook Handling

This package provides a robust webhook handling system that securely processes Stripe events while preventing duplicate processing.

### Setting Up Webhooks

1. **Configure Webhook Secret**
   ```bash
   # Set your webhook secret from Stripe Dashboard
   php vendor/bin/key-helper set STRIPE_WEBHOOK_SECRET whsec_your_secret_here
   
   # Verify it's configured correctly
   php vendor/bin/key-helper webhook:test
   ```

2. **Create a Webhook Endpoint**
   ```php
   <?php
   // webhook.php
   
   require_once 'vendor/autoload.php';
   
   use MonkeysLegion\Stripe\Service\ServiceContainer;
   
   // Get the raw POST data
   $payload = file_get_contents('php://input');
   $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
   
   try {
       $container = ServiceContainer::getInstance();
       $webhookController = $container->get('webhook_controller');
       
       // Process the webhook
       $result = $webhookController->handle($payload, $sigHeader, function($event) {
           // Handle different event types
           switch ($event['type']) {
               case 'payment_intent.succeeded':
                   // Handle successful payment
                   $paymentIntent = $event['data']['object'];
                   // Process order, send confirmation, etc.
                   break;
                   
               case 'customer.subscription.created':
                   // Handle new subscription
                   $subscription = $event['data']['object'];
                   // Update user subscription status
                   break;
                   
               // Handle other event types...
           }
           
           // Return success response
           return ['status' => 'success', 'event' => $event['type']];
       });
       
       // Output success
       http_response_code(200);
       echo json_encode($result);
       
   } catch (\Stripe\Exception\SignatureVerificationException $e) {
       // Invalid signature
       http_response_code(400);
       echo json_encode(['error' => 'Invalid signature']);
       
   } catch (\Exception $e) {
       // Other errors
       http_response_code(500);
       echo json_encode(['error' => $e->getMessage()]);
   }
   ```

### WebhookController Features

The `WebhookController` class provides a clean interface for handling Stripe webhooks:

- **Secure Signature Verification**: Uses Stripe's cryptographic signature verification
- **Idempotency Management**: Prevents duplicate processing of the same event
- **Error Handling**: Comprehensive error handling for various Stripe exceptions
- **Flexible Event Processing**: Pass your own callback to handle specific event types

```php
// Handle a webhook event with custom callback
$webhookController->handle($payload, $sigHeader, function($eventData) {
    // Your custom event handling logic
    return ['status' => 'processed'];
});

// Check if an event has already been processed
$isProcessed = $webhookController->isEventProcessed('evt_123456');

// Clean up expired events
$webhookController->cleanupExpiredEvents();
```

## Idempotency Management

Stripe webhooks might be sent multiple times for the same event (due to retries or network issues). The package includes an idempotency system that ensures each event is processed exactly once.

### MemoryIdempotencyStore

The `MemoryIdempotencyStore` is a database-backed system that:

- **Tracks Processed Events**: Records which webhook events have been processed
- **Prevents Duplicates**: Automatically rejects duplicate event IDs
- **Time-Based Expiration**: Supports automatic cleanup of old events
- **Data Storage**: Can store additional data associated with events

This component is managed automatically by the WebhookController and WebhookMiddleware, requiring no direct interaction in most use cases.

### Database Setup

The idempotency store requires a database table with the following structure:

```sql
CREATE TABLE idempotency_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL UNIQUE,
    processed_at DATETIME NOT NULL,
    expiry DATETIME NULL,
    data JSON NULL,
    UNIQUE INDEX idx_event_id (event_id)
);
```

### Configuring Expiration

By default, processed events are stored for 48 hours. You can customize this:

```php
// In your app configuration
return [
    'webhook_default_ttl' => 86400, // 24 hours (in seconds)
    // ...other config
];
```
