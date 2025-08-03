<?php

namespace MonkeysLegion\Stripe\Provider;

define('STRIPE_CONFIG_PATH', WORKING_DIRECTORY . '/config/stripe.' . ($_ENV['APP_ENV'] ?? 'dev') . '.php');
define('STRIPE_CONFIG_DEFAULT_PATH', __DIR__ . '/../../config/stripe.php');
define('DB_CONFIG_PATH', WORKING_DIRECTORY . '/../config/database.php');
define('DB_CONFIG_DEFAULT_PATH', __DIR__ . '/../../config/database.php');

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use MonkeysLegion\Core\Logger\MonkeyLogger;
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
        /** @var FrameworkLoggerInterface $logger */
        $logger = $in_container->get(FrameworkLoggerInterface::class) ?? new MonkeyLogger();

        try {
            // Load stripe configurations
            $stripeConfig = file_exists(STRIPE_CONFIG_PATH) ? require STRIPE_CONFIG_PATH : [];
            $defaults = file_exists(STRIPE_CONFIG_DEFAULT_PATH) ? require STRIPE_CONFIG_DEFAULT_PATH : [];
            $mergedStripeConfig = array_replace_recursive($defaults, $stripeConfig);

            // Load Database configurations
            $dbConfig = file_exists(DB_CONFIG_PATH) ? require DB_CONFIG_PATH : [];
            $dbDefaults = file_exists(DB_CONFIG_DEFAULT_PATH) ? require DB_CONFIG_DEFAULT_PATH : [];
            $mergedDbConfig = array_replace_recursive($dbDefaults, $dbConfig);

            // set configurations in the ServiceContainer
            $in_container->setConfig($mergedStripeConfig, 'stripe');
            $in_container->setConfig($mergedDbConfig, 'db');

            // Register database services
            self::registerDatabaseServices($in_container, $mergedDbConfig);

            // Register Stripe client services
            self::registerStripeClients($in_container, $mergedStripeConfig);

            // Register idempotency and webhook services
            self::registerWebhookServices($in_container, $mergedStripeConfig, $logger);

            // Register Stripe feature services
            self::registerStripeFeatureServices($in_container, $logger);

            // Register in ContainerBuilder using the same instances
            self::registerWithContainerBuilder($c, $in_container);
        } catch (\Exception $e) {
            $logger->error("StripeServiceProvider: Error during registration - " . $e->getMessage(), [
                'exception' => $e,
            ]);
            // No need to throw an exception here, as the logger will handle it.
            // And no need to block app functionality.
        }
    }

    /**
     * Register database-related services
     */
    private static function registerDatabaseServices(ServiceContainer $container, array $dbConfig): void
    {
        $container->set(Connection::class, fn() => new Connection($dbConfig));
        $container->set(QueryBuilder::class, fn() => new QueryBuilder($container->get(Connection::class)));
    }

    /**
     * Register Stripe client instances
     */
    private static function registerStripeClients(ServiceContainer $container, array $stripeConfig): void
    {
        $container->set(StripeClient::class, fn() => new StripeClient($stripeConfig['secret_key']));
        $container->set('stripe_client_test', fn() => new StripeClient($stripeConfig['test_key']));
    }

    /**
     * Register webhook and idempotency services
     */
    private static function registerWebhookServices(
        ServiceContainer $container,
        array $stripeConfig,
        FrameworkLoggerInterface $logger
    ): void {
        $container->set(MemoryIdempotencyStore::class, function () use ($container) {
            $appEnv = $_ENV['APP_ENV'] ?? 'dev';
            return match ($appEnv) {
                'prod', 'production' => new MemoryIdempotencyStore($container->get(QueryBuilder::class)),
                default => new MemoryIdempotencyStore()
            };
        });

        $container->set(WebhookMiddleware::class, fn() => new WebhookMiddleware(
            array_intersect_key($stripeConfig, array_flip(['webhook_secret', 'webhook_secret_test'])),
            $stripeConfig['webhook_tolerance'],
            $container->get(MemoryIdempotencyStore::class),
            $stripeConfig['webhook_default_ttl'],
            true
        ));

        $container->set(WebhookController::class, fn() => new WebhookController(
            $container->get(WebhookMiddleware::class),
            $container->get(MemoryIdempotencyStore::class),
            $container,
            $logger
        ));
    }

    /**
     * Register Stripe feature services
     */
    private static function registerStripeFeatureServices(
        ServiceContainer $container,
        FrameworkLoggerInterface $logger
    ): void {
        $clients = [
            $container->get('stripe_client_test'),
            $container->get(StripeClient::class)
        ];

        $serviceClasses = [
            StripeGateway::class,
            SetupIntentService::class,
            CheckoutSession::class,
            Subscription::class,
            Product::class,
        ];

        foreach ($serviceClasses as $serviceClass) {
            $container->set($serviceClass, fn() => new $serviceClass($clients, true, $logger));
        }
    }

    /**
     * Register services with the container builder
     */
    private static function registerWithContainerBuilder(
        ContainerBuilder $builder,
        ServiceContainer $container
    ): void {
        $services = [
            Connection::class,
            QueryBuilder::class,
            StripeClient::class,
            MemoryIdempotencyStore::class,
            WebhookMiddleware::class,
            WebhookController::class,
            StripeGateway::class,
            SetupIntentService::class,
            CheckoutSession::class,
            Subscription::class,
            Product::class,
        ];

        $definitions = [];
        foreach ($services as $service) {
            $definitions[$service] = fn() => $container->get($service);
        }

        $builder->addDefinitions($definitions);
    }

    public static function setLogger(MonkeyLogger $logger): void
    {
        $container = ServiceContainer::getInstance();
        $container->set(FrameworkLoggerInterface::class, fn() => $logger);
    }
}
