<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Set testing environment variables
$_ENV['APP_ENV'] = 'testing';

// Create SQLite database directory for testing
$dbDir = __DIR__ . '/../database';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// Initialize test environment
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_1234');
\Stripe\Stripe::setApiVersion('2025-04-30');
