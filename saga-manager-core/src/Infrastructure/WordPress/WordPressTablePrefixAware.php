<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\WordPress;

/**
 * WordPress Table Prefix Aware Base Class
 *
 * Provides WordPress table prefix handling for all infrastructure components
 */
abstract class WordPressTablePrefixAware
{
    protected string $prefix;
    protected \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'saga_';
    }

    /**
     * Get the full table name with WordPress and saga prefixes
     */
    protected function getTableName(string $table): string
    {
        return $this->prefix . $table;
    }

    /**
     * Get the WordPress database object
     */
    protected function getWpdb(): \wpdb
    {
        return $this->wpdb;
    }
}
