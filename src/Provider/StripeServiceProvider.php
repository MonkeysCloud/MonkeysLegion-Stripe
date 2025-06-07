<?php

namespace MonkeysLegion\Stripe\Provider;

use MonkeysLegion\Stripe\Client\SetupIntentService;
use MonkeysLegion\Stripe\Service\ServiceContainer;
use MonkeysLegion\Stripe\Webhook\WebhookController;
use Stripe\StripeClient;

class StripeServiceProvider
{
    protected $c;
    protected array $config = [];

    public function __construct(ServiceContainer $container)
    {
        $this->c = $container;

        $config = require __DIR__ . '/../../config/stripe.php' ?? [];
        $appOverrides = file_exists(getcwd() . '/config/stripe.php') 
            ? require getcwd() . '/config/stripe.php' 
            : [];
        
        $this->config = mergeStripeConfig($config, $appOverrides);
    }

    /**
     * Register the Stripe client in the container.
     * @return void
     */
    public function register(): void
    {
        // Register the official Stripe client
        $this->c->set('stripe_client', function () {
            return new StripeClient($this->config['test_key']);
        });

        // Register the Stripe Gateway with the official client injected
        $this->c->set('StripeServices', function () {
            return new SetupIntentService($this->c->get('stripe_client'));
        });

        // Register the Stripe Webhook controller
        $this->c->set('StripeWebhook', function () {
            return new WebhookController($this->config['webhook_secret']);
        });
    }
}
