<?php

declare(strict_types=1);

if (!function_exists('dd')) {
    function dd(...$args): void
    {
        foreach ($args as $arg) {
            if (is_array($arg) || is_object($arg)) {
                echo '<pre>' . print_r($arg, true) . '</pre>';
            } else {
                echo htmlspecialchars((string)$arg, ENT_QUOTES, 'UTF-8');
            }
        }
        exit(1);
    }
}
