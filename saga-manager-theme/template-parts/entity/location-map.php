<?php
/**
 * Template Part: Location Map
 *
 * Displays a map or coordinates for a location
 *
 * @package SagaManager
 * @since 1.0.0
 *
 * @var array $args {
 *     Template arguments
 *     @type array $coordinates {
 *         Coordinate data
 *         @type float $lat Latitude
 *         @type float $lng Longitude
 *     }
 * }
 */

declare(strict_types=1);

// Extract arguments
$coordinates = $args['coordinates'] ?? [];

// Exit if no coordinates
if (empty($coordinates)) {
    return;
}

$lat = $coordinates['lat'] ?? null;
$lng = $coordinates['lng'] ?? null;

// Exit if incomplete coordinates
if ($lat === null || $lng === null) {
    return;
}
?>

<section class="saga-section saga-location-map">
    <h2 class="saga-section__title"><?php esc_html_e('Map & Coordinates', 'saga-manager'); ?></h2>

    <div class="saga-location-map__container">
        <!-- Coordinates Display -->
        <div class="saga-location-map__coordinates">
            <dl class="saga-attributes">
                <div class="saga-attribute">
                    <dt class="saga-attribute__label"><?php esc_html_e('Latitude', 'saga-manager'); ?></dt>
                    <dd class="saga-attribute__value">
                        <code><?php echo esc_html(number_format((float) $lat, 6)); ?></code>
                    </dd>
                </div>

                <div class="saga-attribute">
                    <dt class="saga-attribute__label"><?php esc_html_e('Longitude', 'saga-manager'); ?></dt>
                    <dd class="saga-attribute__value">
                        <code><?php echo esc_html(number_format((float) $lng, 6)); ?></code>
                    </dd>
                </div>
            </dl>
        </div>

        <?php
        /**
         * Hook for adding custom map display
         *
         * Plugins can hook into this to add interactive maps (e.g., Leaflet, Google Maps)
         *
         * @param float $lat Latitude
         * @param float $lng Longitude
         */
        do_action('saga_location_map_display', $lat, $lng);
        ?>

        <!-- Placeholder for map - can be replaced with JavaScript map library -->
        <div class="saga-location-map__placeholder"
             data-lat="<?php echo esc_attr($lat); ?>"
             data-lng="<?php echo esc_attr($lng); ?>">

            <?php if (has_post_thumbnail()) : ?>
                <?php
                // Display location image as map placeholder
                the_post_thumbnail('medium', [
                    'class' => 'saga-location-map__image',
                    'alt' => sprintf(
                        /* translators: %1$s: latitude, %2$s: longitude */
                        __('Location at coordinates %1$s, %2$s', 'saga-manager'),
                        number_format((float) $lat, 6),
                        number_format((float) $lng, 6)
                    ),
                ]);
                ?>
            <?php else : ?>
                <div class="saga-location-map__default">
                    <svg class="saga-location-map__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <p class="saga-location-map__text">
                        <?php
                        printf(
                            /* translators: %1$s: latitude, %2$s: longitude */
                            esc_html__('Location: %1$s, %2$s', 'saga-manager'),
                            esc_html(number_format((float) $lat, 6)),
                            esc_html(number_format((float) $lng, 6))
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Display additional map data if available
        $elevation = $coordinates['elevation'] ?? null;
        $region = $coordinates['region'] ?? null;

        if ($elevation !== null || $region !== null) :
            ?>
            <div class="saga-location-map__meta">
                <?php if ($elevation !== null) : ?>
                    <span class="saga-location-map__meta-item">
                        <strong><?php esc_html_e('Elevation:', 'saga-manager'); ?></strong>
                        <?php echo esc_html($elevation); ?>
                    </span>
                <?php endif; ?>

                <?php if ($region !== null) : ?>
                    <span class="saga-location-map__meta-item">
                        <strong><?php esc_html_e('Region:', 'saga-manager'); ?></strong>
                        <?php echo esc_html($region); ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
/**
 * Example JavaScript integration for interactive map:
 *
 * document.addEventListener('DOMContentLoaded', function() {
 *     const mapPlaceholder = document.querySelector('.saga-location-map__placeholder');
 *     if (mapPlaceholder) {
 *         const lat = parseFloat(mapPlaceholder.dataset.lat);
 *         const lng = parseFloat(mapPlaceholder.dataset.lng);
 *
 *         // Initialize map library (Leaflet example)
 *         const map = L.map(mapPlaceholder).setView([lat, lng], 13);
 *         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
 *         L.marker([lat, lng]).addTo(map);
 *     }
 * });
 */
?>
