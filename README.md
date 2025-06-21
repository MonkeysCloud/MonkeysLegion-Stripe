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
- **Environment Awareness**: Supports `.env.<stage>` files for `dev`, `prod`, and `test` environments

## Requirements

- PHP 8.4 or higher
- MonkeysLegion Core ^1.0
- Stripe PHP SDK ^17.3

## Environment Awareness

The package supports environment-specific configurations using `.env.<stage>` files. By default, the `dev` environment is used. You can specify the environment using the `--stage` flag.

### Example

```bash
# Use the dev environment (default)
php vendor/bin/key-helper validate

# Use the test environment
php vendor/bin/key-helper --stage=test validate

# Use the production environment
php vendor/bin/key-helper --stage=prod validate
```

The `.env.<stage>` file will be used based on the specified stage.

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

### Environment Variables

Configure your Stripe settings using the following environment variables:

```env
# Stripe API keys
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_TEST_KEY=sk_test_...

# Stripe API configuration
STRIPE_API_VERSION=2025-04-30
STRIPE_CURRENCY=usd
STRIPE_CURRENCY_LIMIT=100000 # Maximum transaction amount in smallest currency unit (e.g., cents)

# Webhook configuration
STRIPE_WEBHOOK_TOLERANCE=20 # Time tolerance for webhook signature validation (in seconds)
STRIPE_WEBHOOK_DEFAULT_TTL=172800 # Default time-to-live for webhook events (in seconds)

# Idempotency configuration
STRIPE_IDEMPOTENCY_TABLE=stripe_memory # Database table for storing idempotency events

# API request configuration
STRIPE_TIMEOUT=60 # Timeout for API requests (in seconds)
STRIPE_WEBHOOK_RETRIES=3 # Maximum number of retries for failed webhook events

# Stripe API endpoint
STRIPE_API_URL=https://api.stripe.com
```

### Configuration Structure

The configuration file supports the following options:

```php
return [
    'secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    'webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
    'test_key'        => getenv('STRIPE_TEST_KEY') ?: '',
    'api_version'     => getenv('STRIPE_API_VERSION') ?: '2025-04-30',
    'currency'        => getenv('STRIPE_CURRENCY') ?: 'usd',
    'currency_limit'  => (int)(getenv('STRIPE_CURRENCY_LIMIT') ?: 100000),
    'webhook_tolerance' => (int)(getenv('STRIPE_WEBHOOK_TOLERANCE') ?: 20),
    'webhook_default_ttl' => (int)(getenv('STRIPE_WEBHOOK_DEFAULT_TTL') ?: 172800),
    'idempotency_table' => getenv('STRIPE_IDEMPOTENCY_TABLE') ?: 'stripe_memory',
    'timeout'         => (int)(getenv('STRIPE_TIMEOUT') ?: 60),
    'webhook_retries' => (int)(getenv('STRIPE_WEBHOOK_RETRIES') ?: 3),
    'api_url'         => getenv('STRIPE_API_URL') ?: 'https://api.stripe.com',
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

### Environment-Specific Key Management

The `--stage` flag allows you to manage keys for specific environments (`dev`, `prod`, `test`).

#### Example

```bash
# Set keys for the test environment
php vendor/bin/key-helper --stage=test set

# Validate keys for the production environment
php vendor/bin/key-helper --stage=prod validate

# Generate a new key for the dev environment
php vendor/bin/key-helper --stage=dev generate
```

The `.env.<stage>` file will be updated or validated based on the specified stage.

**Default behavior without stage flag**: Uses `.env.dev` file

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

#### Validate Keys for a Specific Environment
```bash
# Validate keys for the test environment
php vendor/bin/key-helper --stage=test validate
```

## Webhook Handling

This package provides a robust webhook handling system that securely processes Stripe events while preventing duplicate processing.

### Environment-Aware Storage

The package automatically selects the appropriate storage backend for webhook idempotency based on your `APP_ENV` environment variable:

- **Development** (`dev`, default): **InMemoryStore** - Fast, no persistence needed for development
- **Testing** (`test`, `testing`): **SQLiteStore** - Persistent but lightweight SQLite database for testing
- **Production** (`prod`, `production`): **MySQLStore** - Robust, scalable MySQL database storage

```env
# Set your environment in .env file
APP_ENV=dev      # Uses InMemoryStore
APP_ENV=test     # Uses SQLiteStore  
APP_ENV=prod     # Uses MySQLStore (requires database configuration)
```

### Production Mode Features

In production mode (`APP_ENV=prod`), the webhook system provides additional robustness:

- **Automatic Retries**: Retries transient errors (rate limits, API connection issues) with exponential backoff
- **MySQL Storage**: Persistent storage for webhook idempotency across server restarts
- **Enhanced Logging**: Detailed error logging and retry attempts
- **Timeout Handling**: Process forking with timeout enforcement (Linux/Unix only)

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

### Error Handling & Retry Logic

The webhook controller implements intelligent error handling based on the environment:

```php
// Production mode (APP_ENV=prod):
// - Retries: Rate limits, API connection errors, server errors (5xx)
// - No Retry: Card errors, invalid requests, authentication errors
// - Uses exponential backoff: 60s, 120s, 180s

// Development mode (APP_ENV=dev):
// - No retries for any errors (fail fast for debugging)
// - Immediate error reporting
```

### Storage Implementation Details

#### InMemoryStore (Development)
- Pure PHP array storage
- No persistence between requests
- Automatic TTL-based cleanup
- Zero configuration required

#### SQLiteStore (Testing)
- Lightweight file-based database
- Persists across requests
- Creates database file automatically
- Default location: system temp directory

#### MySQLStore (Production)
- Full database persistence
- Requires `QueryBuilder` and `Connection`
- Uses configurable table name (`idempotency_store`)
- Supports clustering and high availability

### Database Schema

For production MySQL storage, the following table is required:

```sql
CREATE TABLE idempotency_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) UNIQUE NOT NULL,
    processed_at DATETIME NOT NULL,
    expiry DATETIME NULL,
    data JSON NULL,
    INDEX idx_event_id (event_id),
    INDEX idx_expiry (expiry)
);
```
```

### Configuring Webhook Processing

You can customize webhook processing behavior through environment variables:

```env
# Webhook processing configuration
STRIPE_TIMEOUT=60                    # Timeout for webhook processing (seconds)
STRIPE_WEBHOOK_RETRIES=3             # Maximum retry attempts (production only)
STRIPE_WEBHOOK_TOLERANCE=20          # Signature timestamp tolerance (seconds)
STRIPE_WEBHOOK_DEFAULT_TTL=172800    # Event storage TTL (48 hours in seconds)
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
