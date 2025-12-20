# Data Migration Strategy: Single Plugin to Split Architecture

## Overview

This document outlines the migration path from a single Saga Manager plugin to the split backend/frontend architecture.

## Migration Phases

### Phase 1: Preparation (Pre-Migration)

1. **Backup Everything**
   ```bash
   # Database backup
   mysqldump -u root -p wordpress > backup_pre_migration.sql

   # Plugin files backup
   zip -r saga-manager-backup.zip wp-content/plugins/saga-manager/
   ```

2. **Audit Current Installation**
   - Document all shortcodes in use
   - List customized templates
   - Note any action/filter hooks used
   - Export current settings

3. **Version Check**
   ```php
   // Add to current plugin
   function saga_check_migration_readiness() {
       global $wpdb;

       $checks = [
           'php_version' => version_compare(PHP_VERSION, '8.2', '>='),
           'wp_version' => version_compare(get_bloginfo('version'), '6.0', '>='),
           'table_prefix' => !empty($wpdb->prefix),
           'entity_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}saga_entities"),
       ];

       return $checks;
   }
   ```

### Phase 2: Install Backend Plugin

1. **Install saga-manager-core**
   - Upload and activate
   - Tables are created with same schema (no data loss)
   - Existing tables are preserved via dbDelta

2. **Verify Table Structure**
   ```sql
   -- Check tables exist with correct prefix
   SHOW TABLES LIKE 'wp_saga_%';

   -- Verify no data loss
   SELECT COUNT(*) FROM wp_saga_entities;
   SELECT COUNT(*) FROM wp_saga_relationships;
   ```

3. **Migrate Settings**
   ```php
   // Run once after backend activation
   function saga_migrate_settings() {
       $old_settings = get_option('saga_manager_settings');

       if ($old_settings) {
           // Map old settings to new
           update_option('saga_embedding_api_url', $old_settings['embedding_url'] ?? '');
           update_option('saga_cache_ttl', $old_settings['cache_ttl'] ?? 300);
           // ... more mappings
       }
   }
   ```

### Phase 3: Install Frontend Plugin

1. **Install saga-manager-display**
   - Requires backend to be active
   - No database tables created

2. **Verify API Connection**
   ```php
   // Frontend plugin checks backend API
   $api = new SagaApiClient(SagaManagerCore::getApiUrl());

   if (!$api->isAvailable()) {
       add_action('admin_notices', function() {
           echo '<div class="error"><p>Saga Manager: API connection failed.</p></div>';
       });
   }
   ```

### Phase 4: Update Shortcodes and Templates

**Shortcode Compatibility Layer**

The frontend plugin maintains backward-compatible shortcode names:

| Old Shortcode | New Shortcode | Changes |
|--------------|---------------|---------|
| `[saga_entity]` | `[saga_entity]` | None |
| `[saga_list]` | `[saga_entities]` | Renamed, aliased |
| `[saga_timeline]` | `[saga_timeline]` | None |
| `[saga_search]` | `[saga_search]` | None |

```php
// Backward compatibility aliases
add_shortcode('saga_list', function($atts, $content) {
    return do_shortcode('[saga_entities ' .
        implode(' ', array_map(
            fn($k, $v) => "{$k}=\"{$v}\"",
            array_keys($atts),
            $atts
        )) . ']' . $content . '[/saga_entities]'
    );
});
```

**Template Migration**

```
Old Location:                          New Location:
saga-manager/templates/                saga-manager-display/templates/
├── entity-single.php          →       ├── shortcode/entity-single.php
├── entity-list.php            →       ├── shortcode/entity-list.php
└── timeline.php               →       └── shortcode/timeline.php
```

Theme overrides remain in:
```
themes/your-theme/saga-manager/
```

### Phase 5: Deactivate Old Plugin

1. **Deactivate old single plugin**
   - Do NOT delete yet
   - Keep for rollback option

2. **Verify functionality**
   - Test all shortcodes
   - Check admin pages work
   - Verify API responses

3. **Clean up (after 30 days)**
   ```bash
   # Remove old plugin files
   rm -rf wp-content/plugins/saga-manager/

   # Clean up old options
   DELETE FROM wp_options WHERE option_name LIKE 'saga_manager_%';
   ```

## Migration Script

