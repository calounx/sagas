<?php
declare(strict_types=1);

/**
 * Settings Page View
 *
 * @var SagaManager\Infrastructure\Repository\MariaDBEntityRepository $entityRepository
 */

if (!defined('ABSPATH')) {
    exit;
}

// Display admin notices
$notices = get_transient('saga_admin_notices') ?: [];
delete_transient('saga_admin_notices');

// Handle settings save
if (isset($_POST['saga_save_settings']) && wp_verify_nonce($_POST['_saga_settings_nonce'] ?? '', 'saga_save_settings')) {
    // Save settings
    update_option('saga_manager_items_per_page', absint($_POST['items_per_page'] ?? 20));
    update_option('saga_manager_cache_ttl', absint($_POST['cache_ttl'] ?? 300));
    update_option('saga_manager_enable_api', isset($_POST['enable_api']) ? '1' : '0');
    update_option('saga_manager_api_rate_limit', absint($_POST['api_rate_limit'] ?? 100));

    $notices[] = ['message' => __('Settings saved successfully.', 'saga-manager'), 'type' => 'success'];
}

// Get current settings
$itemsPerPage = (int) get_option('saga_manager_items_per_page', 20);
$cacheTtl = (int) get_option('saga_manager_cache_ttl', 300);
$enableApi = get_option('saga_manager_enable_api', '1') === '1';
$apiRateLimit = (int) get_option('saga_manager_api_rate_limit', 100);

// Get database statistics
global $wpdb;

$tableStats = [];
$sagaTables = [
    'saga_sagas' => __('Sagas', 'saga-manager'),
    'saga_entities' => __('Entities', 'saga-manager'),
    'saga_attribute_definitions' => __('Attribute Definitions', 'saga-manager'),
    'saga_attribute_values' => __('Attribute Values', 'saga-manager'),
    'saga_entity_relationships' => __('Relationships', 'saga-manager'),
    'saga_timeline_events' => __('Timeline Events', 'saga-manager'),
    'saga_content_fragments' => __('Content Fragments', 'saga-manager'),
    'saga_quality_metrics' => __('Quality Metrics', 'saga-manager'),
];

foreach ($sagaTables as $table => $label) {
    $tableName = $wpdb->prefix . $table;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$tableName}");
    if ($count !== null) {
        $tableStats[$table] = [
            'label' => $label,
            'count' => (int) $count,
        ];
    }
}
?>

