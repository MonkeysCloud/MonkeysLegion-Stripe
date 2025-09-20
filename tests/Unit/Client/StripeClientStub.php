<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Client;

class StripeClientStub extends \Stripe\StripeClient
{
    public $paymentIntents;
    public $refunds;
}
