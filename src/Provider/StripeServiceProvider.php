<?php

namespace MonkeysLegion\Stripe\Provider;

define('WORKING_DIRECTORY', getcwd() . '/..');
define('STRIPE_CONFIG_PATH', WORKING_DIRECTORY . '/config/stripe.php');
define('STRIPE_CONFIG_DEFAULT_PATH', __DIR__ . '/../../config/stripe.php');
define('DB_CONFIG_PATH', WORKING_DIRECTORY . '/../config/database.php');
define('DB_CONFIG_DEFAULT_PATH', __DIR__ . '/../../config/database.php');

use MonkeysLegion\Database\MySQL\Connection;
use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Client\{CheckoutSession, Product, SetupIntentService, StripeGateway, Subscription};
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use Stripe\StripeClient;

class StripeServiceProvider
{
    public function __construct() {}

    /**
     * Register the services provided by this provider.
     * @return array<string, callable>
     */
    public static function register(ContainerBuilder $c): void
    {
        $in_container = ServiceContainer::getInstance();
        $stripeConfig = file_exists(STRIPE_CONFIG_PATH) ? require STRIPE_CONFIG_PATH : [];
        $defaults = file_exists(STRIPE_CONFIG_DEFAULT_PATH) ? require STRIPE_CONFIG_DEFAULT_PATH : [];
        $mergedStripeConfig = configMerger($stripeConfig, $defaults);
        $in_container->setConfig($mergedStripeConfig, 'stripe');

        $dbConfig = file_exists(DB_CONFIG_PATH) ? require DB_CONFIG_PATH : [];
        $dbDefaults = file_exists(DB_CONFIG_DEFAULT_PATH) ? require DB_CONFIG_DEFAULT_PATH : [];
        $mergedDbConfig = configMerger($dbConfig, $dbDefaults);
        $in_container->setConfig($mergedDbConfig, 'db');

        // Register in internal container
        $in_container->set('connection', fn() => new Connection($mergedDbConfig));
        $in_container->set('query_builder', fn() => new QueryBuilder($in_container->get('connection')));
        $in_container->set('stripe_client', fn() => new StripeClient($mergedStripeConfig['secret_key']));
        $in_container->set('stripe_client_test', fn() => new StripeClient($mergedStripeConfig['test_key']));
        $in_container->set('memory_idempotency_store', function () use ($in_container) {
            $appEnv = $_ENV['APP_ENV'] ?? 'dev';
            return match ($appEnv) {
                'prod', 'production' => new MemoryIdempotencyStore($in_container->get('query_builder')),
                default => new MemoryIdempotencyStore()
            };
        });
        $in_container->set('webhook_middleware', fn() => new WebhookMiddleware(
            array_intersect_key($mergedStripeConfig, array_flip(['test_key', 'webhook_secret'])),
            $mergedStripeConfig['webhook_tolerance'],
            $in_container->get('memory_idempotency_store'),
            $mergedStripeConfig['webhook_default_ttl'],
            true
        ));
        $in_container->set('webhook_controller', fn() => new WebhookController(
            $in_container->get('webhook_middleware'),
            $in_container->get('memory_idempotency_store'),
            $in_container
        ));

        $in_container->set('stripe_gateway', fn() => new StripeGateway([
            $in_container->get('stripe_client_test'),
            $in_container->get('stripe_client')
        ]));
        $in_container->set('setup_intent_service', fn() => new SetupIntentService([
            $in_container->get('stripe_client_test'),
            $in_container->get('stripe_client')
        ]));
        $in_container->set('checkout_session', fn() => new CheckoutSession([
            $in_container->get('stripe_client_test'),
            $in_container->get('stripe_client')
        ]));
        $in_container->set('subscription', fn() => new Subscription([
            $in_container->get('stripe_client_test'),
            $in_container->get('stripe_client')
        ]));
        $in_container->set('product', fn() => new Product([
            $in_container->get('stripe_client_test'),
            $in_container->get('stripe_client')
        ]));

        // Register in ContainerBuilder using the same instances
        $c->addDefinitions([
            Connection::class => fn() => $in_container->get('connection'),
            QueryBuilder::class => fn() => $in_container->get('query_builder'),
            StripeClient::class => fn() => $in_container->get('stripe_client'),
            MemoryIdempotencyStore::class => fn() => $in_container->get('memory_idempotency_store'),
            WebhookMiddleware::class => fn() => $in_container->get('webhook_middleware'),
            WebhookController::class => fn() => $in_container->get('webhook_controller'),
            StripeGateway::class => fn() => $in_container->get('stripe_gateway'),
            SetupIntentService::class => fn() => $in_container->get('setup_intent_service'),
            CheckoutSession::class => fn() => $in_container->get('checkout_session'),
            Subscription::class => fn() => $in_container->get('subscription'),
            Product::class => fn() => $in_container->get('product'),
        ]);
    }
}
