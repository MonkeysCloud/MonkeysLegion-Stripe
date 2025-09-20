<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Enum;

enum Stages: string
{
    case DEV = 'dev';
    case DEVELOPMENT = 'development';

    case TEST = 'test';
    case TESTING = 'testing';

    case PROD = 'prod';
    case PRODUCTION = 'production';
}
