# MonkeysLegion Stripe Integration

[![PHP Version](https://img.shields.io/badge/php-8.4%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

First-class Stripe integration package for the MonkeysLegion PHP framework, providing PSR-compliant HTTP clients and service container integration.

## 📋 What You'll Learn

This documentation covers everything you need to integrate Stripe payments into your MonkeysLegion application:

- **🚀 Quick Start**: Get up and running in minutes with automated setup
- **🔧 Configuration**: Environment variables, key management, and security setup
- **🔑 Key Management**: Interactive CLI tools for managing Stripe API keys and webhook secrets
- **📋 Service Registration**: Dependency injection setup with MonkeysLegion DI container
- **💳 Payment Operations**: Complete API for payment intents, checkout sessions, subscriptions, and products
- **🔄 Test/Live Mode**: Seamless switching between test and production environments
- **🪝 Webhook Handling**: Secure webhook processing with signature verification and idempotency
- **📊 Logging**: PSR-3 compatible logging with Monolog integration
- **🛡️ Security**: Payload validation, size limits, and secure key storage

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
- MonkeysLegion DI ^1.0 (`composer require monkeyscloud/monkeyslegion-di`)
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
# Essential Stripe API keys (managed by key-helper)
STRIPE_SECRET_KEY=sk_test_...            # Main secret key for backend API calls
STRIPE_PUBLISHABLE_KEY=pk_test_...       # Public key for frontend Stripe.js
STRIPE_WEBHOOK_SECRET=whsec_...          # Webhook secret for production/main environment
STRIPE_TEST_KEY=sk_test_...              # Additional test secret key
STRIPE_WEBHOOK_SECRET_TEST=whsec_...     # Webhook secret specifically for test mode

# Optional Stripe API configuration (not validated by key-helper)
STRIPE_API_VERSION=2025-04-30            # Stripe API version your code expects
STRIPE_CURRENCY=usd                      # Default currency for transactions
STRIPE_CURRENCY_LIMIT=100000             # Maximum transaction amount in cents

# Optional webhook configuration
STRIPE_WEBHOOK_TOLERANCE=20              # Time tolerance for webhook signature validation (seconds)
STRIPE_WEBHOOK_DEFAULT_TTL=172800        # Default time-to-live for webhook events (seconds)
STRIPE_MAX_PAYLOAD_SIZE=131072           # Maximum webhook payload size in bytes (default: 128KB)

# Optional idempotency configuration
STRIPE_IDEMPOTENCY_TABLE=stripe_memory   # Database table for storing idempotency events

# Optional API request configuration
STRIPE_TIMEOUT=60                        # Timeout for API requests (seconds)
STRIPE_WEBHOOK_RETRIES=3                 # Maximum number of retries for failed webhook events

# Optional Stripe API endpoint
STRIPE_API_URL=https://api.stripe.com    # Stripe API base URL
```

**Note**: The key-helper utility validates only the essential Stripe keys. Additional configuration variables can be added manually to your `.env` files as needed.

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

## Key Management

The package includes a comprehensive key management utility for generating, validating, and managing Stripe API keys and webhook secrets. All operations are performed through the command line interface.

### Key-Helper Features

- **Environment-Aware**: Works with `.env.dev`, `.env.prod`, `.env.test` files
- **Secure Key Generation**: Uses cryptographically secure random bytes
- **Format Validation**: Validates Stripe key formats and structures
- **Interactive Setup**: Guided setup for all essential Stripe keys
- **Key Rotation**: Safe rotation of existing keys with backup
- **Webhook Testing**: Live webhook secret validation with Stripe SDK
- **Batch Operations**: Validate all keys at once or individually

### Command Reference

```bash
php vendor/bin/key-helper [--stage=dev|prod|test] [COMMAND] [OPTIONS]

Available Commands:
  generate [KEY_TYPE]          # Generate and save a new key (default: STRIPE_SECRET_KEY)
  set [KEY_NAME] [VALUE]       # Set a specific key or enter interactive mode
  rotate [KEY_TYPE]            # Rotate (replace) an existing key with backup
  validate [KEY_TYPE]          # Validate keys (all if no type specified)
  show [KEY_TYPE]              # Display current key value
  list                         # List all Stripe/webhook keys in environment
  webhook:test                 # Test webhook secret validation with simulated payload

Available Key Types:
  secret                       # STRIPE_SECRET_KEY
  test                         # STRIPE_TEST_KEY
  publishable                  # STRIPE_PUBLISHABLE_KEY
  webhook                      # STRIPE_WEBHOOK_SECRET
  webhook_test                 # STRIPE_WEBHOOK_SECRET_TEST

Stage Options:
  --stage=dev                  # Use .env.dev file (default)
  --stage=prod                 # Use .env.prod file
  --stage=test                 # Use .env.test file
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

#### Generate Default Secret Key
```bash
# Generates STRIPE_SECRET_KEY (sk_test_* format)
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

# Generate test webhook secret
php vendor/bin/key-helper generate webhook_test
```

### Rotate Keys

#### Rotate Security Keys
```bash
# Rotate secret keys with new generated values
php vendor/bin/key-helper rotate secret
php vendor/bin/key-helper rotate webhook
php vendor/bin/key-helper rotate publishable
php vendor/bin/key-helper rotate webhook_test
```

#### Environment-Specific Rotation
```bash
# Rotate production keys
php vendor/bin/key-helper --stage=prod rotate secret
php vendor/bin/key-helper --stage=prod rotate webhook

# Rotate test environment keys
php vendor/bin/key-helper --stage=test rotate webhook
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
# - STRIPE_TEST_KEY
# - STRIPE_WEBHOOK_SECRET
# - STRIPE_WEBHOOK_SECRET_TEST
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
# ✅ STRIPE_WEBHOOK_SECRET_TEST (webhook_test): VALID
```

#### Validate Specific Keys
```bash
# Validate individual key types
php vendor/bin/key-helper validate secret
php vendor/bin/key-helper validate webhook
php vendor/bin/key-helper validate webhook_test
```

#### Validate Keys for a Specific Environment
```bash
# Validate keys for the test environment
php vendor/bin/key-helper --stage=test validate

# Validate specific key in production
php vendor/bin/key-helper --stage=prod validate secret
```

### Advanced Key Management

#### Show Current Key Values
```bash
# Display current key values
php vendor/bin/key-helper show secret
php vendor/bin/key-helper show webhook
php vendor/bin/key-helper show webhook_test

# Show keys for specific environment
php vendor/bin/key-helper --stage=prod show secret
```

#### List All Configuration Keys
```bash
# List all STRIPE and WEBHOOK keys
php vendor/bin/key-helper list

# Example output:
# STRIPE and WEBHOOK keys found:
#   STRIPE_SECRET_KEY = sk_test_...
#   STRIPE_PUBLISHABLE_KEY = pk_test_...
#   STRIPE_WEBHOOK_SECRET = whsec_...
#   STRIPE_WEBHOOK_SECRET_TEST = whsec_...
```

#### Webhook Secret Testing
```bash
# Test webhook secret validation with simulated Stripe payload
php vendor/bin/key-helper webhook:test

# Example output:
# Testing webhook secret validation...
# ✅ Webhook secret validation: VALID
# HTTP Status: 200 OK
# Process Time: 15.42ms
# Event Type: payment_intent.succeeded
```

### Key Validation Features

The key-helper validates different types of keys with specific format requirements:

#### Supported Validation Types
- **Stripe Secret Keys**: `sk_test_*` or `sk_live_*` format, minimum 20 characters
- **Stripe Publishable Keys**: `pk_test_*` or `pk_live_*` format, minimum 20 characters  
- **Webhook Secrets**: `whsec_*` format, minimum 7 characters
- **Generated Keys**: 64-character hexadecimal strings for app keys
- **Placeholder Validation**: Accepts partial keys ending with `...` for development

#### Error Handling
```bash
# Example validation errors:
# ❌ STRIPE_SECRET_KEY (secret): INVALID
# ⚠️  STRIPE_WEBHOOK_SECRET (webhook): NOT SET
# ✅ STRIPE_PUBLISHABLE_KEY (publishable): VALID
```

### Environment File Management

The key-helper safely manages environment files with these features:

#### File Safety
- **Atomic Operations**: All file operations are atomic to prevent corruption
- **Backup on Rotation**: Old keys are displayed before replacement
- **Comment Preservation**: Comments in `.env` files are preserved during updates
- **Directory Creation**: Automatically creates directories if they don't exist

#### Multi-Environment Support
- **Stage-Specific Files**: Supports `.env.dev`, `.env.prod`, `.env.test`
- **Environment Isolation**: Each environment has separate key management
- **Consistent Interface**: Same commands work across all environments

#### Key Generation Security
- **Cryptographically Secure**: Uses PHP's `random_bytes()` for key generation
- **Appropriate Formats**: Generates keys in correct Stripe format (sk_test_, pk_test_, whsec_)
- **Configurable Length**: Supports different key lengths for different purposes

## Service Registration

### Registration
(In Your app.php)

register the service provider by:
Install DI package via Composer:

```bash
composer require monkeyscloud/monkeyslegion-stripe
```php
use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\Stripe\Provider\StripeServiceProvider;

// Create a new container builder instance
$containerBuilder = new ContainerBuilder();

// Register the Stripe service provider
StripeServiceProvider::register($containerBuilder);

// Build the container
$container = $containerBuilder->build();

// Globalize it
define('ML_CONTAINER', $container);

// Get Stripe services using class names
$stripeClient = ML_CONTAINER->get(\Stripe\StripeClient::class);
$stripeGateway = ML_CONTAINER->get(\MonkeysLegion\Stripe\Client\StripeGateway::class);
$checkoutSession = ML_CONTAINER->get(\MonkeysLegion\Stripe\Client\CheckoutSession::class);
$subscription = ML_CONTAINER->get(\MonkeysLegion\Stripe\Client\Subscription::class);
$product = ML_CONTAINER->get(\MonkeysLegion\Stripe\Client\Product::class);
$setupIntentService = ML_CONTAINER->get(\MonkeysLegion\Stripe\Client\SetupIntentService::class);
$webhookController = ML_CONTAINER->get(\MonkeysLegion\Stripe\Webhook\WebhookController::class);
```
### Test Mode Management

All client services support switching between test and live modes:

```php
// Switch to test mode (default)
$stripeGateway->setTestMode(true);      // Uses STRIPE_TEST_KEY
$checkoutSession->setTestMode(true);    // Uses STRIPE_TEST_KEY
$subscription->setTestMode(true);       // Uses STRIPE_TEST_KEY

// Switch to live mode for production
$stripeGateway->setTestMode(false);     // Uses STRIPE_SECRET_KEY
$checkoutSession->setTestMode(false);   // Uses STRIPE_SECRET_KEY
$subscription->setTestMode(false);      // Uses STRIPE_SECRET_KEY

// You may use this approach if you prefer
$isProduction = ($_ENV['APP_ENV'] ?? 'dev') === 'prod';
$stripeGateway->setTestMode(!$isProduction);
```

## Usage

### Payment Intent Operations

```php
// Get the gateway service
$stripeGateway = $container->get(\MonkeysLegion\Stripe\Client\StripeGateway::class);

// Create a payment intent
$paymentIntent = $stripeGateway->createPaymentIntent(
    2000,        // amount in cents
    'usd',       // currency
    true         // enable automatic payment methods
);

// Retrieve a payment intent
$paymentIntent = $stripeGateway->retrievePaymentIntent('pi_1234567890');

// Confirm a payment intent
$confirmedPayment = $stripeGateway->confirmPaymentIntent('pi_1234567890', [
    'payment_method' => 'pm_card_visa'
]);

// Cancel a payment intent
$cancelledPayment = $stripeGateway->cancelPaymentIntent('pi_1234567890');

// Capture a payment intent (for manual capture)
$capturedPayment = $stripeGateway->capturePaymentIntent('pi_1234567890');

// Refund a payment intent
$refund = $stripeGateway->refundPaymentIntent('pi_1234567890', [
    'amount' => 1000  // partial refund
]);

// Update a payment intent
$updatedPayment = $stripeGateway->updatePaymentIntent('pi_1234567890', [
    'description' => 'Updated payment description'
]);

// List payment intents
$paymentIntents = $stripeGateway->listPaymentIntent([
    'limit' => 10,
    'customer' => 'cus_1234567890'
]);

// Search payment intents
$searchResults = $stripeGateway->searchPaymentIntent([
    'query' => 'status:\'succeeded\' AND metadata[\'order_id\']:\'12345\''
]);

// Increment authorization
$incrementedPayment = $stripeGateway->incrementAuthorization('pi_1234567890', 500);

// Check if payment intent is valid
$isValid = $stripeGateway->isValidPaymentIntent('pi_1234567890');
```

### Checkout Session Operations

```php
// Get the checkout service
$checkoutSession = $container->get(\MonkeysLegion\Stripe\Client\CheckoutSession::class);

// Create a checkout session
$session = $checkoutSession->createCheckoutSession([
    'mode' => 'payment',
    'line_items' => [
        [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Premium Plan'
                ],
                'unit_amount' => 2000,
            ],
            'quantity' => 1,
        ],
    ],
    'success_url' => 'https://example.com/success?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'https://example.com/cancel',
]);

