<?php
/**
 * AI Settings Page
 *
 * Admin interface for AI Consistency Guardian configuration
 * Handles API key management, feature toggles, and cost tracking
 *
 * @package SagaManager\Admin
 * @version 1.4.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AI settings page
 *
 * @return void
 */
function saga_ai_register_settings_page(): void
{
    add_submenu_page(
        'themes.php',
        __('AI Consistency Guardian', 'saga-manager-theme'),
        __('AI Guardian', 'saga-manager-theme'),
        'manage_options',
        'saga-ai-settings',
        'saga_ai_render_settings_page'
    );
}
add_action('admin_menu', 'saga_ai_register_settings_page');

/**
 * Register settings
 *
 * @return void
 */
function saga_ai_register_settings(): void
{
    // Feature toggle
    register_setting('saga_ai_settings', 'saga_ai_consistency_enabled', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);

    // AI provider
    register_setting('saga_ai_settings', 'saga_ai_provider', [
        'type' => 'string',
        'default' => 'openai',
        'sanitize_callback' => 'saga_ai_sanitize_provider',
    ]);

    // OpenAI API key (encrypted)
    register_setting('saga_ai_settings', 'saga_ai_openai_key', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'saga_ai_encrypt_api_key',
    ]);

    // Anthropic API key (encrypted)
    register_setting('saga_ai_settings', 'saga_ai_anthropic_key', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'saga_ai_encrypt_api_key',
    ]);

    // Sensitivity level
    register_setting('saga_ai_settings', 'saga_ai_sensitivity', [
        'type' => 'string',
        'default' => 'moderate',
        'sanitize_callback' => 'saga_ai_sanitize_sensitivity',
    ]);

    // Enabled rule types
    register_setting('saga_ai_settings', 'saga_ai_enabled_rules', [
        'type' => 'array',
        'default' => ['timeline', 'character', 'location', 'relationship', 'logical'],
        'sanitize_callback' => 'saga_ai_sanitize_rules',
    ]);

    // Auto-run frequency
    register_setting('saga_ai_settings', 'saga_ai_auto_run', [
        'type' => 'string',
        'default' => 'manual',
        'sanitize_callback' => 'sanitize_key',
    ]);
}
add_action('admin_init', 'saga_ai_register_settings');

/**
 * Sanitize AI provider
 *
 * @param string $provider Provider name
 * @return string
 */
function saga_ai_sanitize_provider(string $provider): string
{
    $validProviders = ['openai', 'anthropic'];

    return in_array($provider, $validProviders, true) ? $provider : 'openai';
}

/**
 * Encrypt API key before saving
 *
 * @param string $key API key
 * @return string
 */
function saga_ai_encrypt_api_key(string $key): string
{
    if (empty($key)) {
        return '';
    }

    // Don't re-encrypt if already encrypted
    if (strpos($key, 'sk-') !== 0 && strpos($key, 'sk-ant-') !== 0) {
        return $key; // Already encrypted
    }

    return \SagaManager\AI\AIClient::encrypt($key);
}

/**
 * Sanitize sensitivity level
 *
 * @param string $sensitivity Sensitivity level
 * @return string
 */
function saga_ai_sanitize_sensitivity(string $sensitivity): string
{
    $validLevels = ['strict', 'moderate', 'permissive'];

    return in_array($sensitivity, $validLevels, true) ? $sensitivity : 'moderate';
}

/**
 * Sanitize enabled rules
 *
 * @param mixed $rules Rules array
 * @return array
 */
function saga_ai_sanitize_rules($rules): array
{
    if (!is_array($rules)) {
        return [];
    }

    $validRules = ['timeline', 'character', 'location', 'relationship', 'logical'];

    return array_intersect($rules, $validRules);
}

/**
 * Render settings page
 *
 * @return void
 */
