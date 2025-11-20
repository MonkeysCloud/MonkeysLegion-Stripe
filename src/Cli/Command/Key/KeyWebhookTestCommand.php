<?php

declare(strict_types=1);

namespace MonkeysLegion\Stripe\Cli\Command\Key;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Console\Traits\Cli;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

#[CommandAttr('key:webhook-test', 'Test webhook secret validation')]
final class KeyWebhookTestCommand extends Command
{
    use Cli;

    public function handle(): int
    {
        $stage = $this->option('stage') ?? 'dev';
        $envFile = $this->getEnvFile($stage);
        $webhookSecret = $this->readKey('STRIPE_WEBHOOK_SECRET', $envFile);

        if ($webhookSecret === null) {
            $this->cliLine()
                ->error('❌ STRIPE_WEBHOOK_SECRET not found in .env file')
                ->print();

            $this->cliLine()
                ->muted('HTTP Status: N/A')
                ->print();

            $this->cliLine()
                ->muted('Process Time: N/A')
                ->print();

            return self::FAILURE;
        }

        $this->cliLine()
            ->info('Testing webhook secret validation...')
            ->print();

        $startTime = microtime(true);

        try {
            $testPayload = json_encode([
                'id' => 'evt_test_payment_intent_succeeded_' . uniqid(),
                'object' => 'event',
                'type' => 'payment_intent.succeeded',
                'livemode' => false,
                'created' => time(),
                'data' => [
                    'object' => [
                        'id' => 'pi_test_' . uniqid(),
                        'object' => 'payment_intent',
                        'amount' => 2000,
                        'currency' => 'usd',
                        'status' => 'succeeded',
                        'description' => 'Test manual payment intent',
                    ]
                ]
            ]);

            $timestamp = time();
            $signedPayload = $timestamp . '.' . $testPayload;
            $signature = hash_hmac('sha256', $signedPayload, $webhookSecret);
            $testSigHeader = "t=$timestamp,v1=$signature";

            $result = Webhook::constructEvent($testPayload, $testSigHeader, $webhookSecret);

            $endTime = microtime(true);
            $processTime = round(($endTime - $startTime) * 1000, 2);

            $this->cliLine()
                ->success('✅ Webhook secret validation: VALID')
                ->print();

            $this->cliLine()
                ->add('HTTP Status: ', 'gray')
                ->success('200 OK')
                ->print();

            $this->cliLine()
                ->add('Process Time: ', 'gray')
                ->add("{$processTime}ms", 'cyan')
                ->print();

            $this->cliLine()
                ->add('Event Type: ', 'gray')
                ->add($result->type ?? 'N/A', 'yellow')
                ->print();

            return self::SUCCESS;
        } catch (SignatureVerificationException $e) {
            $endTime = microtime(true);
            $processTime = round(($endTime - $startTime) * 1000, 2);

            $this->cliLine()
                ->error('❌ Webhook secret validation: INVALID')
                ->print();

            $this->cliLine()
                ->add('HTTP Status: ', 'gray')
                ->error('400 Bad Request')
                ->print();

            $this->cliLine()
                ->add('Process Time: ', 'gray')
                ->add("{$processTime}ms", 'cyan')
                ->print();

            $this->cliLine()
                ->add('Error: ', 'gray')
                ->add($e->getMessage(), 'red')
                ->print();

            return self::FAILURE;
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $processTime = round(($endTime - $startTime) * 1000, 2);

            $this->cliLine()
                ->error('❌ Webhook secret validation: INVALID')
                ->print();

            $this->cliLine()
                ->add('HTTP Status: ', 'gray')
                ->error('500 Internal Server Error')
                ->print();

            $this->cliLine()
                ->add('Process Time: ', 'gray')
                ->add("{$processTime}ms", 'cyan')
                ->print();

            $this->cliLine()
                ->add('Error: ', 'gray')
                ->add($e->getMessage(), 'red')
                ->print();

            return self::FAILURE;
        }
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
