<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Cli\Command\Key;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;

#[CommandAttr('key:show', 'Display the current value of a Stripe key')]
final class KeyShowCommand extends Command
{
    use Cli;

    private const ALLOWED_KEYS = [
        'secret'      => 'STRIPE_SECRET_KEY',
        'test'        => 'STRIPE_TEST_KEY',
        'publishable' => 'STRIPE_PUBLISHABLE_KEY',
        'webhook'     => 'STRIPE_WEBHOOK_SECRET',
        'webhook_test' => 'STRIPE_WEBHOOK_SECRET_TEST'
    ];

    public function handle(): int
    {
        $keyType = $this->argument(0);

        if (!$keyType) {
            $this->cliLine()
                ->error('You must specify a key type.')
                ->print();
            return self::FAILURE;
        }

        if (!isset(self::ALLOWED_KEYS[$keyType])) {
            $this->cliLine()
                ->error('Invalid key type: ')
                ->add($keyType, 'red')
                ->print();
            return self::FAILURE;
        }

        $keyName = self::ALLOWED_KEYS[$keyType];
        $stage = $this->option('stage') ?? 'dev';
        $envFile = $this->getEnvFile($stage);

        $key = $this->readKey($keyName, $envFile);

        if ($key === null) {
            $this->cliLine()
                ->error("No key named '$keyName' found.")
                ->print();
            return self::FAILURE;
        }

        $this->cliLine()
            ->add('Current key ', 'white')
            ->add("($keyName)", 'cyan', 'bold')
            ->add(': ', 'white')
            ->add($key, 'yellow', 'bold')
            ->print();

        return self::SUCCESS;
    }

    private function getEnvFile(string $stage): string
    {
        $base = base_path() . '/.env';
        return $stage === 'dev' ? $base : $base . '.' . $stage;
    }

    private function readKey(string $keyName, string $envFile): ?string
    {
        if (!file_exists($envFile)) {
            return null;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                if (trim($k) === $keyName) {
                    return trim($v);
                }
            }
        }
        return null;
    }
}
