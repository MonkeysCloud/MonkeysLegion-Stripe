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
use MonkeysLegion\Stripe\Logger\Logger;
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use Psr\Log\LoggerInterface;
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
        $in_container->setConfig($mergedDbConfig, 'db');        // Register in internal container
        $in_container->set(Connection::class, fn() => new Connection($mergedDbConfig));
        $in_container->set(QueryBuilder::class, fn() => new QueryBuilder($in_container->get(Connection::class)));
        $in_container->set(StripeClient::class, fn() => new StripeClient($mergedStripeConfig['secret_key']));
        $in_container->set('stripe_client_test', fn() => new StripeClient($mergedStripeConfig['test_key']));
        $in_container->set(MemoryIdempotencyStore::class, function () use ($in_container) {
            $appEnv = $_ENV['APP_ENV'] ?? 'dev';
            return match ($appEnv) {
                'prod', 'production' => new MemoryIdempotencyStore($in_container->get(QueryBuilder::class)),
                default => new MemoryIdempotencyStore()
            };
        });
        $in_container->set(Logger::class, fn() => new Logger());
        $in_container->set(WebhookMiddleware::class, fn() => new WebhookMiddleware(
            array_intersect_key($mergedStripeConfig, array_flip(['webhook_secret', 'webhook_secret_test'])),
            $mergedStripeConfig['webhook_tolerance'],
            $in_container->get(MemoryIdempotencyStore::class),
            $mergedStripeConfig['webhook_default_ttl'],
            true
        ));
        $in_container->set(WebhookController::class, fn() => new WebhookController(
            $in_container->get(WebhookMiddleware::class),
            $in_container->get(MemoryIdempotencyStore::class),
            $in_container
        ));
        $in_container->set(StripeGateway::class, fn() => new StripeGateway([
            $in_container->get('stripe_client_test'),
            $in_container->get(StripeClient::class)
        ], true, $in_container->get(Logger::class)));
        $in_container->set(SetupIntentService::class, fn() => new SetupIntentService([
            $in_container->get('stripe_client_test'),
            $in_container->get(StripeClient::class)
        ], true, $in_container->get(Logger::class)));
        $in_container->set(CheckoutSession::class, fn() => new CheckoutSession([
            $in_container->get('stripe_client_test'),
            $in_container->get(StripeClient::class)
        ], true, $in_container->get(Logger::class)));
        $in_container->set(Subscription::class, fn() => new Subscription([
            $in_container->get('stripe_client_test'),
            $in_container->get(StripeClient::class)
        ], true, $in_container->get(Logger::class)));
        $in_container->set(Product::class, fn() => new Product([
            $in_container->get('stripe_client_test'),
            $in_container->get(StripeClient::class)
        ], true, $in_container->get(Logger::class)));

        // Register in ContainerBuilder using the same instances
        $c->addDefinitions([
            Connection::class => fn() => $in_container->get(Connection::class),
            QueryBuilder::class => fn() => $in_container->get(QueryBuilder::class),
            StripeClient::class => fn() => $in_container->get(StripeClient::class),
            MemoryIdempotencyStore::class => fn() => $in_container->get(MemoryIdempotencyStore::class),
            Logger::class => fn() => $in_container->get(Logger::class),
            WebhookMiddleware::class => fn() => $in_container->get(WebhookMiddleware::class),
            WebhookController::class => fn() => $in_container->get(WebhookController::class),
            StripeGateway::class => fn() => $in_container->get(StripeGateway::class),
            SetupIntentService::class => fn() => $in_container->get(SetupIntentService::class),
            CheckoutSession::class => fn() => $in_container->get(CheckoutSession::class),
            Subscription::class => fn() => $in_container->get(Subscription::class),
            Product::class => fn() => $in_container->get(Product::class),
        ]);
    }

    public static function setLogger(LoggerInterface $logger): void
    {
        $container = ServiceContainer::getInstance();
        $container->get(Logger::class)->setLogger($logger);
        $container->get(WebhookController::class)->setLogger($container->get(Logger::class));
        $container->get(StripeGateway::class)->setLogger($container->get(Logger::class));
        $container->get(SetupIntentService::class)->setLogger($container->get(Logger::class));
        $container->get(CheckoutSession::class)->setLogger($container->get(Logger::class));
        $container->get(Subscription::class)->setLogger($container->get(Logger::class));
        $container->get(Product::class)->setLogger($container->get(Logger::class));
    }
}
