<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\Stripe\Service\ServiceContainer;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientExceptionInterface;

class StripeClient
{
    protected HttpClient $httpClient;

    public function __construct(ServiceContainer $container)
    {
        $this->initialize($container->get('http_client'));
    }

    private function initialize(HttpClient $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \RuntimeException if HTTP client not set
     * @throws ClientExceptionInterface if an error happens while processing the request
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (!isset($this->httpClient)) {
            throw new \RuntimeException('HTTP client not set.');
        }

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw $e;
        }
    }
}