// Retrieve a checkout session
$session = $checkoutSession->retrieveCheckoutSession('cs_1234567890');

// List checkout sessions
$sessions = $checkoutSession->listCheckoutSessions([
    'limit' => 10
]);

// Expire a checkout session
$expiredSession = $checkoutSession->expireCheckoutSession('cs_1234567890');

// List line items from a session
$lineItems = $checkoutSession->listLineItems('cs_1234567890');

// Get checkout URL directly
$checkoutUrl = $checkoutSession->getCheckoutUrl([
    'mode' => 'payment',
    'line_items' => [/* ... */],
    'success_url' => 'https://example.com/success',
    'cancel_url' => 'https://example.com/cancel',
]);

// Validate checkout session
$isValid = $checkoutSession->isValidCheckoutSession('cs_1234567890');

// Check if session is expired
$isExpired = $checkoutSession->isExpiredCheckoutSession('cs_1234567890');
```

### Subscription Operations

```php
// Get the subscription service
$subscription = $container->get(\MonkeysLegion\Stripe\Client\Subscription::class);

// Create a subscription
$newSubscription = $subscription->createSubscription(
    'cus_1234567890',  // customer ID
    'price_1234567890', // price ID
    [
        'trial_period_days' => 7,
        'metadata' => ['plan' => 'premium']
    ]
);

