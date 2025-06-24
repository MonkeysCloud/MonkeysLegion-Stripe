<?php
declare(strict_types=1);

namespace MonkeysLegion\Stripe\Cli\Command;

use FilesystemIterator;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Cli\Command\MakerHelpers;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Publish Stripe scaffolding (controllers, routes, config, docs) into the host app
 * without relying on the symfony/filesystem component, and ensures .env has Stripe keys.
 */
#[CommandAttr('stripe:install', 'Publish Stripe scaffolding into your project')]
final class StripeInstallCommand extends Command
{
    use MakerHelpers;

    public function handle(): int
    {
        $projectRoot = base_path();
        $stubDir = __DIR__ . '/../../../stubs';

        // 1) Copy scaffolding files
        $map = [
            "{$stubDir}/Controller/StripeController.php"                => "{$projectRoot}/app/Controller/StripeController.php",
            "{$stubDir}/Controller/ProductController.php"               => "{$projectRoot}/app/Controller/ProductController.php",
            "{$stubDir}/Controller/WebhookController.php"               => "{$projectRoot}/app/Controller/WebhookController.php",
            "{$stubDir}/config/stripe.php"                              => "{$projectRoot}/config/stripe.php",
            "{$stubDir}/config/stripe.mlc"                              => "{$projectRoot}/config/stripe.mlc",
            "{$stubDir}/config/stripe/stripe.dev.php"                   => "{$projectRoot}/config/stripe/stripe.dev.php",
            "{$stubDir}/config/stripe/stripe.prod.php"                  => "{$projectRoot}/config/stripe/stripe.prod.php",
            "{$stubDir}/config/stripe/stripe.test.php"                  => "{$projectRoot}/config/stripe/stripe.test.php",
            "{$stubDir}/public/assets/css/app.css"                      => "{$projectRoot}/public/assets/css/app.css",
            "{$stubDir}/resources/views/components/nav-bar.ml.php"      => "{$projectRoot}/resources/views/components/nav-bar.ml.php",
            "{$stubDir}/resources/views/docs/checkout-session.ml.php"   => "{$projectRoot}/resources/views/docs/checkout-session.ml.php",
            "{$stubDir}/resources/views/docs/payment-intent.ml.php"     => "{$projectRoot}/resources/views/docs/payment-intent.ml.php",
            "{$stubDir}/resources/views/docs/product.ml.php"            => "{$projectRoot}/resources/views/docs/product.ml.php",
            "{$stubDir}/resources/views/docs/setup-intent.ml.php"       => "{$projectRoot}/resources/views/docs/setup-intent.ml.php",
            "{$stubDir}/resources/views/docs/subscription.ml.php"       => "{$projectRoot}/resources/views/docs/subscription.ml.php",
            "{$stubDir}/resources/views/layouts/docs.ml.php"            => "{$projectRoot}/resources/views/layouts/docs.ml.php",
            "{$stubDir}/resources/views/webhook/demo.ml.php"            => "{$projectRoot}/resources/views/webhook/demo.ml.php",
        ];

        foreach ($map as $from => $to) {
            if (is_dir($from)) {
                $this->mirror($from, $to);
                $this->info('✓ Published directory ' . str_replace($projectRoot . '/', '', $to));
                continue;
            }

            if (file_exists($to) && !$this->shouldOverwrite($to, $projectRoot)) {
                continue;
            }

            $this->copyFile($from, $to);
            $this->info('✓ Published file ' . str_replace($projectRoot . '/', '', $to));
        }

        // 2) Ensure .env contains Stripe keys
        $this->ensureEnvKeys($projectRoot);

        // 3) Patch config/app.php: expose Stripe routes as public paths
        $this->exposeStripePaths($projectRoot);

        $this->line('<info>Stripe scaffolding and .env setup complete!</info>');
        return self::SUCCESS;
    }

