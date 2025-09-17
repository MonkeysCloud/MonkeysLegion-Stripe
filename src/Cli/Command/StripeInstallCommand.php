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
                $this->info('✓ Published directory ' . str_replace($projectRoot . '/', '', $to . "\n"));
                continue;
            }

            if (file_exists($to) && !$this->shouldOverwrite($to, $projectRoot)) {
                continue;
            }

            $this->copyFile($from, $to);
            $this->info('✓ Published file ' . str_replace($projectRoot . '/', '', $to . "\n"));
        }

        // 2) Ensure .env contains Stripe keys
        $this->ensureEnvKeys($projectRoot);

        // 3) Patch config/app.php: expose Stripe routes as public paths
        $this->exposeStripePaths($projectRoot);

        // 4) Patch config/app.mlc: add stripe { … } section
        $this->addStripeConfig($projectRoot);

        // 5) Add StripeServiceProvider to composer.json
        $this->addServiceProviderToComposer($projectRoot);

        $this->line('<info>Stripe scaffolding and .env setup complete!</info>');
        return self::SUCCESS;
    }

    /**
     * Ensure the StripeServiceProvider is registered in config/app.php.
     *
     * @param string $projectRoot
     */
    private function ensureEnvKeys(string $projectRoot): void
    {
        $envFile = $projectRoot . '/.env';
        if (!file_exists($envFile)) {
            $this->warn('.env file not found; skipping Stripe key injection.');
            return;
        }

        /** @var list<string>|false $lines */
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            $this->warn('.env file is empty; skipping Stripe key injection.');
            return;
        }
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
     * Exposed stripe paths.
     *
     * @param string $projectRoot
     * @return void
     */
    private function exposeStripePaths(string $projectRoot): void
    {
        $mlcFile = "{$projectRoot}/config/app.mlc";
        if (!is_file($mlcFile)) {
            $this->warn('config/app.mlc not found; skipping public path injection.');
            return;
        }

        /** @var list<string>|false $lines */
        $lines = file($mlcFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            $this->warn('Failed to read config/app.mlc; skipping public path injection.');
            return;
        }
        $toAdd = ['/', '/stripe/*', '/docs', '/docs/*', '/success', '/cancel'];

        // 1) Find auth { … } block
        $authStart = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*auth\s*\{\s*$/', $line)) {
                $authStart = $i;
                break;
            }
        }
        if ($authStart === null) {
            $this->warn('No `auth {` section in app.mlc; cannot inject public_paths.');
            return;
        }
        // find matching closing brace
        $depth = 1;
        $authEnd = null;
        for ($i = $authStart + 1, $n = count($lines); $i < $n; $i++) {
            if (strpos($lines[$i], '{') !== false)  $depth++;
            if (strpos($lines[$i], '}') !== false)  $depth--;
            if ($depth === 0) {
                $authEnd = $i;
                break;
            }
        }
        if ($authEnd === null) {
            $this->warn('Could not find end of auth { … } block.');
            return;
        }

        // 2) Extract inner block lines
        $blockLines = array_slice($lines, $authStart + 1, $authEnd - $authStart - 1);

        // 3) Detect child-indent (default to 4 spaces)
        preg_match('/^(\s*)\S/', $blockLines[0] ?? '', $m);
        $indent = $m[1] ?? '    ';

        // 4) Locate existing public_paths array (inline or multiline)
        $exist     = [];
        $listStart = $listEnd = null;
        $inMulti   = false;

        foreach ($blockLines as $j => $raw) {
            $l = $raw;

            // inline form: public_paths = ["/a","/b"]
            if (
                $listStart === null &&
                preg_match('/^\s*public_paths\s*=\s*\[\s*(.*?)\s*\]\s*$/', $l, $m2)
            ) {
                $listStart = $j;
                $listEnd   = $j;
                preg_match_all('/"([^"]*)"/', $m2[1], $m3);
                $exist = $m3[1];
                break;
            }

            // multiline start: public_paths = [
            if (
                $listStart === null &&
                preg_match('/^\s*public_paths\s*=\s*\[\s*$/', $l)
            ) {
                $listStart = $j;
                $inMulti   = true;
                continue;
            }

            // inside multiline
            if ($inMulti) {
                if (preg_match('/^\s*\]\s*$/', $l)) {
                    $listEnd = $j;
                    break;
                }
                if (preg_match('/"([^"]*)"/', $l, $m4)) {
                    $exist[] = $m4[1];
                }
            }
        }

        // 5) Merge & dedupe
        $merged = array_values(array_unique(array_merge($exist, $toAdd)));

        // 6) Build the new block in “public_paths = [ … ]” style
        $newBlock = [];
        $newBlock[] = $indent . 'public_paths = [';
        $lastIndex  = count($merged) - 1;
        foreach ($merged as $idx => $p) {
            $comma = $idx < $lastIndex ? ',' : '';
            $newBlock[] = $indent . '  "' . $p . '"' . $comma;
        }
        $newBlock[] = $indent . ']';

        // 7) Splice into blockLines (replace or insert)
        if ($listStart !== null && $listEnd !== null) {
            array_splice($blockLines, $listStart, $listEnd - $listStart + 1, $newBlock);
        } else {
            array_splice($blockLines, 0, 0, $newBlock);
        }

        // 8) Reassemble full file
        $out = [];
        // lines up through auth {
        for ($i = 0; $i <= $authStart; $i++) {
            $out[] = $lines[$i];
        }
        // updated auth body
        foreach ($blockLines as $ln) {
            $out[] = $ln;
        }
        // closing brace
        $out[] = $lines[$authEnd];
        // remainder
        for ($i = $authEnd + 1, $n = count($lines); $i < $n; $i++) {
            $out[] = $lines[$i];
        }

        file_put_contents($mlcFile, implode("\n", $out) . "\n");
        $this->info('✓ Ensured config/app.mlc › auth.public_paths = [ … ] contains all Stripe/docs paths.');
    }

    /**
     * Make sure config/app.mlc contains a stripe { … } section
     * with endpoint_secrets, tolerance, default_ttl and test_mode.
     *
     * @param string $projectRoot
     */
    private function addStripeConfig(string $projectRoot): void
    {
        $mlcFile = "{$projectRoot}/config/app.mlc";
        if (!is_file($mlcFile)) {
            $this->warn('config/app.mlc not found; skipping stripe section injection.');
            return;
        }

        /** @var list<string>|false $lines */
        $lines = file($mlcFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            $this->warn('Failed to read config/app.mlc; skipping stripe section injection.');
            return;
        }

        // -----------------------------------------------------------------
        // 1) Find an existing `stripe {` block (track braces)
        // -----------------------------------------------------------------
        $stripeStart = null;
        $stripeEnd   = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*stripe\s*\{\s*$/', $line)) {
                $stripeStart = $i;
                // walk to matching }
                $depth = 1;
                for ($j = $i + 1, $n = count($lines); $j < $n; $j++) {
                    if (strpos($lines[$j], '{') !== false) $depth++;
                    if (strpos($lines[$j], '}') !== false) $depth--;
                    if ($depth === 0) {
                        $stripeEnd = $j;
                        break;
                    }
                }
                break;
            }
        }

        // -----------------------------------------------------------------
        // 2) Existing child-indent or default four spaces
        // -----------------------------------------------------------------
        $indent = '    ';
        if ($stripeStart !== null && $stripeStart + 1 < count($lines)) {
            if (preg_match('/^(\s+)\S/', $lines[$stripeStart + 1], $m)) {
                $indent = $m[1];
            }
        }

        // -----------------------------------------------------------------
        // 3) Build defaults and merge with any existing keys
        // -----------------------------------------------------------------
        $defaults = [
            'endpoint_secrets'     => '{ webhook_secret : "sk_live_…", webhook_secret_test : "sk_test_…" }',
            'webhook_tolerance'    => '300',
            'webhook_default_ttl'  => '172800',
            'test_mode'            => 'true',
        ];

        $existing = [];

        if ($stripeStart !== null) {
            // scan the existing block for key = value pairs
            for ($k = $stripeStart + 1; $k < $stripeEnd; $k++) {
                if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*(.+)$/', $lines[$k], $m)) {
                    $existing[$m[1]] = trim($m[2]);
                }
            }
        }

        // Final key/value list (existing overrides defaults)
        $kv = $defaults;
        foreach ($existing as $k => $v) {
            $kv[$k] = $v;
        }

        // -----------------------------------------------------------------
        // 4) Compose the block
        // -----------------------------------------------------------------
        $block = [];
        $block[] = 'stripe {';
        foreach ($kv as $k => $v) {
            $block[] = $indent . $k . ' = ' . $v;
        }
        $block[] = '}';

        // -----------------------------------------------------------------
        // 5) Splice into file
        // -----------------------------------------------------------------
        if ($stripeStart !== null && $stripeEnd !== null) {
            // replace old block
            array_splice($lines, $stripeStart, $stripeEnd - $stripeStart + 1, $block);
        } else {
            // append after auth { … } or at end of file
            $insertAt = count($lines);
            foreach ($lines as $i => $line) {
                if (preg_match('/^\s*auth\s*\{\s*$/', $line)) {
                    // jump to its closing brace
                    $d = 1;
                    for ($j = $i + 1; $j < count($lines); $j++) {
                        if (strpos($lines[$j], '{') !== false) $d++;
                        if (strpos($lines[$j], '}') !== false) $d--;
                        if ($d === 0) {
                            $insertAt = $j + 1;
                            break;
                        }
                    }
                    break;
                }
            }
            array_splice($lines, $insertAt, 0, array_merge([''], $block)); // blank line before
        }

        file_put_contents($mlcFile, implode("\n", $lines) . "\n");
        $this->info('✓ Added/merged stripe { … } section in config/app.mlc.');
    }

    /**
     * Add MonkeysLegion\Stripe\Provider\StripeServiceProvider to composer.json providers section
     *
     * @param string $projectRoot
     */
    private function addServiceProviderToComposer(string $projectRoot): void
    {
        $composerFile = "{$projectRoot}/composer.json";
        if (!is_file($composerFile)) {
            $this->warn('composer.json not found; cannot add StripeServiceProvider.');
            return;
        }

        // Read the composer.json file
        $json = file_get_contents($composerFile);
        if ($json === false) {
            $this->warn('Failed to read composer.json; cannot add StripeServiceProvider.');
            return;
        }
        $composerData = json_decode($json, true);
        if ($composerData === null || !is_array($composerData)) {
            $this->warn('Failed to parse composer.json: ' . json_last_error_msg());
            return;
        }

        // Provider to add
        $provider = 'MonkeysLegion\\Stripe\\Provider\\StripeServiceProvider';

        // Create nested structure if needed
        if (!isset($composerData['extra']) || !is_array($composerData['extra'])) {
            $composerData['extra'] = [];
        }

        if (!isset($composerData['extra']['monkeyslegion']) || !is_array($composerData['extra']['monkeyslegion'])) {
            $composerData['extra']['monkeyslegion'] = [];
        }

        if (!isset($composerData['extra']['monkeyslegion']['providers']) || !is_array($composerData['extra']['monkeyslegion']['providers'])) {
            $composerData['extra']['monkeyslegion']['providers'] = [];
        }

        // Check if the provider is already in the list
        if (in_array($provider, $composerData['extra']['monkeyslegion']['providers'])) {
            $this->info('StripeServiceProvider already registered in composer.json');
            return;
        }

        // Add the provider
        $composerData['extra']['monkeyslegion']['providers'][] = $provider;

        // Write back to composer.json with pretty formatting
        $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        file_put_contents($composerFile, json_encode($composerData, $jsonOptions) . "\n");

        $this->info('✓ Added StripeServiceProvider to composer.json › extra.monkeyslegion.providers');
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

        /** @var \SplFileInfo $item */
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
