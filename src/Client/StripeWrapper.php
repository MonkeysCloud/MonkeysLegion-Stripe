<?php

namespace MonkeysLegion\Stripe\Client;

use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use RuntimeException;

abstract class StripeWrapper
{
    protected StripeClient $stripe;
    protected array $stripeClients;
    protected bool $test_mode = true;

    public function __construct(array $stripeClients = [], bool $test_mode = true)
    {
        $this->stripeClients = $stripeClients;
        $this->setTestMode($test_mode);
    }

    /**
     * Set the test mode for the Stripe client.
     *
     * @param bool $test_mode true for test mode, false for live mode.
     */
    public function setTestMode(bool $test_mode): void
    {
        $this->test_mode = $test_mode;
        if (!isset($this->stripeClients[(int) $this->test_mode])) {
            throw new \RuntimeException("Stripe client for " . ($this->test_mode ? 'test' : 'live') . " mode is not defined.");
        }
        $this->stripe = $this->stripeClients[(int) $this->test_mode];
    }
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function handle(callable $callback)
    {
        try {
            return $callback();
        } catch (ApiErrorException $e) {
            throw new RuntimeException("Stripe error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Validates required parameters
     * 
     * @param array $params Parameters to validate
     * @param array $required Required parameter names
     * @throws \InvalidArgumentException
     */
    protected function validateRequired(array $params, array $required): void
    {
        foreach ($required as $param) {
            if (empty($params[$param])) {
                throw new \InvalidArgumentException("The '{$param}' parameter is required.");
            }
        }
    }

    /**
     * Validates that at least one of the parameters is set
     * 
     * @param array $params Parameters to validate
     * @param array $oneOf One of these parameter names must be set
     * @throws \InvalidArgumentException
     */
    protected function validateOneOf(array $params, array $oneOf): void
    {
        foreach ($oneOf as $param) {
            if (!empty($params[$param])) {
                return;
            }
        }
        throw new \InvalidArgumentException("At least one of the following parameters is required: " . implode(', ', $oneOf));
    }
}
