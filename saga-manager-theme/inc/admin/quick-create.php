<?php
declare(strict_types=1);

namespace SagaManager\Admin;

/**
 * Entity Quick Create Admin Bar Integration
 *
 * Provides rapid entity creation through admin bar shortcut and modal interface.
 * Includes keyboard shortcuts, AJAX submission, and localStorage autosave.
 *
 * @package SagaManager
 * @since 1.3.0
 */
class QuickCreate
{
    private const NONCE_ACTION = 'saga_quick_create';
    private const NONCE_NAME = 'saga_quick_create_nonce';
    private const CAPABILITY = 'edit_posts';

    /**
     * Initialize quick create system
     */
    public function init(): void
    {
        // Admin bar integration
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_saga_quick_create', [$this, 'handle_ajax_create']);
        add_action('wp_ajax_saga_get_entity_templates', [$this, 'handle_get_templates']);
        add_action('wp_ajax_saga_check_duplicate', [$this, 'handle_check_duplicate']);

        // Render modal
        add_action('wp_footer', [$this, 'render_modal']);
        add_action('admin_footer', [$this, 'render_modal']);
    }

    /**
     * Add quick create menu to admin bar
     *
     * @param \WP_Admin_Bar $admin_bar
     */
    public function add_admin_bar_menu(\WP_Admin_Bar $admin_bar): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        // Get entity count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        // Main menu item
        $admin_bar->add_menu([
            'id' => 'saga-quick-create',
            'title' => $this->get_menu_title((int)$count),
            'href' => '#',
            'meta' => [
                'class' => 'saga-quick-create-trigger',
                'title' => __('Quick Create Entity (Ctrl+Shift+E)', 'saga-manager'),
            ],
        ]);

        // Add submenu for each entity type
        $entity_types = $this->get_entity_types();

        foreach ($entity_types as $type => $label) {
            $admin_bar->add_menu([
                'parent' => 'saga-quick-create',
                'id' => "saga-quick-create-{$type}",
                'title' => $this->get_entity_type_menu($type, $label),
                'href' => '#',
                'meta' => [
                    'class' => 'saga-quick-create-type',
                    'data-entity-type' => $type,
                ],
            ]);
        }

        // Recent entities separator
        $admin_bar->add_menu([
            'parent' => 'saga-quick-create',
            'id' => 'saga-recent-separator',
            'title' => '<hr style="margin: 5px 0; border: 0; border-top: 1px solid #555;">',
        ]);