<div class="wrap saga-manager-settings">
    <h1><?php esc_html_e('Saga Manager Settings', 'saga-manager'); ?></h1>

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <div class="saga-settings-container">
        <div class="saga-settings-main">
            <form method="post" action="">
                <?php wp_nonce_field('saga_save_settings', '_saga_settings_nonce'); ?>

                <h2 class="title"><?php esc_html_e('General Settings', 'saga-manager'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="items_per_page"><?php esc_html_e('Items Per Page', 'saga-manager'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="items_per_page" id="items_per_page" class="small-text"
                                   value="<?php echo esc_attr($itemsPerPage); ?>" min="5" max="100" />
                            <p class="description">
                                <?php esc_html_e('Number of items to display per page in list views.', 'saga-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cache_ttl"><?php esc_html_e('Cache TTL (seconds)', 'saga-manager'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="cache_ttl" id="cache_ttl" class="small-text"
                                   value="<?php echo esc_attr($cacheTtl); ?>" min="0" max="86400" />
                            <p class="description">
                                <?php esc_html_e('How long to cache entity data. Set to 0 to disable caching.', 'saga-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('API Settings', 'saga-manager'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="enable_api"><?php esc_html_e('Enable REST API', 'saga-manager'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_api" id="enable_api" value="1"
                                       <?php checked($enableApi); ?> />
                                <?php esc_html_e('Enable the Saga Manager REST API endpoints', 'saga-manager'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, external applications can access saga data via the REST API.', 'saga-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_rate_limit"><?php esc_html_e('API Rate Limit', 'saga-manager'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="api_rate_limit" id="api_rate_limit" class="small-text"
                                   value="<?php echo esc_attr($apiRateLimit); ?>" min="10" max="1000" />
                            <span><?php esc_html_e('requests per minute per user', 'saga-manager'); ?></span>
                            <p class="description">
                                <?php esc_html_e('Maximum number of API requests allowed per minute per user.', 'saga-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('API Endpoints', 'saga-manager'); ?></h2>
                <div class="saga-api-endpoints">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Endpoint', 'saga-manager'); ?></th>
                                <th><?php esc_html_e('Method', 'saga-manager'); ?></th>
                                <th><?php esc_html_e('Description', 'saga-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>/wp-json/saga/v1/entities</code></td>
                                <td>GET</td>
                                <td><?php esc_html_e('List all entities', 'saga-manager'); ?></td>
                            </tr>
                            <tr>
                                <td><code>/wp-json/saga/v1/entities/{id}</code></td>
                                <td>GET</td>
                                <td><?php esc_html_e('Get a single entity', 'saga-manager'); ?></td>
                            </tr>
                            <tr>
                                <td><code>/wp-json/saga/v1/entities</code></td>
                                <td>POST</td>
                                <td><?php esc_html_e('Create a new entity', 'saga-manager'); ?></td>
                            </tr>
                            <tr>
                                <td><code>/wp-json/saga/v1/entities/{id}</code></td>
                                <td>PUT</td>
                                <td><?php esc_html_e('Update an entity', 'saga-manager'); ?></td>
                            </tr>
                            <tr>
                                <td><code>/wp-json/saga/v1/entities/{id}</code></td>
                                <td>DELETE</td>
                                <td><?php esc_html_e('Delete an entity', 'saga-manager'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" name="saga_save_settings" class="button button-primary">
                        <?php esc_html_e('Save Settings', 'saga-manager'); ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="saga-settings-sidebar">
            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Database Statistics', 'saga-manager'); ?></h2>
                <div class="inside">
                    <table class="widefat">
                        <tbody>
                            <?php foreach ($tableStats as $table => $stat): ?>
                                <tr>
                                    <td><?php echo esc_html($stat['label']); ?></td>
                                    <td class="saga-stat-value"><?php echo number_format_i18n($stat['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('System Information', 'saga-manager'); ?></h2>
                <div class="inside">
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><?php esc_html_e('Plugin Version', 'saga-manager'); ?></td>
                                <td><?php echo esc_html(SAGA_MANAGER_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('PHP Version', 'saga-manager'); ?></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('WordPress Version', 'saga-manager'); ?></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Database Version', 'saga-manager'); ?></td>
                                <td><?php echo esc_html($wpdb->db_version()); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Table Prefix', 'saga-manager'); ?></td>
                                <td><code><?php echo esc_html($wpdb->prefix); ?>saga_*</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Maintenance', 'saga-manager'); ?></h2>
                <div class="inside">
                    <p>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=saga-manager-settings&action=clear_cache'), 'saga_clear_cache')); ?>" class="button">
                            <?php esc_html_e('Clear Cache', 'saga-manager'); ?>
                        </a>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Clear all cached saga data.', 'saga-manager'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.saga-settings-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}
.saga-settings-main {
    flex: 1;
    max-width: 800px;
}
.saga-settings-sidebar {
    width: 300px;
}
.saga-settings-sidebar .postbox {
    margin-bottom: 20px;
}
.saga-settings-sidebar .inside {
    padding: 0;
}
.saga-settings-sidebar table {
    margin: 0;
}
.saga-stat-value {
    text-align: right;
    font-weight: bold;
}
.saga-api-endpoints {
    margin-bottom: 20px;
}
.saga-api-endpoints code {
    background: #f0f0f1;
    padding: 2px 6px;
}
</style>
