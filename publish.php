<?php

$source = __DIR__ . '/config/stripe.php';

$projectRoot = getcwd();
$destination = $projectRoot . '/config/stripe.php';

if (!file_exists($source)) {
    echo "❌ Source config file does not exist: $source\n";
    exit(1);
}

if (!is_dir(dirname($destination))) {
    mkdir(dirname($destination), 0755, true);
}

if (file_exists($destination)) {
    echo "⚠️  Config file already exists at: $destination\n";
    echo "Do you want to overwrite it? (y/N): ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'y') {
        echo "⏭️  Skipped.\n";
        exit(0);
    }
}

if (copy($source, $destination)) {
    echo "✅ Published config file to: $destination\n";
} else {
    echo "❌ Failed to publish config.\n";
}