```php
<?php
/**
 * One-time migration script
 * Run via WP-CLI: wp eval-file migrate-saga.php
 */

namespace SagaMigration;

class Migrator
{
    public function run(): void
    {
        $this->log('Starting Saga Manager migration...');

        // Step 1: Check prerequisites
        if (!$this->checkPrerequisites()) {
            $this->log('Prerequisites not met. Aborting.');
            return;
        }

        // Step 2: Migrate settings
        $this->migrateSettings();

        // Step 3: Migrate any CPT data to saga tables (if applicable)
        $this->migrateCPTData();

        // Step 4: Update shortcode references
        $this->updateShortcodes();

        // Step 5: Clear caches
        $this->clearCaches();

        $this->log('Migration complete!');
    }

    private function checkPrerequisites(): bool
    {
        // Check backend plugin active
        if (!class_exists('\SagaManagerCore\SagaManagerCore')) {
            $this->log('ERROR: saga-manager-core not active');
            return false;
        }

        // Check frontend plugin active
        if (!class_exists('\SagaManagerDisplay\SagaManagerDisplay')) {
            $this->log('ERROR: saga-manager-display not active');
            return false;
        }

        return true;
    }

    private function migrateSettings(): void
    {
        $this->log('Migrating settings...');

        $mappings = [
            // old_option => new_option
            'saga_manager_embedding_url' => 'saga_embedding_api_url',
            'saga_manager_cache_ttl' => 'saga_cache_ttl',
            'saga_manager_per_page' => 'saga_default_per_page',
        ];

        foreach ($mappings as $old => $new) {
            $value = get_option($old);
            if ($value !== false) {
                update_option($new, $value);
                $this->log("  Migrated: {$old} -> {$new}");
            }
        }
    }

    private function migrateCPTData(): void
    {
        global $wpdb;

        $this->log('Checking for CPT data to migrate...');

        // If you had CPT entities, migrate them
        $cpt_posts = $wpdb->get_results(
            "SELECT ID, post_title, post_name, post_content
             FROM {$wpdb->posts}
             WHERE post_type = 'saga_entity'
             AND post_status = 'publish'"
        );

        if (empty($cpt_posts)) {
            $this->log('  No CPT data found.');
            return;
        }

        $this->log("  Found " . count($cpt_posts) . " CPT entities to migrate.");

        foreach ($cpt_posts as $post) {
            // Check if already exists in saga_entities
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saga_entities WHERE slug = %s",
                $post->post_name
            ));

            if (!$exists) {
                // Insert into saga_entities
                // Note: You'd need to extract saga_id, entity_type from post meta
                $this->log("  Would migrate: {$post->post_title}");
            }
        }
    }

    private function updateShortcodes(): void
    {
        global $wpdb;

        $this->log('Updating shortcode references...');

        $replacements = [
            '[saga_list' => '[saga_entities',
            '[/saga_list]' => '[/saga_entities]',
        ];

        foreach ($replacements as $old => $new) {
            $count = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->posts}
                 SET post_content = REPLACE(post_content, %s, %s)
                 WHERE post_content LIKE %s",
                $old,
                $new,
                '%' . $wpdb->esc_like($old) . '%'
            ));

            if ($count > 0) {
                $this->log("  Replaced '{$old}' in {$count} posts");
            }
        }
    }

    private function clearCaches(): void
    {
        $this->log('Clearing caches...');

        wp_cache_flush();

        // Clear known cache plugins
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
        error_log("[SAGA_MIGRATION] {$message}");
    }
}

// Run migration
$migrator = new Migrator();
$migrator->run();
```

## Rollback Plan

If issues occur, rollback is straightforward:

1. **Deactivate new plugins**
   ```bash
   wp plugin deactivate saga-manager-core saga-manager-display
   ```

2. **Reactivate old plugin**
   ```bash
   wp plugin activate saga-manager
   ```

3. **Restore settings if needed**
   ```sql
   -- Settings were preserved, no action needed
   -- Tables were never modified, data intact
   ```

## Post-Migration Checklist

- [ ] All shortcodes render correctly
- [ ] Admin pages load without errors
- [ ] Entity CRUD operations work
- [ ] Relationships display correctly
- [ ] Timeline renders properly
- [ ] Search functionality works
- [ ] Cache warming complete
- [ ] Performance baseline established
- [ ] Error logs checked
- [ ] Old plugin removed (after 30 days)

## Version Compatibility Matrix

| Core Version | Display Version | Compatible |
|-------------|-----------------|------------|
| 1.0.x       | 1.0.x           | Yes        |
| 1.1.x       | 1.0.x           | Yes        |
| 1.1.x       | 1.1.x           | Yes        |
| 2.0.x       | 1.x.x           | No*        |

*Major version bumps may require migration steps
