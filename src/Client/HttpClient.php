<?php

namespace MonkeysLegion\Stripe\Client;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;

class HttpClient implements ClientInterface
{
    /**
     * Create a new HTTP client instance.
     *
     * @param array $config Optional Guzzle configuration
     * @return GuzzleClient
     */
    public static function create(array $config = [])
    {
        return new GuzzleClient($config);
    }

    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface if an error happens while processing the request
     */
    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        // This method should be implemented to send the request using Guzzle or any other HTTP client.
        // For now, we will throw an exception to indicate that this method needs to be implemented.
        throw new \RuntimeException('Method sendRequest not implemented.');
    }
}
