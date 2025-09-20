<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Exceptions;

use RuntimeException;

class EventAlreadyProcessedException extends RuntimeException
{
    public function __construct(string $eventId)
    {
        parent::__construct("Event already processed: $eventId");
    }
}