// Retrieve a subscription
$subscription = $subscription->retrieveSubscription('sub_1234567890');

// Update a subscription
$updatedSubscription = $subscription->updateSubscription('sub_1234567890', [
    'metadata' => ['updated' => 'true'],
    'proration_behavior' => 'create_prorations'
]);

// Cancel a subscription
$cancelledSubscription = $subscription->cancelSubscription('sub_1234567890', [
    'at_period_end' => true
]);

// List customer subscriptions
$subscriptions = $subscription->listSubscriptions('cus_1234567890', [
    'status' => 'active',
    'limit' => 10
]);

// Resume a subscription
$resumedSubscription = $subscription->resumeSubscription('sub_1234567890');

// Search subscriptions
$searchResults = $subscription->searchSubscriptions([
    'query' => 'status:\'active\' AND metadata[\'plan\']:\'premium\''
]);
```

### Product Operations

```php
// Get the product service
$product = $container->get(\MonkeysLegion\Stripe\Client\Product::class);

// Create a product
$newProduct = $product->createProduct([
    'name' => 'Premium Software License',
    'description' => 'Annual software license with premium features',
    'metadata' => ['category' => 'software']
]);

// Retrieve a product
$product = $product->retrieveProduct('prod_1234567890');

// Update a product
$updatedProduct = $product->updateProduct('prod_1234567890', [
    'name' => 'Updated Premium License',
    'description' => 'Updated description'
]);

