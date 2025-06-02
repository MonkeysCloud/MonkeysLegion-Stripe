<?php

define('KEY_FILE', getcwd() . '/.env');

function generateKey(int $length = 32): string
{
    return bin2hex(random_bytes($length)); // 64 hex chars (32 bytes)
}

function saveKey(string $key, string $keyName = 'APP_KEY'): void
{
    $dir = dirname(KEY_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);  // Create directory recursively if not exists
    }

    // Read existing keys from file
    $env = [];
    if (file_exists(KEY_FILE)) {
        $lines = file(KEY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // skip comments
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $env[trim($k)] = trim($v);
            }
        }
    }

    // Update or add the key
    $env[$keyName] = $key;

    // Write back all keys
    $content = '';
    foreach ($env as $k => $v) {
        $content .= "$k=$v\n";
    }
    file_put_contents(KEY_FILE, $content);

    echo "Key saved to " . KEY_FILE . PHP_EOL;
}

function readKey(string $keyName = 'APP_KEY'): ?string
{
    if (!file_exists(KEY_FILE)) {
        return null;
    }
    $lines = file(KEY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            if (trim($k) === $keyName) {
                return trim($v);
            }
        }
    }
    return null;
}

function validateKey(string $key): bool
{
    // Example validation: must be hex string of 64 chars
    return preg_match('/^[a-f0-9]{64}$/', $key) === 1;
}

function printUsage()
{
    echo "Usage:" . PHP_EOL;
    echo "  key-helper.php generate [KEY_NAME]   # Generate and save a new key (default: APP_KEY)" . PHP_EOL;
    echo "  key-helper.php rotate [KEY_NAME]     # Rotate (replace) the key" . PHP_EOL;
    echo "  key-helper.php validate [KEY_NAME]   # Validate the existing key" . PHP_EOL;
    echo "  key-helper.php show [KEY_NAME]       # Show the current key" . PHP_EOL;
}

// Allowed key names and their aliases
const ALLOWED_KEYS = [
    'secret'      => 'STRIPE_SECRET_KEY',
    'publishable' => 'STRIPE_PUBLISHABLE_KEY',
    'webhook'     => 'STRIPE_WEBHOOK_SECRET'
];

if ($argc < 2) {
    printUsage();
    exit(1);
}

$command = strtolower($argv[1]);
$keyAlias = isset($argv[2]) ? strtolower($argv[2]) : null;
$keyName = null;

// Validate key alias for commands that require it
if (in_array($command, ['generate', 'rotate', 'validate', 'show'])) {
    if ($keyAlias === null) {
        echo "You must specify a key type: secret, publishable, or webhook.\n";
        printUsage();
        exit(1);
    }
    if (!array_key_exists($keyAlias, ALLOWED_KEYS)) {
        echo "Invalid key type: $keyAlias\n";
        echo "Allowed types: secret, publishable, webhook\n";
        exit(1);
    }
    $keyName = ALLOWED_KEYS[$keyAlias];
}

switch ($command) {
    case 'generate':
        $key = generateKey();
        saveKey($key, $keyName);
        echo "Generated key ($keyName): $key" . PHP_EOL;
        break;

    case 'rotate':
        echo "Rotating key $keyName..." . PHP_EOL;
        $key = generateKey();
        saveKey($key, $keyName);
        echo "New key ($keyName): $key" . PHP_EOL;
        break;

    case 'validate':
        $key = readKey($keyName);
        if ($key === null) {
            echo "No key named '$keyName' found to validate." . PHP_EOL;
            exit(1);
        }
        if (validateKey($key)) {
            echo "Key '$keyName' is valid." . PHP_EOL;
        } else {
            echo "Key '$keyName' is invalid!" . PHP_EOL;
            exit(1);
        }
        break;

    case 'show':
        $key = readKey($keyName);
        if ($key === null) {
            echo "No key named '$keyName' found." . PHP_EOL;
            exit(1);
        }
        echo "Current key ($keyName): $key" . PHP_EOL;
        break;

    default:
        echo "Unknown command: $command" . PHP_EOL;
        printUsage();
        exit(1);
}
