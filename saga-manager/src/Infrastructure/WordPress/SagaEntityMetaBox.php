<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\WordPress;

use SagaManager\Domain\Entity\EntityType;

/**
 * Saga Entity Meta Box
 *
 * Provides admin UI for entity metadata (saga_id, entity_type, importance_score)
 */
class SagaEntityMetaBox extends WordPressTablePrefixAware
{
    private const POST_TYPE = 'saga_entity';
    private const NONCE_ACTION = 'saga_entity_meta_box';
    private const NONCE_NAME = 'saga_entity_meta_box_nonce';

    /**
     * Register meta box
     */
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
    }

    /**
     * Add meta box to post editor
     */
    public function addMetaBox(): void
    {
        add_meta_box(
            'saga_entity_details',
            __('Saga Entity Details', 'saga-manager'),
            [$this, 'renderMetaBox'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render meta box HTML
     *
     * @param \WP_Post $post Current post
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        // Add nonce for security
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        // Get current values
        $saga_id = (int) get_post_meta($post->ID, '_saga_id', true);
        $entity_type = get_post_meta($post->ID, '_entity_type', true);
        $importance_score = (int) get_post_meta($post->ID, '_importance_score', true) ?: 50;

        // Get available sagas
        $sagas = $this->getAvailableSagas();

        ?>
        <div class="saga-entity-meta-box">
            <p>
                <label for="saga_id">
                    <strong><?php esc_html_e('Saga:', 'saga-manager'); ?></strong>
                </label>
                <select name="saga_id" id="saga_id" class="widefat" required>
                    <option value=""><?php esc_html_e('Select a saga...', 'saga-manager'); ?></option>
                    <?php foreach ($sagas as $saga): ?>
                        <option value="<?php echo esc_attr($saga['id']); ?>"
                                <?php selected($saga_id, $saga['id']); ?>>
                            <?php echo esc_html($saga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="entity_type">
                    <strong><?php esc_html_e('Entity Type:', 'saga-manager'); ?></strong>
                </label>
                <select name="entity_type" id="entity_type" class="widefat" required>
                    <option value=""><?php esc_html_e('Select type...', 'saga-manager'); ?></option>
                    <?php foreach (EntityType::cases() as $type): ?>
                        <option value="<?php echo esc_attr($type->value); ?>"
                                <?php selected($entity_type, $type->value); ?>>
                            <?php echo esc_html($type->label()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="importance_score">
                    <strong><?php esc_html_e('Importance Score:', 'saga-manager'); ?></strong>
                </label>
                <input type="number"
                       name="importance_score"
                       id="importance_score"
                       class="widefat"
                       min="0"
                       max="100"
                       step="1"
                       value="<?php echo esc_attr($importance_score); ?>" />
                <span class="description">
                    <?php esc_html_e('0-100 scale (higher = more important)', 'saga-manager'); ?>
                </span>
            </p>

            <?php
            // Display sync conflict warning if exists
            $conflict = get_post_meta($post->ID, '_saga_sync_conflict', true);
            if ($conflict && is_array($conflict)):
            ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php esc_html_e('Sync Conflict Detected:', 'saga-manager'); ?></strong><br>
                        <?php
                        if (isset($conflict['resolution'])) {
                            printf(
                                esc_html__('Resolved using: %s', 'saga-manager'),
                                esc_html($conflict['resolution'])
                            );
                        }
                        if (isset($conflict['timestamp'])) {
                            echo '<br>' . esc_html(sprintf(
                                __('Last conflict: %s', 'saga-manager'),
                                $conflict['timestamp']
                            ));
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <style>
                .saga-entity-meta-box p {
                    margin-bottom: 15px;
                }
                .saga-entity-meta-box label {
                    display: block;
                    margin-bottom: 5px;
                }
                .saga-entity-meta-box .description {
                    display: block;
                    margin-top: 5px;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Save meta box data
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function saveMetaBox(int $post_id, \WP_Post $post): void
    {
        // Check if this is the correct post type
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        // Verify nonce
        if (!isset($_POST[self::NONCE_NAME]) ||
            !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Sanitize and save saga_id
        if (isset($_POST['saga_id'])) {
            $saga_id = absint($_POST['saga_id']);
            if ($saga_id > 0) {
                update_post_meta($post_id, '_saga_id', $saga_id);
            } else {
                delete_post_meta($post_id, '_saga_id');
            }
        }

        // Sanitize and save entity_type
        if (isset($_POST['entity_type'])) {
            $entity_type = sanitize_key($_POST['entity_type']);

            // Validate entity type
            try {
                EntityType::from($entity_type);
                update_post_meta($post_id, '_entity_type', $entity_type);
            } catch (\ValueError $e) {
                error_log("[SAGA][ERROR] Invalid entity type: {$entity_type}");
            }
        }

        // Sanitize and save importance_score
        if (isset($_POST['importance_score'])) {
            $importance_score = absint($_POST['importance_score']);

            // Clamp to 0-100 range
            $importance_score = max(0, min(100, $importance_score));

            update_post_meta($post_id, '_importance_score', $importance_score);
        }
    }

    /**
     * Get available sagas from database
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function getAvailableSagas(): array
    {
        $table = $this->getTableName('sagas');

        $results = $this->wpdb->get_results(
            "SELECT id, name FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
            ];
        }, $results);
    }
}
