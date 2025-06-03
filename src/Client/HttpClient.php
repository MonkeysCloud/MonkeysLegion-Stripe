<?php

namespace MonkeysLegion\Stripe\Client;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientExceptionInterface;
use GuzzleHttp\Exception\GuzzleException;

class HttpClient implements ClientInterface
{
    protected GuzzleClient $client;

    /**
     * HttpClient constructor.
     *
     * @param array $config Optional Guzzle configuration
     */
    public function __construct(array $config = [])
    {
        $this->client = new GuzzleClient($config);
    }

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
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->send($request);
        } catch (GuzzleException $e) {
            throw new class($e->getMessage(), $e->getCode(), $e) extends Exception implements ClientExceptionInterface {};
        }
    }
}
