<?php

/**
 * Merges the Stripe configuration from the vendor and app.
 * @param array $vendor The vendor configuration array.
 * @param array $app The app configuration array.
 */
function mergeStripeConfig(array $vendor, array $app): array
{
    foreach ($app as $k => $v) {
        if (is_array($v) && isset($vendor[$k])) {
            $vendor[$k] = mergeStripeConfig($vendor[$k], $v);
        } else {
            $vendor[$k] = $v;
        }
    }
    return $vendor;
}