// Delete a product
$deletedProduct = $product->deleteProduct('prod_1234567890');

// List products
$products = $product->listProducts([
    'active' => true,
    'limit' => 10
]);

// Search products
$searchResults = $product->searchProducts(
    'metadata[\'category\']:\'software\'',
    ['limit' => 20]
);
```

### Setup Intent Operations

```php
// Get the setup intent service
$setupIntentService = $container->get(\MonkeysLegion\Stripe\Client\SetupIntentService::class);

// Create a setup intent
$setupIntent = $setupIntentService->createSetupIntent([
    'customer' => 'cus_1234567890',
    'payment_method_types' => ['card'],
    'usage' => 'off_session'
]);

// Retrieve a setup intent
$setupIntent = $setupIntentService->retrieveSetupIntent('seti_1234567890');

// Confirm a setup intent
$confirmedSetupIntent = $setupIntentService->confirmSetupIntent('seti_1234567890', [
    'payment_method' => 'pm_card_visa'
]);

// Cancel a setup intent
$cancelledSetupIntent = $setupIntentService->cancelSetupIntent('seti_1234567890');

// Update a setup intent
$updatedSetupIntent = $setupIntentService->updateSetupIntent('seti_1234567890', [
    'metadata' => ['updated' => 'true']
]);

// List setup intents
$setupIntents = $setupIntentService->listSetupIntents([
    'customer' => 'cus_1234567890',
    'limit' => 10
]);