function saga_ai_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'saga-manager-theme'));
    }

    // Get current settings
    $enabled = get_option('saga_ai_consistency_enabled', false);
    $provider = get_option('saga_ai_provider', 'openai');
    $sensitivity = get_option('saga_ai_sensitivity', 'moderate');
    $enabledRules = get_option('saga_ai_enabled_rules', []);
    $autoRun = get_option('saga_ai_auto_run', 'manual');

    // Get statistics
    $stats = saga_ai_get_usage_stats();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('AI Consistency Guardian Settings', 'saga-manager-theme'); ?></h1>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php settings_fields('saga_ai_settings'); ?>

            <table class="form-table">
                <!-- Feature Toggle -->
                <tr>
                    <th scope="row">
                        <label for="saga_ai_consistency_enabled">
                            <?php esc_html_e('Enable AI Guardian', 'saga-manager-theme'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                id="saga_ai_consistency_enabled"
                                name="saga_ai_consistency_enabled"
                                value="1"
                                <?php checked($enabled, true); ?>
                            />
                            <?php esc_html_e('Enable AI-powered consistency checking', 'saga-manager-theme'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, AI will analyze saga entities for plot holes and inconsistencies.', 'saga-manager-theme'); ?>
                        </p>
                    </td>
                </tr>

                <!-- AI Provider -->
                <tr>
                    <th scope="row">
                        <label for="saga_ai_provider">
                            <?php esc_html_e('AI Provider', 'saga-manager-theme'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="saga_ai_provider" name="saga_ai_provider">
                            <option value="openai" <?php selected($provider, 'openai'); ?>>
                                OpenAI GPT-4
                            </option>
                            <option value="anthropic" <?php selected($provider, 'anthropic'); ?>>
                                Anthropic Claude
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Primary AI provider for semantic analysis.', 'saga-manager-theme'); ?>
                        </p>
                    </td>
                </tr>

                <!-- OpenAI API Key -->
                <tr>
                    <th scope="row">
                        <label for="saga_ai_openai_key">
                            <?php esc_html_e('OpenAI API Key', 'saga-manager-theme'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="saga_ai_openai_key"
                            name="saga_ai_openai_key"
                            class="regular-text"
                            placeholder="sk-..."
                            autocomplete="off"
                        />
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: OpenAI API URL */
                                esc_html__('Get your API key from %s', 'saga-manager-theme'),
                                '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>'
                            );
                            ?>
                            <br>
                            <?php esc_html_e('API keys are encrypted before storage.', 'saga-manager-theme'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Anthropic API Key -->
                <tr>
                    <th scope="row">
                        <label for="saga_ai_anthropic_key">
                            <?php esc_html_e('Anthropic API Key', 'saga-manager-theme'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="saga_ai_anthropic_key"
                            name="saga_ai_anthropic_key"
                            class="regular-text"
                            placeholder="sk-ant-..."
                            autocomplete="off"
                        />
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: Anthropic API URL */
                                esc_html__('Get your API key from %s (fallback provider)', 'saga-manager-theme'),
                                '<a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <!-- Sensitivity Level -->
                <tr>
                    <th scope="row">
                        <label for="saga_ai_sensitivity">
                            <?php esc_html_e('Sensitivity Level', 'saga-manager-theme'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="saga_ai_sensitivity" name="saga_ai_sensitivity">
                            <option value="strict" <?php selected($sensitivity, 'strict'); ?>>
                                <?php esc_html_e('Strict - Flag all potential issues', 'saga-manager-theme'); ?>
                            </option>
                            <option value="moderate" <?php selected($sensitivity, 'moderate'); ?>>
                                <?php esc_html_e('Moderate - Balanced approach', 'saga-manager-theme'); ?>
                            </option>
                            <option value="permissive" <?php selected($sensitivity, 'permissive'); ?>>
                                <?php esc_html_e('Permissive - Only major issues', 'saga-manager-theme'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Controls how aggressively issues are detected.', 'saga-manager-theme'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Enabled Rule Types -->
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Rule Types', 'saga-manager-theme'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <?php esc_html_e('Enabled rule types', 'saga-manager-theme'); ?>
                            </legend>

                            <?php
                            $ruleTypes = [
                                'timeline' => __('Timeline Consistency', 'saga-manager-theme'),
                                'character' => __('Character Contradictions', 'saga-manager-theme'),
                                'location' => __('Location Logic', 'saga-manager-theme'),
                                'relationship' => __('Relationship Validation', 'saga-manager-theme'),
                                'logical' => __('Logical Errors', 'saga-manager-theme'),
                            ];

                            foreach ($ruleTypes as $key => $label):
                            ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="saga_ai_enabled_rules[]"
                                        value="<?php echo esc_attr($key); ?>"
                                        <?php checked(in_array($key, $enabledRules, true)); ?>
                                    />
                                    <?php echo esc_html($label); ?>
                                </label>
                                <br>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e('Select which rule types to check.', 'saga-manager-theme'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Auto-run Frequency -->
                <tr>
                    <th scope="row">
                        <label for="saga_ai_auto_run">
                            <?php esc_html_e('Auto-run Checks', 'saga-manager-theme'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="saga_ai_auto_run" name="saga_ai_auto_run">
                            <option value="manual" <?php selected($autoRun, 'manual'); ?>>
                                <?php esc_html_e('Manual only', 'saga-manager-theme'); ?>
                            </option>
                            <option value="daily" <?php selected($autoRun, 'daily'); ?>>
                                <?php esc_html_e('Daily', 'saga-manager-theme'); ?>
                            </option>
                            <option value="weekly" <?php selected($autoRun, 'weekly'); ?>>
                                <?php esc_html_e('Weekly', 'saga-manager-theme'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Automatically run consistency checks on a schedule.', 'saga-manager-theme'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <!-- Usage Statistics -->
        <hr>
        <h2><?php esc_html_e('Usage Statistics', 'saga-manager-theme'); ?></h2>

        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Metric', 'saga-manager-theme'); ?></th>
                    <th><?php esc_html_e('Value', 'saga-manager-theme'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e('AI Checks This Hour', 'saga-manager-theme'); ?></td>
                    <td><?php echo esc_html($stats['checks_this_hour']); ?> / 10</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Total Issues Detected', 'saga-manager-theme'); ?></td>
                    <td><?php echo esc_html($stats['total_issues']); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Open Issues', 'saga-manager-theme'); ?></td>
                    <td><?php echo esc_html($stats['open_issues']); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Resolved Issues', 'saga-manager-theme'); ?></td>
                    <td><?php echo esc_html($stats['resolved_issues']); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Average AI Confidence', 'saga-manager-theme'); ?></td>
                    <td><?php echo esc_html(number_format($stats['avg_confidence'] * 100, 1)); ?>%</td>
                </tr>
            </tbody>
        </table>

        <!-- Test Connection Button -->
        <p>
            <button type="button" class="button" id="saga-ai-test-connection">
                <?php esc_html_e('Test AI Connection', 'saga-manager-theme'); ?>
            </button>
            <span id="saga-ai-test-result"></span>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#saga-ai-test-connection').on('click', function() {
            var $button = $(this);
            var $result = $('#saga-ai-test-result');

            $button.prop('disabled', true);
            $result.html('<span class="spinner is-active"></span>');

            $.post(ajaxurl, {
                action: 'saga_ai_test_connection',
                nonce: '<?php echo esc_js(wp_create_nonce('saga_ai_test')); ?>'
            }, function(response) {
                $button.prop('disabled', false);

                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Get usage statistics
 *
 * @return array
 */
function saga_ai_get_usage_stats(): array
{
    global $wpdb;

    $tableName = $wpdb->prefix . 'saga_consistency_issues';

    // Get current hour rate limit
    $userId = get_current_user_id();
    $checksThisHour = (int) get_transient("saga_ai_rate_limit_{$userId}") ?: 0;

    // Get issue statistics
    $stats = $wpdb->get_row("
        SELECT
            COUNT(*) as total_issues,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_issues,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_issues,
            AVG(COALESCE(ai_confidence, 0)) as avg_confidence
        FROM {$tableName}
    ", ARRAY_A);

    if ($stats === null) {
        return [
            'checks_this_hour' => $checksThisHour,
            'total_issues' => 0,
            'open_issues' => 0,
            'resolved_issues' => 0,
            'avg_confidence' => 0.0,
        ];
    }

    return [
        'checks_this_hour' => $checksThisHour,
        'total_issues' => (int) $stats['total_issues'],
        'open_issues' => (int) $stats['open_issues'],
        'resolved_issues' => (int) $stats['resolved_issues'],
        'avg_confidence' => (float) $stats['avg_confidence'],
    ];
}

/**
 * AJAX handler for testing AI connection
 *
 * @return void
 */
function saga_ai_test_connection_ajax(): void
{
    check_ajax_referer('saga_ai_test', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error([
            'message' => __('Insufficient permissions', 'saga-manager-theme'),
        ]);
    }

    try {
        $aiClient = new \SagaManager\AI\AIClient();

        // Simple test with minimal context
        $testContext = [
            'entities' => [
                ['name' => 'Test Character', 'type' => 'character', 'description' => 'Test'],
            ],
            'relationships' => [],
            'timeline' => [],
        ];

        $issues = $aiClient->analyzeConsistency(1, $testContext);

        wp_send_json_success([
            'message' => __('AI connection successful!', 'saga-manager-theme'),
            'issues_found' => count($issues),
        ]);
    } catch (\Exception $e) {
        wp_send_json_error([
            'message' => __('Connection failed: ', 'saga-manager-theme') . $e->getMessage(),
        ]);
    }
}
add_action('wp_ajax_saga_ai_test_connection', 'saga_ai_test_connection_ajax');
