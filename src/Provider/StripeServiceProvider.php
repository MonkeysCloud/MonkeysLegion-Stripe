<?php

namespace MonkeysLegion\Stripe\Provider;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use MonkeysLegion\Core\Logger\MonkeyLogger;
use MonkeysLegion\Core\Provider\ProviderInterface;
use MonkeysLegion\Database\MySQL\Connection;
use MonkeysLegion\DI\ContainerBuilder;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Client\{CheckoutSession, Product, SetupIntentService, StripeGateway, Subscription};
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use Stripe\StripeClient;

class StripeServiceProvider implements ProviderInterface
{
    public function __construct() {}

    public static function register(string $root, ContainerBuilder $c): void
    {
        $in_container = ServiceContainer::getInstance();
        /** @var FrameworkLoggerInterface $logger */
        $logger = $in_container->get(FrameworkLoggerInterface::class) ?? new MonkeyLogger();

        try {
            $stripeConfigPath = $root . '/config/stripe.php';
            $stripeConfigDefaultPath = __DIR__ . '/../../config/stripe.php';
            $dbConfigPath = $root . '/../config/database.php';
            $dbConfigDefaultPath = __DIR__ . '/../../config/database.php';

            // Load stripe configurations
            $stripeConfig = self::require($stripeConfigPath);
            $defaults = self::require($stripeConfigDefaultPath);
            /** @var array<string, mixed> $mergedStripeConfig */
            $mergedStripeConfig = array_replace_recursive($defaults, $stripeConfig);


            // Load Database configurations
            $dbConfig = self::require($dbConfigPath);
            $dbDefaults = self::require($dbConfigDefaultPath);
            /** @var array<string, mixed> $mergedDbConfig */
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
     *
     * @param ServiceContainer $container
     * @param array<string, mixed> $dbConfig
     */
    private static function registerDatabaseServices(ServiceContainer $container, array $dbConfig): void
    {
        $container->set(Connection::class, fn() => new Connection($dbConfig));

        /** @var Connection $conn */
        $conn = $container->get(Connection::class);
        $container->set(QueryBuilder::class, fn() => new QueryBuilder($conn));
    }

    /**
     * Register Stripe client instances
     *
     * @param ServiceContainer $container
     * @param array<string, mixed> $stripeConfig
     */
    private static function registerStripeClients(ServiceContainer $container, array $stripeConfig): void
    {
        $secret_key = isset($stripeConfig['secret_key']) && is_string($stripeConfig['secret_key']) ? $stripeConfig['secret_key'] : '';
        $test_key = isset($stripeConfig['test_key']) && is_string($stripeConfig['test_key']) ? $stripeConfig['test_key'] : '';

        $container->set(StripeClient::class, fn() => new StripeClient($secret_key));
        $container->set('stripe_client_test', fn() => new StripeClient($test_key));
    }

    /**
     * Register webhook and idempotency services
     *
     * @param ServiceContainer $container
     * @param array<string, mixed> $stripeConfig
     * @param FrameworkLoggerInterface $logger
     */
    private static function registerWebhookServices(
        ServiceContainer $container,
        array $stripeConfig,
        FrameworkLoggerInterface $logger
    ): void {
        // Register MemoryIdempotencyStore
        $container->set(MemoryIdempotencyStore::class, function () use ($container) {
            $appEnv = $_ENV['APP_ENV'] ?? 'dev';
            /** @var QueryBuilder $qb */
            $qb = $container->get(QueryBuilder::class);

            return match ($appEnv) {
                'prod', 'production' => new MemoryIdempotencyStore($qb),
                default => new MemoryIdempotencyStore(),
            };
        });

        // Ensure endpoint secrets exist and are arrays
        $keys = [
            'webhook_secret'      => $stripeConfig['webhook_secret'] ?? throw new \RuntimeException('Missing webhook_secret in config'),
            'webhook_secret_test' => $stripeConfig['webhook_secret_test'] ?? throw new \RuntimeException('Missing webhook_secret_test in config'),
        ];

        $webhook_tolerance = (int) ($stripeConfig['webhook_tolerance'] ?? 300);
        $webhook_default_ttl = isset($stripeConfig['webhook_default_ttl'])
            ? (int) $stripeConfig['webhook_default_ttl']
            : 172800; // default 48 hours

        // Register WebhookMiddleware
        $container->set(WebhookMiddleware::class, function () use ($container, $keys, $webhook_tolerance, $webhook_default_ttl) {
            /** @var MemoryIdempotencyStore $idempotencyStore */
            $idempotencyStore = $container->get(MemoryIdempotencyStore::class);

            return new WebhookMiddleware(
                $keys,                  // endpoint secrets as array
                $webhook_tolerance,     // int
                $idempotencyStore,      // store
                $webhook_default_ttl,   // TTL
                true                    // test mode
            );
        });

        // Register WebhookController
        $container->set(WebhookController::class, function () use ($container, $logger) {
            /** @var WebhookMiddleware $webhookMiddleware */
            $webhookMiddleware = $container->get(WebhookMiddleware::class);
            /** @var MemoryIdempotencyStore $idempotencyStore */
            $idempotencyStore = $container->get(MemoryIdempotencyStore::class);

            return new WebhookController(
                $webhookMiddleware,
                $idempotencyStore,
                $container,
                $logger
            );
        });
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
            'stripe_client_test',
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

    public static function setLogger(FrameworkLoggerInterface $logger): void
    {
        $container = ServiceContainer::getInstance();
        $container->set(FrameworkLoggerInterface::class, fn() => $logger);
    }

    /**
     * Require a PHP file and return its returned array.
     *
     * @param string $path
     * @return array<mixed>
     */
    private static function require(string $path): array
    {
        if (!file_exists($path)) return [];
        $content = require $path;

        return is_array($content) ? $content : [];
    }
}