    private function ensureEnvKeys(string $projectRoot): void
    {
        $envFile = $projectRoot . '/.env';
        if (!file_exists($envFile)) {
            $this->warn('.env file not found; skipping Stripe key injection.');
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $required = [
            'STRIPE_PUBLISHABLE_KEY',
            'STRIPE_SECRET_KEY',
            'STRIPE_TEST_KEY',
            'STRIPE_WEBHOOK_SECRET',
            'STRIPE_WEBHOOK_SECRET_TEST',
            'STRIPE_API_VERSION',
            'STRIPE_CURRENCY',
            'STRIPE_CURRENCY_LIMIT',
            'STRIPE_WEBHOOK_TOLERANCE',
            'STRIPE_WEBHOOK_DEFAULT_TTL',
            'STRIPE_IDEMPOTENCY_TABLE',
            'STRIPE_TIMEOUT',
            'STRIPE_WEBHOOK_RETRIES',
            'STRIPE_MAX_PAYLOAD_SIZE',
            'STRIPE_API_URL',
        ];

        $missing = [];
        foreach ($required as $key) {
            $found = false;
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), "$key=")) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $key;
            }
        }

        if (empty($missing)) {
            $this->info('All Stripe keys already present in .env.');
            return;
        }

        // Append missing keys with placeholder comments
        $append = "# Stripe configuration added by stripe:install command\n";
        foreach ($missing as $key) {
            $comment = match ($key) {
                'STRIPE_API_VERSION' => '# Stripe API version to use',
                'STRIPE_CURRENCY_LIMIT' => '# Maximum transaction amount in smallest currency unit (e.g., cents)',
                'STRIPE_WEBHOOK_TOLERANCE' => '# Time tolerance (in seconds) for webhook signature validation',
                'STRIPE_WEBHOOK_DEFAULT_TTL' => '# Default TTL (in seconds) for webhook events',
                'STRIPE_IDEMPOTENCY_TABLE' => '# Database table for idempotency keys',
                'STRIPE_MAX_PAYLOAD_SIZE' => '# Maximum size (in bytes) for Stripe webhook payloads',
                default => ''
            };
            $append .= "$key=" . strtoupper($key) . "_VALUE $comment\n";
        }

        file_put_contents($envFile, "\n" . $append, FILE_APPEND);
        $this->info('✓ Added missing Stripe keys to .env: ' . implode(', ', $missing));
    }


    /**
     * Add Stripe endpoint patterns to AuthMiddleware publicPaths in config/app.php.
     */
    private function exposeStripePaths(string $projectRoot): void
    {
        $file = "{$projectRoot}/config/app.php";
        if (!is_file($file)) {
            $this->warn('config/app.php not found; skipping public path injection.');
            return;
        }
        $contents = file_get_contents($file);

        // 1) Find the entire new AuthMiddleware(...) call
        $pattern = '/
        (new\s+AuthMiddleware\s*      # “new AuthMiddleware”
         \(
           (.*?)                      #   capture all arguments
         \)
        )
    /sx';

        if (!preg_match($pattern, $contents, $m, PREG_OFFSET_CAPTURE)) {
            $this->warn('AuthMiddleware binding not found; manual configuration may be required.');
            return;
        }

        [$fullCall, $offset] = $m[1];
        $insideArgs         = $m[2][0];

        // 2) Split out arguments (simple: split on commas not inside brackets)
        $args = preg_split('/,(?![^[]*\])/', $insideArgs);

        // Trim whitespace
        $args = array_map('trim', $args);

        // 3) Determine existing public-paths array
        //    It’ll be the 4th argument if present and starts with “[”
        $existingItems = [];
        if (isset($args[3]) && str_starts_with($args[3], '[')) {
            // strip brackets and whitespace
            $body = trim($args[3], "[] \t\n\r");
            if ($body !== '') {
                // split on commas
                foreach (preg_split('/,(?![^\'"]*[\'"])/', $body) as $item) {
                    $existingItems[] = trim($item, " \t\n\r'\"");
                }
            }
        }

        // 4) Define the six paths to ensure
        $toAdd = [
            '/stripe/*', '/docs', '/docs/*', '/success', '/cancel'
        ];

        // 5) Merge & preserve order + remove duplicates
        $merged = array_values(array_unique(array_merge($existingItems, $toAdd)));

        // 6) Build the new short-array literal
        $publicPathsCode = "[\n"
            . "    '" . implode("',\n    '", $merged) . "',\n"
            . "]";

        // 7) Reconstruct the argument list:
        //    - replace the 4th arg if existed, otherwise append it
        if (isset($args[3]) && str_starts_with($args[3], '[')) {
            $args[3] = $publicPathsCode;
        } else {
            $args[] = $publicPathsCode;
        }

        // 8) Re-join arguments and rebuild the call
        $newCall = 'new AuthMiddleware(' . implode(', ', $args) . ')';

        // 9) Splice back into the file
        $newContents = substr_replace(
            $contents,
            $newCall,
            (int)$offset,
            strlen($fullCall)
        );

        file_put_contents($file, $newContents);
        $this->info('✓ Merged Stripe & docs paths into AuthMiddleware publicPaths.');
    }

    /**
     * Recursively copy a directory using native PHP iterators.
     */
    private function mirror(string $source, string $dest): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $target   = $dest . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                $this->copyFile($item->getPathname(), $target);
            }
        }
    }

    /**
     * Copy a single file ensuring the destination directory exists.
     */
    private function copyFile(string $from, string $to): void
    {
        @mkdir(dirname($to), 0755, true);
        copy($from, $to);
    }

    /**
     * Prompt the user to confirm overwriting an existing file.
     * Returns true if the user confirms, false otherwise.
     */
    private function shouldOverwrite(string $to, string $projectRoot): bool
    {
        $overwrite = $this->confirm(str_replace($projectRoot . '/', '', $to) . ' exists, overwrite?', false);
        if (!$overwrite) {
            $this->line('↷ Skipped ' . str_replace($projectRoot . '/', '', $to));
        }
        return $overwrite;
    }

    /**
     * Ask a yes/no question and return true for 'yes', false for 'no'.
     * Defaults to the provided value if no input is given.
     */
    private function confirm(string $question, bool $default = false): bool
    {
        $answer = $this->ask($question . ($default ? ' [Y/n]' : ' [y/N]'));
        if ($answer === '') {
            return $default;
        }
        return in_array(strtolower($answer), ['y', 'yes'], true);
    }

    /**
     * Output a warning message to the console.
     */
    private function warn(string $message): void
    {
        $this->line('<comment>' . $message . '</comment>');
    }
}
