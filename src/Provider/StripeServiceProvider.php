<?php

namespace MonkeysLegion\Stripe\Provider;

use MonkeysLegion\Stripe\Client\StripeClient;
use MonkeysLegion\Stripe\Client\HttpClient;
use MonkeysLegion\Stripe\Service\ServiceContainer;


class StripeServiceProvider
{
    protected $c;
    protected array $config = [];

    public function __construct(ServiceContainer $container)
    {
        $this->c = $container;

        $config = require __DIR__ . '/../../config/stripe.php' ?? [];
        $appOverrides = require getcwd() . '/../config/stripe.php' ?? [];

        $this->config = mergeStripeConfig($config, $appOverrides);
    }

    /**
     * Register the Stripe client in the container.
     * @return void
     */
    public function register(): void
    {
        // Register the HTTP client For Only Stripe
        // This is a separate HTTP client specifically for Stripe operations
        $this->c->set('stripe_http_client', function () {
            return HttpClient::create($this->config);
        });

        // Register the Stripe client with the Stripe HTTP client injected
        $this->c->set('StripeClient', function () {
            return new StripeClient($this->c->get('stripe_http_client'));
        });
    }
}
