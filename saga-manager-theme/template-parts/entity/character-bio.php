<?php
/**
 * Template Part: Character Biography
 *
 * Reusable character biography section with attributes
 *
 * @package SagaManager
 * @since 1.0.0
 *
 * @var array $args {
 *     Template arguments
 *     @type int    $post_id    Post ID of the character
 *     @type string $title      Section title (default: 'Biography')
 *     @type array  $attributes Optional array of additional attributes to display
 * }
 */

declare(strict_types=1);

// Extract arguments
$post_id = $args['post_id'] ?? get_the_ID();
$title = $args['title'] ?? __('Biography', 'saga-manager');
$additional_attributes = $args['attributes'] ?? [];

// Validate post ID
if (!$post_id) {
    return;
}

// Get the post
$post = get_post($post_id);

if (!$post || $post->post_status !== 'publish') {
    return;
}
?>

<section class="saga-section saga-character-bio">
    <h2 class="saga-section__title"><?php echo esc_html($title); ?></h2>

    <div class="saga-character-bio__container">

        <?php if (has_post_thumbnail($post_id)) : ?>
            <div class="saga-character-bio__portrait">
                <?php echo get_the_post_thumbnail($post_id, 'medium', [
                    'class' => 'saga-character-bio__portrait-image',
                    'alt' => get_the_title($post_id),
                ]); ?>
            </div>
        <?php endif; ?>

        <div class="saga-character-bio__content">

            <!-- Character Name -->
            <h3 class="saga-character-bio__name">
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="saga-character-bio__link">
                    <?php echo esc_html(get_the_title($post_id)); ?>
                </a>
            </h3>

            <!-- Aliases -->
            <?php
            $aliases = get_post_meta($post_id, '_saga_character_aliases', true);
            if (!empty($aliases)) :
                ?>
                <p class="saga-character-bio__aliases">
                    <em><?php echo esc_html($aliases); ?></em>
                </p>
            <?php endif; ?>

            <!-- Excerpt/Description -->
            <?php if ($post->post_excerpt) : ?>
                <div class="saga-character-bio__excerpt">
                    <?php echo wp_kses_post(wpautop($post->post_excerpt)); ?>
                </div>
            <?php endif; ?>

            <!-- Quick Attributes -->
            <?php
            $quick_attrs = [
                'species' => __('Species', 'saga-manager'),
                'affiliation' => __('Affiliation', 'saga-manager'),
                'occupation' => __('Occupation', 'saga-manager'),
            ];

            // Merge with additional attributes
            $quick_attrs = array_merge($quick_attrs, $additional_attributes);

            $has_attrs = false;
            foreach ($quick_attrs as $key => $label) {
                if (get_post_meta($post_id, "_saga_character_{$key}", true)) {
                    $has_attrs = true;
                    break;
                }
            }

            if ($has_attrs) :
                ?>
                <dl class="saga-character-bio__attributes">
                    <?php
                    foreach ($quick_attrs as $key => $label) :
                        $value = get_post_meta($post_id, "_saga_character_{$key}", true);

                        if (!empty($value)) :
                            ?>
                            <div class="saga-character-bio__attribute">
                                <dt class="saga-character-bio__attribute-label"><?php echo esc_html($label); ?></dt>
                                <dd class="saga-character-bio__attribute-value"><?php echo esc_html($value); ?></dd>
                            </div>
                        <?php
                        endif;
                    endforeach;
                    ?>
                </dl>
            <?php endif; ?>

            <!-- Read More Link -->
            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="saga-character-bio__read-more">
                <?php esc_html_e('View Full Profile', 'saga-manager'); ?>
                <span class="saga-character-bio__arrow" aria-hidden="true">&rarr;</span>
            </a>

        </div>

    </div>
</section>
