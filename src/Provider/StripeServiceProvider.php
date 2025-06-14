<?php

namespace MonkeysLegion\Stripe\Provider;

define('WORKING_DIRECTORY', getcwd());
define('STRIPE_CONFIG_PATH', WORKING_DIRECTORY . '/config/stripe.php');
define('STRIPE_CONFIG_DEFAULT_PATH', __DIR__ . '/../../config/stripe.php');
define('DB_CONFIG_PATH', WORKING_DIRECTORY . '/../config/database.php');
define('DB_CONFIG_DEFAULT_PATH', __DIR__ . '/../../config/database.php');

use MonkeysLegion\Database\MySQL\Connection;
use MonkeysLegion\Query\QueryBuilder;
use MonkeysLegion\Stripe\Client\{CheckoutSession, Product, SetupIntentService, StripeGateway, Subscription};
use MonkeysLegion\Stripe\Middleware\WebhookMiddleware;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Storage\MemoryIdempotencyStore;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use Stripe\StripeClient;

class StripeServiceProvider
{
    private $c;
    private array $stripeConfig = [];
    private ?array $dbConfig = null;

    public function __construct(ServiceContainer $container)
    {
        $this->c = $container;

        $StripeConfig = file_exists(STRIPE_CONFIG_PATH) ? require STRIPE_CONFIG_PATH : [];
        $appOverrides = file_exists(STRIPE_CONFIG_DEFAULT_PATH)
            ? require STRIPE_CONFIG_DEFAULT_PATH
            : [];
        $this->stripeConfig = configMerger($StripeConfig, $appOverrides);

        $dbConfig =  file_exists(DB_CONFIG_PATH) ? require DB_CONFIG_PATH : [];
        $appOverrides = file_exists(DB_CONFIG_DEFAULT_PATH)
            ? require DB_CONFIG_DEFAULT_PATH
            : [];
        $this->dbConfig = configMerger($dbConfig, $appOverrides);
    }

    /**
     * Register the Stripe client in the container.
     * @return void
     */
    public function register(): void
    {
        // ------------------------------------------------------------------------
        // Register database connection
        $this->c->set('db_connection', function () {
            return new Connection($this->dbConfig);
        });
        // ------------------------------------------------------------------------
        // Register query builder
        $this->c->set('query_builder', function () {
            return new QueryBuilder($this->c->get('db_connection'));
        });
        // ------------------------------------------------------------------------
        // Register Stripe client
        $this->c->set('stripe_client', function () {
            return new StripeClient($this->stripeConfig['test_key']);
        });
        // ------------------------------------------------------------------------
        // Register idempotency store
        $this->c->set('idempotency_store', function () {
            return new MemoryIdempotencyStore($this->c->get('query_builder'));
        });
        // ------------------------------------------------------------------------
        // Register webhook middleware
        $this->c->set('webhook_middleware', function () {
            return new WebhookMiddleware(
                $this->stripeConfig['webhook_secret'],
                $this->stripeConfig['webhook_tolerance'],
                $this->c->get('idempotency_store'),
                $this->stripeConfig['webhook_default_ttl']
            );
        });
        // ------------------------------------------------------------------------
        // Register webhook controller
        $this->c->set('webhook_controller', function () {
            return new WebhookController(
                $this->c->get('webhook_middleware'),
                $this->c->get('idempotency_store')
            );
        });
        // ------------------------------------------------------------------------
        // Register Stripe services 
        $this->c->set('StripeGateway', function () {
            return new StripeGateway($this->c->get('stripe_client'));
        });

        $this->c->set('SetupIntentService', function () {
            return new SetupIntentService($this->c->get('stripe_client'));
        });

        $this->c->set('CheckoutSessionService', function () {
            return new CheckoutSession($this->c->get('stripe_client'));
        });

        $this->c->set('SubscriptionService', function () {
            return new Subscription($this->c->get('stripe_client'));
        });

        $this->c->set('ProductService', function () {
            return new Product($this->c->get('stripe_client'));
        });
        // ------------------------------------------------------------------------
    }
}
