<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Cli\Command\Key;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;

#[CommandAttr('key:list', 'List all Stripe and Webhook keys from .env')]
final class KeyListCommand extends Command
{
    use Cli;

    public function handle(): int
    {
        $stage = $this->option('stage') ?? 'dev';
        $envFile = $this->getEnvFile($stage);

        if (!file_exists($envFile)) {
            $this->cliLine()
                ->error('No .env file found.')
                ->print();
            return self::FAILURE;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $relevantKeys = [];

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $keyName = trim($k);
                if (str_starts_with($keyName, 'STRIPE') || str_starts_with($keyName, 'WEBHOOK')) {
                    $relevantKeys[$keyName] = trim($v);
                }
            }
        }

        if (empty($relevantKeys)) {
            $this->cliLine()
                ->warning('No STRIPE or WEBHOOK keys found.')
                ->print();
            return self::SUCCESS;
        }

        $this->cliLine()
            ->info('STRIPE and WEBHOOK keys found:')
            ->print();

        $this->line('');

        foreach ($relevantKeys as $key => $value) {
            $this->cliLine()
                ->add('  ', 'white')
                ->add($key, 'cyan', 'bold')
                ->add(' = ', 'gray')
                ->add($value, 'yellow')
                ->print();
        }

        return self::SUCCESS;
    }

    private function getEnvFile(string $stage): string
    {
        $base = base_path() . '/.env';
        return $stage === 'dev' ? $base : $base . '.' . $stage;
    }
}