        // Add recent entities
        $this->add_recent_entities($admin_bar);
    }

    /**
     * Get admin bar menu title with badge
     *
     * @param int $count
     * @return string
     */
    private function get_menu_title(int $count): string
    {
        $icon = '<span class="ab-icon dashicons dashicons-plus-alt2"></span>';
        $text = '<span class="ab-label">' . __('New Entity', 'saga-manager') . '</span>';
        $badge = sprintf(
            '<span class="saga-entity-count-badge">%s</span>',
            number_format_i18n($count)
        );

        return $icon . $text . $badge;
    }

    /**
     * Get entity type menu item HTML
     *
     * @param string $type
     * @param string $label
     * @return string
     */
    private function get_entity_type_menu(string $type, string $label): string
    {
        $icons = [
            'character' => 'admin-users',
            'location' => 'location',
            'event' => 'calendar-alt',
            'faction' => 'groups',
            'artifact' => 'star-filled',
            'concept' => 'lightbulb',
        ];

        $icon = $icons[$type] ?? 'admin-generic';

        return sprintf(
            '<span class="dashicons dashicons-%s"></span> %s',
            esc_attr($icon),
            esc_html($label)
        );
    }

    /**
     * Add recent entities to admin bar
     *
     * @param \WP_Admin_Bar $admin_bar
     */
    private function add_recent_entities(\WP_Admin_Bar $admin_bar): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT id, canonical_name, entity_type, wp_post_id
             FROM {$table}
             ORDER BY created_at DESC
             LIMIT %d",
            5
        ));

        if (empty($recent)) {
            return;
        }

        // Recent entities header
        $admin_bar->add_menu([
            'parent' => 'saga-quick-create',
            'id' => 'saga-recent-header',
            'title' => '<em>' . __('Recent Entities', 'saga-manager') . '</em>',
            'meta' => ['class' => 'saga-recent-header'],
        ]);

        foreach ($recent as $entity) {
            $edit_url = $entity->wp_post_id
                ? get_edit_post_link($entity->wp_post_id)
                : admin_url("post.php?post={$entity->wp_post_id}&action=edit");

            $admin_bar->add_menu([
                'parent' => 'saga-quick-create',
                'id' => "saga-recent-{$entity->id}",
                'title' => esc_html($entity->canonical_name),
                'href' => $edit_url,
                'meta' => [
                    'class' => 'saga-recent-entity',
                    'title' => ucfirst($entity->entity_type),
                ],
            ]);
        }
    }

    /**
     * Enqueue JavaScript and CSS assets
     */
    public function enqueue_assets(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'saga-quick-create',
            get_template_directory_uri() . '/assets/css/quick-create.css',
            [],
            '1.3.0'
        );

        // JavaScript
        wp_enqueue_script(
            'saga-quick-create',
            get_template_directory_uri() . '/assets/js/quick-create.js',
            ['jquery', 'wp-util'],
            '1.3.0',
            true
        );

        // Localize script
        wp_localize_script('saga-quick-create', 'sagaQuickCreate', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'entityTypes' => $this->get_entity_types(),
            'i18n' => [
                'title' => __('Quick Create Entity', 'saga-manager'),
                'creating' => __('Creating entity...', 'saga-manager'),
                'success' => __('Entity created successfully!', 'saga-manager'),
                'error' => __('Failed to create entity. Please try again.', 'saga-manager'),
                'required' => __('This field is required.', 'saga-manager'),
                'duplicate' => __('An entity with this name already exists.', 'saga-manager'),
                'confirm_cancel' => __('Discard unsaved changes?', 'saga-manager'),
            ],
        ]);

        // TinyMCE for rich text
        wp_enqueue_editor();
    }

    /**
     * Render quick create modal
     */
    public function render_modal(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        require_once get_template_directory() . '/inc/admin/quick-create-modal.php';
    }

    /**
     * Handle AJAX entity creation
     */
    public function handle_ajax_create(): void
    {
        // Security checks
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'saga-manager'),
            ], 403);
        }

        // Sanitize input
        $data = $this->sanitize_entity_data($_POST);

        // Validate
        $validation = $this->validate_entity_data($data);
        if (is_wp_error($validation)) {
            wp_send_json_error([
                'message' => $validation->get_error_message(),
                'errors' => $validation->get_error_data(),
            ], 400);
        }

        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Create WordPress post
            $post_id = $this->create_entity_post($data);

            if (is_wp_error($post_id)) {
                throw new \Exception($post_id->get_error_message());
            }

            // Create database entity
            $entity_id = $this->create_database_entity($post_id, $data);

            if (!$entity_id) {
                throw new \Exception('Failed to create database entity');
            }

            // Create attributes
            if (!empty($data['attributes'])) {
                $this->create_entity_attributes($entity_id, $data['attributes']);
            }

            // Create relationships
            if (!empty($data['relationships'])) {
                $this->create_entity_relationships($entity_id, $data['relationships']);
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            // Clear cache
            wp_cache_delete("saga_entity_{$entity_id}", 'saga');

            wp_send_json_success([
                'message' => __('Entity created successfully!', 'saga-manager'),
                'entity_id' => $entity_id,
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'view_url' => get_permalink($post_id),
            ]);

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            error_log('[SAGA][ERROR] Quick create failed: ' . $e->getMessage());

            wp_send_json_error([
                'message' => __('Failed to create entity. Please try again.', 'saga-manager'),
            ], 500);
        }
    }

    /**
     * Sanitize entity data from request
     *
     * @param array $raw_data
     * @return array
     */
    private function sanitize_entity_data(array $raw_data): array
    {
        return [
            'name' => sanitize_text_field($raw_data['name'] ?? ''),
            'entity_type' => sanitize_key($raw_data['entity_type'] ?? 'character'),
            'description' => wp_kses_post($raw_data['description'] ?? ''),
            'importance' => absint($raw_data['importance'] ?? 50),
            'saga_id' => absint($raw_data['saga_id'] ?? 1),
            'status' => sanitize_key($raw_data['status'] ?? 'draft'),
            'featured_image' => absint($raw_data['featured_image'] ?? 0),
            'attributes' => $raw_data['attributes'] ?? [],
            'relationships' => array_map('absint', $raw_data['relationships'] ?? []),
        ];
    }

    /**
     * Validate entity data
     *
     * @param array $data
     * @return true|\WP_Error
     */
    private function validate_entity_data(array $data)
    {
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors['name'] = __('Name is required.', 'saga-manager');
        }

        // Entity type validation
        $valid_types = array_keys($this->get_entity_types());
        if (!in_array($data['entity_type'], $valid_types, true)) {
            $errors['entity_type'] = __('Invalid entity type.', 'saga-manager');
        }

        // Importance range
        if ($data['importance'] < 0 || $data['importance'] > 100) {
            $errors['importance'] = __('Importance must be between 0 and 100.', 'saga-manager');
        }

        // Check for duplicates
        if ($this->is_duplicate_name($data['name'], $data['saga_id'])) {
            $errors['name'] = __('An entity with this name already exists.', 'saga-manager');
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', __('Validation failed.', 'saga-manager'), $errors);
        }

        return true;
    }

    /**
     * Create WordPress post for entity
     *
     * @param array $data
     * @return int|\WP_Error
     */
    private function create_entity_post(array $data)
    {
        $post_data = [
            'post_title' => $data['name'],
            'post_content' => $data['description'],
            'post_status' => $data['status'],
            'post_type' => 'saga_entity',
            'post_author' => get_current_user_id(),
        ];

        $post_id = wp_insert_post($post_data, true);

        if (!is_wp_error($post_id) && $data['featured_image']) {
            set_post_thumbnail($post_id, $data['featured_image']);
        }

        return $post_id;
    }

    /**
     * Create database entity record
     *
     * @param int $post_id
     * @param array $data
     * @return int|false
     */
    private function create_database_entity(int $post_id, array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        $result = $wpdb->insert(
            $table,
            [
                'saga_id' => $data['saga_id'],
                'entity_type' => $data['entity_type'],
                'canonical_name' => $data['name'],
                'slug' => sanitize_title($data['name']),
                'importance_score' => $data['importance'],
                'wp_post_id' => $post_id,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Create entity attributes
     *
     * @param int $entity_id
     * @param array $attributes
     */
    private function create_entity_attributes(int $entity_id, array $attributes): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_attribute_values';

        foreach ($attributes as $attr_id => $value) {
            $wpdb->insert(
                $table,
                [
                    'entity_id' => $entity_id,
                    'attribute_id' => absint($attr_id),
                    'value_string' => sanitize_text_field($value),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%s']
            );
        }
    }

    /**
     * Create entity relationships
     *
     * @param int $entity_id
     * @param array $relationships
     */
    private function create_entity_relationships(int $entity_id, array $relationships): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_entity_relationships';

        foreach ($relationships as $target_id) {
            $wpdb->insert(
                $table,
                [
                    'source_entity_id' => $entity_id,
                    'target_entity_id' => $target_id,
                    'relationship_type' => 'related',
                    'strength' => 50,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%d', '%s', '%s']
            );
        }
    }

    /**
     * Check if entity name is duplicate
     *
     * @param string $name
     * @param int $saga_id
     * @return bool
     */
    private function is_duplicate_name(string $name, int $saga_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE canonical_name = %s AND saga_id = %d",
            $name,
            $saga_id
        ));

        return (int)$count > 0;
    }

    /**
     * Handle get entity templates AJAX
     */
    public function handle_get_templates(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $entity_type = sanitize_key($_POST['entity_type'] ?? '');

        require_once get_template_directory() . '/inc/admin/entity-templates.php';
        $templates = new \SagaManager\Admin\EntityTemplates();

        wp_send_json_success([
            'templates' => $templates->get_templates_for_type($entity_type),
        ]);
    }

    /**
     * Handle duplicate check AJAX
     */
    public function handle_check_duplicate(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $saga_id = absint($_POST['saga_id'] ?? 1);

        $is_duplicate = $this->is_duplicate_name($name, $saga_id);

        wp_send_json_success([
            'is_duplicate' => $is_duplicate,
        ]);
    }

    /**
     * Get available entity types
     *
     * @return array
     */
    private function get_entity_types(): array
    {
        return [
            'character' => __('Character', 'saga-manager'),
            'location' => __('Location', 'saga-manager'),
            'event' => __('Event', 'saga-manager'),
            'faction' => __('Faction', 'saga-manager'),
            'artifact' => __('Artifact', 'saga-manager'),
            'concept' => __('Concept', 'saga-manager'),
        ];
    }
}
