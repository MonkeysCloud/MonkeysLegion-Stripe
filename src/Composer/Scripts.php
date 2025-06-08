<?php

namespace MonkeysLegion\Stripe\Composer;

class Scripts
{
    public static function validateConfig(): void
    {
        $configPath = __DIR__ . '/../../config/stripe.php';

        if (!file_exists($configPath)) {
            echo "❌ Missing Stripe config file: config/stripe.php\n";
            exit(1);
        }

        echo "✅ Stripe config file is valid.\n";
    }
}
