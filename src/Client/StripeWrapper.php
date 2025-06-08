<?php

namespace MonkeysLegion\Stripe\Client;

use Stripe\Exception\ApiErrorException;
use RuntimeException;

abstract class StripeWrapper
{
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
}
