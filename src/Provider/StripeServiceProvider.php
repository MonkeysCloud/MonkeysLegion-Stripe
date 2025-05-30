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
        $appOverrides = $this->c->get('config')['stripe'] ?? [];

        $this->config = mergeStripeConfig($config, $appOverrides);
    }

    /**
     * Register the Stripe client in the container.
     * @return void
     */
    public function register(): void
    {
        // Register the HTTP client
        $this->c->set('http_client', function () {
            return HttpClient::create($this->config);
        });

        // Register the Stripe client with the HTTP client injected
        $this->c->set('StripeClient', function () {
            return new StripeClient($this->c->get('http_client'));
        });
    }
}
