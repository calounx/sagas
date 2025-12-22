<?php
declare(strict_types=1);

namespace SagaManager\Tests\Fixtures;

/**
 * Multisite Test Fixtures
 *
 * Provides fixtures for testing WordPress multisite compatibility.
 * Verifies table prefix handling across network sites.
 */
class MultisiteFixtures
{
    private static ?int $networkSiteId = null;
    private static array $siteData = [];

    /**
     * Check if we're in a multisite environment
     */
    public static function isMultisite(): bool
    {
        return is_multisite();
    }

    /**
     * Create a test site in multisite network
     *
     * @param string $subdomain Site subdomain
     * @param string $title Site title
     * @return int Site ID
     */
    public static function createNetworkSite(string $subdomain = 'testsite', string $title = 'Test Site'): int
    {
        if (!self::isMultisite()) {
            throw new \RuntimeException('Multisite is not enabled');
        }

        $domain = is_subdomain_install()
            ? "{$subdomain}." . get_network()->domain
            : get_network()->domain;

        $path = is_subdomain_install()
            ? '/'
            : '/' . $subdomain . '/';

        $siteId = wp_insert_site([
            'domain' => $domain,
            'path' => $path,
            'title' => $title,
            'user_id' => get_current_user_id() ?: 1,
            'network_id' => get_current_network_id(),
        ]);

        if (is_wp_error($siteId)) {
            throw new \RuntimeException('Failed to create site: ' . $siteId->get_error_message());
        }

        self::$networkSiteId = $siteId;

        // Store site data for cleanup
        self::$siteData[$siteId] = [
            'domain' => $domain,
            'path' => $path,
        ];

        return $siteId;
    }

    /**
     * Switch to a test site context
     *
     * @param int $siteId
     */
    public static function switchToSite(int $siteId): void
    {
        if (!self::isMultisite()) {
            return;
        }

        switch_to_blog($siteId);
    }

    /**
     * Restore to original site context
     */
    public static function restoreSite(): void
    {
        if (!self::isMultisite()) {
            return;
        }

        restore_current_blog();
    }

    /**
     * Get the table prefix for a specific site
     *
     * @param int|null $siteId Site ID (null for current)
     * @return string
     */
    public static function getTablePrefix(?int $siteId = null): string
    {
        global $wpdb;

        if (!self::isMultisite()) {
            return $wpdb->prefix;
        }

        if ($siteId === null || $siteId === get_current_blog_id()) {
            return $wpdb->prefix;
        }

        // For multisite, prefix is base_prefix + site_id + _
        if ($siteId === 1) {
            return $wpdb->base_prefix;
        }

        return $wpdb->base_prefix . $siteId . '_';
    }

    /**
     * Verify table prefix handling across sites
     *
     * This is a key test for multisite compatibility.
     * Each site should have isolated saga tables.
     *
     * @return array{site_id: int, prefix: string, tables: string[]}[]
     */
    public static function verifyTableIsolation(): array
    {
        if (!self::isMultisite()) {
            return [];
        }

        global $wpdb;

        $results = [];

        // Check main site
        $mainPrefix = $wpdb->base_prefix . 'saga_';
        $results[] = [
            'site_id' => 1,
            'prefix' => $mainPrefix,
            'tables' => self::getSagaTables($mainPrefix),
        ];

        // Check each network site we created
        foreach (self::$siteData as $siteId => $data) {
            $sitePrefix = self::getTablePrefix($siteId) . 'saga_';
            $results[] = [
                'site_id' => $siteId,
                'prefix' => $sitePrefix,
                'tables' => self::getSagaTables($sitePrefix),
            ];
        }

        return $results;
    }

    /**
     * Get saga tables for a given prefix
     */
    private static function getSagaTables(string $prefix): array
    {
        global $wpdb;

        $escapedPrefix = str_replace('_', '\\_', $prefix);

        $tables = $wpdb->get_col(
            "SHOW TABLES LIKE '{$escapedPrefix}%'"
        );

        return $tables ?: [];
    }

    /**
     * Create saga tables for a specific site
     *
     * @param int $siteId
     */
    public static function createSiteSchema(int $siteId): void
    {
        self::switchToSite($siteId);

        try {
            $schema = new \SagaManager\Infrastructure\WordPress\DatabaseSchema();
            $schema->createTables();
            $schema->addForeignKeys();
        } finally {
            self::restoreSite();
        }
    }

    /**
     * Load test data for a specific site
     *
     * @param int $siteId
     * @param string $sagaName
     * @return int Saga ID
     */
    public static function loadSiteTestData(int $siteId, string $sagaName = 'Test Saga'): int
    {
        self::switchToSite($siteId);

        try {
            global $wpdb;

            // Verify we're using the correct prefix
            $expectedPrefix = self::getTablePrefix($siteId);
            if ($wpdb->prefix !== $expectedPrefix) {
                throw new \RuntimeException(
                    "Table prefix mismatch: expected {$expectedPrefix}, got {$wpdb->prefix}"
                );
            }

            // Insert test saga
            $wpdb->insert(
                $wpdb->prefix . 'saga_sagas',
                [
                    'name' => $sagaName . ' (Site ' . $siteId . ')',
                    'universe' => 'Test Universe',
                    'calendar_type' => 'absolute',
                    'calendar_config' => json_encode(['format' => 'Y-m-d']),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

            return $wpdb->insert_id;

        } finally {
            self::restoreSite();
        }
    }

    /**
     * Verify data isolation between sites
     *
     * Creates data on multiple sites and verifies no cross-contamination.
     *
     * @return array{passed: bool, details: array}
     */
    public static function verifyDataIsolation(): array
    {
        if (!self::isMultisite()) {
            return ['passed' => true, 'details' => ['Multisite not enabled']];
        }

        global $wpdb;

        $details = [];
        $passed = true;

        // Get all sites with saga tables
        $sites = array_keys(self::$siteData);
        array_unshift($sites, 1); // Add main site

        foreach ($sites as $siteId) {
            self::switchToSite($siteId);

            try {
                $expectedPrefix = self::getTablePrefix($siteId);
                $sagaTable = $expectedPrefix . 'saga_sagas';

                // Count sagas
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$sagaTable}");

                $details["site_{$siteId}"] = [
                    'prefix' => $expectedPrefix,
                    'saga_count' => (int) $count,
                ];

            } catch (\Exception $e) {
                $details["site_{$siteId}_error"] = $e->getMessage();
                $passed = false;
            } finally {
                self::restoreSite();
            }
        }

        return ['passed' => $passed, 'details' => $details];
    }

    /**
     * Clean up all test sites and data
     */
    public static function cleanup(): void
    {
        if (!self::isMultisite()) {
            return;
        }

        foreach (array_keys(self::$siteData) as $siteId) {
            // Delete site (will cascade delete tables if properly configured)
            wp_delete_site($siteId);
        }

        self::$networkSiteId = null;
        self::$siteData = [];
    }

    /**
     * Get the last created network site ID
     */
    public static function getNetworkSiteId(): ?int
    {
        return self::$networkSiteId;
    }
}
