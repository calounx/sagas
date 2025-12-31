<?php
declare(strict_types=1);

namespace SagaTheme;

/**
 * PSR-4 Autoloader for Saga Manager Theme
 *
 * Maps SagaTheme namespace to inc/ directory
 * Follows PSR-4 autoloading standard
 */
spl_autoload_register(function (string $class): void {
    // Only autoload SagaTheme namespace classes
    $prefix = 'SagaTheme\\';
    $baseDir = __DIR__ . '/';

    // Check if class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // Replace namespace separators with directory separators
    // Convert to lowercase and add 'class-' prefix per WordPress conventions
    $file = $baseDir . 'class-' . str_replace('\\', '/', strtolower($relativeClass)) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
    }
});
