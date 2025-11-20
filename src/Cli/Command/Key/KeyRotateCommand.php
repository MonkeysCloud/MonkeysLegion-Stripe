<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Cli\Command\Key;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;

#[CommandAttr('key:rotate', 'Rotate (replace) a Stripe key')]
final class KeyRotateCommand extends Command
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

            $this->cliLine()
                ->add('Available types: ', 'yellow')
                ->add(implode(', ', array_keys(self::ALLOWED_KEYS)), 'cyan')
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

        $this->cliLine()
            ->info('Rotating key ')
            ->add($keyName, 'cyan', 'bold')
            ->add('...', 'white')
            ->print();

        $oldKey = $this->readKey($keyName, $envFile);
        if ($oldKey) {
            $this->cliLine()
                ->add('Old key: ', 'gray')
                ->add($oldKey, 'yellow')
                ->print();
        }

        $newKey = $this->generateDefaultValue($keyName);
        $this->saveKey($newKey, $keyName, $envFile);

        $this->cliLine()
            ->add('New key ', 'white')
            ->add("($keyName)", 'cyan', 'bold')
            ->add(': ', 'white')
            ->add($newKey, 'green', 'bold')
            ->print();

        $this->cliLine()
            ->success('✅ Key rotation completed successfully!')
            ->print();

        return self::SUCCESS;
    }

    private function generateDefaultValue(string $keyType): string
    {
        return match ($keyType) {
            'STRIPE_SECRET_KEY', 'STRIPE_TEST_KEY' => 'sk_test_' . bin2hex(random_bytes(24)),
            'STRIPE_PUBLISHABLE_KEY' => 'pk_test_' . bin2hex(random_bytes(24)),
            'STRIPE_WEBHOOK_SECRET', 'STRIPE_WEBHOOK_SECRET_TEST' => 'whsec_' . bin2hex(random_bytes(24)),
            default => bin2hex(random_bytes(32)),
        };
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

    private function saveKey(string $key, string $keyName, string $envFile): void
    {
        // ...existing code from KeyGenerateCommand...
        $dir = dirname($envFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    [$k, $v] = explode('=', $line, 2);
                    $env[trim($k)] = trim($v);
                }
            }
        }

        $env[$keyName] = $key;

        $content = '';
        foreach ($env as $k => $v) {
            $content .= "$k=$v\n";
        }
        file_put_contents($envFile, $content);
    }
}