// Validate setup intent
$isValid = $setupIntentService->isValidSetupIntent('seti_1234567890');
```

### Webhook Handling
### Setting Up Webhooks

1. **Configure Webhook Secret**
   ```bash
   # Set your webhook secret from Stripe Dashboard
   php vendor/bin/key-helper set STRIPE_WEBHOOK_SECRET whsec_your_secret_here
   
   # Verify it's configured correctly
   php vendor/bin/key-helper webhook:test
   ```

```php
// Get the webhook controller
$webhookController = ML_CONTAINER->get(\MonkeysLegion\Stripe\Webhook\WebhookController::class);

// Handle incoming webhook
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$result = $webhookController->handle($payload, $sigHeader, function($event) {
    return ['status' => 'success', 'event' => $event['type']];
});

// Complete webhook endpoint example
function handleWebhook() {
    // This should be at your app.php
    $containerBuilder = new ContainerBuilder();
    StripeServiceProvider::register($containerBuilder);
    $container = $containerBuilder->build();
    define('ML_CONTAINER', $container);
    
    // 
    $webhookController = ML_CONTAINER->get(\MonkeysLegion\Stripe\Webhook\WebhookController::class);
    
    $payload = file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    try {
        $result = $webhookController->handle($payload, $sigHeader, function ($event) {
            // Your event handling logic here
            return processStripeEvent($event);
        });

        http_response_code(200);
        echo json_encode($result);
    } catch (\Throwable $e) {
        $code = $e->getCode();

        // Ensure we don't return invalid HTTP codes
        if ($code < 100 || $code >= 600) $code = 500

        http_response_code($code);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
```
```php
// Production mode (APP_ENV=prod):
// - Retries: Rate limits, API connection errors, server errors (5xx)
// - No Retry: Card errors, invalid requests, authentication errors
// - Uses exponential backoff: 60s, 120s, 180s

// Development mode (APP_ENV=dev):
// - No retries for any errors (fail fast for debugging)
// - Immediate error reporting
```
This package provides also a robust webhook handling system that securely processes Stripe events while preventing duplicate processing.

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

### Error Handling & Retry Logic

The webhook controller implements intelligent error handling based on the environment:

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
    'webhook_default_ttl' => 86400, // 24 hours (in seconds)    // ...other config
];
```

## PSR-3 Logger Integration

The package includes built-in PSR-3 logger support for comprehensive logging of Stripe operations and webhook processing.

### Default Logger Behavior

By default, the package uses an internal Logger class that supports PSR-3 LoggerInterface. The logging behavior adapts to your environment:

- **Development** (`APP_ENV=dev`): Debug level messages
- **Testing** (`APP_ENV=test`): Notice level messages  
- **Production** (`APP_ENV=prod`): Warning level messages

### Logger Features

- **Structured Logging**: Includes context data like request IDs, error codes, and retry counts
- **Environment-Aware**: Automatically adjusts log levels based on APP_ENV
- **Operation Tracking**: Logs all Stripe API calls, webhook processing, and error handling
- **Security**: Sensitive data like API keys are never logged

### Webhook Payload Size Limits

For security, webhook payloads are limited to a maximum size to prevent memory exhaustion attacks:

- **Default Size Limit**: 128KB (131,072 bytes)
- **Environment Variable**: `STRIPE_MAX_PAYLOAD_SIZE=131072`
- **Automatic Validation**: Payloads exceeding the limit are rejected with detailed logging

```env
# Configure webhook payload size limit
STRIPE_MAX_PAYLOAD_SIZE=131072  # 128KB (default)
STRIPE_MAX_PAYLOAD_SIZE=262144  # 256KB (custom)
```

The payload validation includes:
- Size limit checking
- JSON format validation  
- Empty payload detection
- Detailed error logging for debugging
