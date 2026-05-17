<?php

namespace MonkeysLegion\Stripe\Client;

use MonkeysLegion\HttpClient\HttpClient as BaseClient;
use MonkeysLegion\HttpClient\Bridge\PsrClientAdapter;
use MonkeysLegion\HttpClient\DTO\ClientConfig;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * MonkeysLegion Framework — Stripe Package
 *
 * PSR-18 HTTP client implementation for Stripe integration.
 * Now delegates to monkeyslegion-http-client via PsrClientAdapter,
 * eliminating the Guzzle runtime dependency.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
class HttpClient implements ClientInterface
{
    private readonly PsrClientAdapter $adapter;

    /**
     * HttpClient constructor.
     *
     * @param array<string, mixed> $config Optional client configuration
     */
    public function __construct(array $config = [])
    {
        $baseUrl = is_string($config['base_uri'] ?? null) ? $config['base_uri'] : '';
        $timeout = is_int($config['timeout'] ?? null) ? $config['timeout'] : 30;
        $verifySsl = ($config['verify'] ?? true) !== false;

        $client = new BaseClient(new ClientConfig(
            baseUrl: $baseUrl,
            timeout: $timeout,
            verifySsl: $verifySsl,
        ));

        $this->adapter = new PsrClientAdapter($client);
    }

    /**
     * Create a new HTTP client instance.
     *
     * @param array<string, mixed> $config Optional client configuration
     * @return HttpClient
     */
    public static function create(array $config = []): HttpClient
    {
        return new self($config);
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
        return $this->adapter->sendRequest($request);
    }
}
