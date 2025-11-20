<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Cli\Command\Key;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;

#[CommandAttr('key:validate', 'Validate Stripe keys in .env file')]
final class KeyValidateCommand extends Command
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
        $stage = $this->option('stage') ?? 'dev';
        $envFile = $this->getEnvFile($stage);

        if ($keyType) {
            return $this->validateSingleKey($keyType, $envFile);
        }

        return $this->validateAllKeys($envFile, $stage);
    }

    private function validateAllKeys(string $envFile, string $stage): int
    {
        $this->cliLine()
            ->info('Validating all Stripe keys for environment: ')
            ->add($stage, 'cyan', 'bold')
            ->print();

        $this->line('');

        $hasErrors = false;

        foreach (self::ALLOWED_KEYS as $alias => $keyName) {
            $key = $this->readKey($keyName, $envFile);

            if ($key === null) {
                $this->cliLine()
                    ->add('⚠️  ', 'yellow')
                    ->add($keyName, 'white', 'bold')
                    ->add(" ($alias)", 'gray')
                    ->add(': ', 'white')
                    ->warning('NOT SET')
                    ->print();
                $hasErrors = true;
            } elseif ($this->validateKey($key, $keyName)) {
                $this->cliLine()
                    ->add('✅ ', 'green')
                    ->add($keyName, 'white', 'bold')
                    ->add(" ($alias)", 'gray')
                    ->add(': ', 'white')
                    ->success('VALID')
                    ->print();
            } else {
                $this->cliLine()
                    ->add('❌ ', 'red')
                    ->add($keyName, 'white', 'bold')
                    ->add(" ($alias)", 'gray')
                    ->add(': ', 'white')
                    ->error('INVALID')
                    ->print();
                $hasErrors = true;
            }
        }

        $this->line('');

        if ($hasErrors) {
            $this->cliLine()
                ->warning('Some keys have issues. Please check the warnings above.')
                ->print();
            return self::FAILURE;
        }

        $this->cliLine()
            ->success('All keys are valid!')
            ->print();

        return self::SUCCESS;
    }

    private function validateSingleKey(string $keyType, string $envFile): int
    {
        if (!isset(self::ALLOWED_KEYS[$keyType])) {
            $this->cliLine()
                ->error('Invalid key type: ')
                ->add($keyType, 'red')
                ->print();
            return self::FAILURE;
        }

        $keyName = self::ALLOWED_KEYS[$keyType];

        $this->cliLine()
            ->info('Validating ')
            ->add($keyName, 'cyan', 'bold')
            ->add('...', 'white')
            ->print();

        $key = $this->readKey($keyName, $envFile);

        if ($key === null) {
            $this->cliLine()
                ->error("Key '$keyName' not found in .env file.")
                ->print();
            return self::FAILURE;
        }

        if ($this->validateKey($key, $keyName)) {
            $this->cliLine()
                ->success("Key '$keyName' is valid.")
                ->print();

            $this->cliLine()
                ->add('Value: ', 'gray')
                ->add($key, 'yellow')
                ->print();

            return self::SUCCESS;
        }

        $this->cliLine()
            ->error("Key '$keyName' is invalid!")
            ->print();

        $this->cliLine()
            ->add('Current value: ', 'gray')
            ->add($key, 'red')
            ->print();

        return self::FAILURE;
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

    private function validateKey(string $key, string $keyType): bool
    {
        if (empty(trim($key))) {
            return false;
        }

        return match ($keyType) {
            'STRIPE_SECRET_KEY', 'STRIPE_TEST_KEY' => (str_starts_with($key, 'sk_test_') || str_starts_with($key, 'sk_live_')) && strlen($key) > 20,
            'STRIPE_PUBLISHABLE_KEY' => (str_starts_with($key, 'pk_test_') || str_starts_with($key, 'pk_live_')) && strlen($key) > 20,
            'STRIPE_WEBHOOK_SECRET', 'STRIPE_WEBHOOK_SECRET_TEST' =>
            str_starts_with($key, 'whsec_') && strlen($key) >= 7,
            default => strlen($key) > 0,
        };
    }
}
