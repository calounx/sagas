<?php
declare(strict_types=1);

namespace SagaTheme;

/**
 * PSR-4 Autoloader for Saga Manager Theme
 *
 * Maps namespaces to directories:
 * - SagaTheme\  → inc/
 * - SagaManager\ → inc/
 * Follows PSR-4 autoloading standard
 */
spl_autoload_register(
	function ( string $class ): void {
		$baseDir = __DIR__ . '/';

		// Handle SagaManager namespace (inc/ai/, inc/ajax/, etc.)
		$managerPrefix = 'SagaManager\\';
		if ( strncmp( $managerPrefix, $class, strlen( $managerPrefix ) ) === 0 ) {
			$relativeClass = substr( $class, strlen( $managerPrefix ) );
			// Convert namespace to path: SagaManager\AI\Interfaces\Foo → inc/ai/interfaces/Foo.php
			// Use lowercase for directory names, preserve case for filenames
			$parts = explode( '\\', $relativeClass );
			$filename = array_pop( $parts ); // Preserve class name case
			$path = strtolower( implode( '/', $parts ) ); // Lowercase directories
			$file = $baseDir . $path . ( $path ? '/' : '' ) . $filename . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
			return;
		}

		// Handle SagaTheme namespace (legacy WordPress-style naming)
		$themePrefix = 'SagaTheme\\';
		if ( strncmp( $themePrefix, $class, strlen( $themePrefix ) ) === 0 ) {
			$relativeClass = substr( $class, strlen( $themePrefix ) );
			// WordPress convention: 'class-' prefix and lowercase
			$file = $baseDir . 'class-' . str_replace( '\\', '/', strtolower( $relativeClass ) ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
);
