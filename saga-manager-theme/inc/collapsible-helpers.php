<?php
/**
 * Collapsible Sections Helpers
 *
 * Helper functions for rendering accordion-style collapsible sections
 * with state persistence and accessibility.
 *
 * @package SagaManager
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a collapsible section
 *
 * @param array $args {
 *     Section configuration
 *     @type string $id Required. Unique section identifier
 *     @type string $title Required. Section heading text
 *     @type string $content Required. Section content HTML
 *     @type bool $expanded Optional. Default expanded state. Default true.
 *     @type string $icon Optional. Icon identifier. Default empty.
 *     @type string $heading_level Optional. Heading level (h2-h6). Default 'h3'.
 *     @type array $classes Optional. Additional CSS classes. Default empty.
 * }
 * @return void
 */
function saga_collapsible_section( array $args ): void {
	$defaults = array(
		'id'            => '',
		'title'         => '',
		'content'       => '',
		'expanded'      => true,
		'icon'          => '',
		'heading_level' => 'h3',
		'classes'       => array(),
	);

	$args = wp_parse_args( $args, $defaults );

	// Validate required fields
	if ( empty( $args['id'] ) || empty( $args['title'] ) || empty( $args['content'] ) ) {
		if ( WP_DEBUG ) {
			error_log( '[SAGA][ERROR] Collapsible section missing required fields' );
		}
		return;
	}

	// Sanitize inputs
	$section_id    = sanitize_key( $args['id'] );
	$title         = wp_kses_post( $args['title'] );
	$content       = wp_kses_post( $args['content'] );
	$expanded      = (bool) $args['expanded'];
	$icon          = sanitize_text_field( $args['icon'] );
	$heading_level = in_array( $args['heading_level'], array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true )
		? $args['heading_level']
		: 'h3';

	$classes = array_merge(
		array( 'saga-collapsible-section' ),
		array_map( 'sanitize_html_class', (array) $args['classes'] )
	);

	// Load template part
	get_template_part(
		'template-parts/collapsible-section',
		null,
		array(
			'section_id'    => $section_id,
			'title'         => $title,
			'content'       => $content,
			'expanded'      => $expanded,
			'icon'          => $icon,
			'heading_level' => $heading_level,
			'classes'       => implode( ' ', $classes ),
		)
	);
}

/**
 * Render section controls (Expand All / Collapse All)
 *
 * @param array $args {
 *     Optional. Control configuration
 *     @type string $position Optional. Control position (top|bottom|both). Default 'top'.
 *     @type bool $show_expand Optional. Show expand all button. Default true.
 *     @type bool $show_collapse Optional. Show collapse all button. Default true.
 * }
 * @return void
 */
function saga_collapsible_controls( array $args = array() ): void {
	$defaults = array(
		'position'      => 'top',
		'show_expand'   => true,
		'show_collapse' => true,
	);

	$args = wp_parse_args( $args, $defaults );

	if ( ! $args['show_expand'] && ! $args['show_collapse'] ) {
		return;
	}

	?>
	<div class="saga-section-controls" data-position="<?php echo esc_attr( $args['position'] ); ?>">
		<?php if ( $args['show_expand'] ) : ?>
			<button type="button" class="saga-expand-all" aria-label="<?php esc_attr_e( 'Expand all sections', 'saga-manager' ); ?>">
				<svg class="icon" width="16" height="16" aria-hidden="true">
					<use href="#icon-chevron-down"></use>
				</svg>
				<?php esc_html_e( 'Expand All', 'saga-manager' ); ?>
			</button>
		<?php endif; ?>

		<?php if ( $args['show_collapse'] ) : ?>
			<button type="button" class="saga-collapse-all" aria-label="<?php esc_attr_e( 'Collapse all sections', 'saga-manager' ); ?>">
				<svg class="icon" width="16" height="16" aria-hidden="true">
					<use href="#icon-chevron-up"></use>
				</svg>
				<?php esc_html_e( 'Collapse All', 'saga-manager' ); ?>
			</button>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Get section configuration for entity type
 *
 * @param string $entity_type Entity type (character, location, etc.)
 * @return array Array of section configurations
 */
function saga_get_entity_sections( string $entity_type ): array {
	$sections = array();

	switch ( $entity_type ) {
		case 'character':
			$sections = array(
				'biography'     => array(
					'title'    => __( 'Biography', 'saga-manager' ),
					'icon'     => 'user',
					'expanded' => true,
				),
				'attributes'    => array(
					'title'    => __( 'Attributes', 'saga-manager' ),
					'icon'     => 'list',
					'expanded' => true,
				),
				'relationships' => array(
					'title'    => __( 'Relationships', 'saga-manager' ),
					'icon'     => 'users',
					'expanded' => false,
				),
				'timeline'      => array(
					'title'    => __( 'Timeline', 'saga-manager' ),
					'icon'     => 'clock',
					'expanded' => false,
				),
				'quotes'        => array(
					'title'    => __( 'Quotes', 'saga-manager' ),
					'icon'     => 'quote',
					'expanded' => false,
				),
			);
			break;

		case 'location':
			$sections = array(
				'description'  => array(
					'title'    => __( 'Description', 'saga-manager' ),
					'icon'     => 'map-pin',
					'expanded' => true,
				),
				'geography'    => array(
					'title'    => __( 'Geography', 'saga-manager' ),
					'icon'     => 'globe',
					'expanded' => true,
				),
				'inhabitants'  => array(
					'title'    => __( 'Inhabitants', 'saga-manager' ),
					'icon'     => 'users',
					'expanded' => false,
				),
				'events'       => array(
					'title'    => __( 'Events', 'saga-manager' ),
					'icon'     => 'calendar',
					'expanded' => false,
				),
				'sublocations' => array(
					'title'    => __( 'Sub-locations', 'saga-manager' ),
					'icon'     => 'map',
					'expanded' => false,
				),
			);
			break;

		case 'event':
			$sections = array(
				'description'  => array(
					'title'    => __( 'Description', 'saga-manager' ),
					'icon'     => 'file-text',
					'expanded' => true,
				),
				'participants' => array(
					'title'    => __( 'Participants', 'saga-manager' ),
					'icon'     => 'users',
					'expanded' => true,
				),
				'location'     => array(
					'title'    => __( 'Location', 'saga-manager' ),
					'icon'     => 'map-pin',
					'expanded' => false,
				),
				'consequences' => array(
					'title'    => __( 'Consequences', 'saga-manager' ),
					'icon'     => 'arrow-right',
					'expanded' => false,
				),
				'related'      => array(
					'title'    => __( 'Related Events', 'saga-manager' ),
					'icon'     => 'link',
					'expanded' => false,
				),
			);
			break;

		case 'faction':
			$sections = array(
				'description' => array(
					'title'    => __( 'Description', 'saga-manager' ),
					'icon'     => 'file-text',
					'expanded' => true,
				),
				'leadership'  => array(
					'title'    => __( 'Leadership', 'saga-manager' ),
					'icon'     => 'crown',
					'expanded' => true,
				),
				'members'     => array(
					'title'    => __( 'Members', 'saga-manager' ),
					'icon'     => 'users',
					'expanded' => false,
				),
				'territories' => array(
					'title'    => __( 'Territories', 'saga-manager' ),
					'icon'     => 'map',
					'expanded' => false,
				),
				'history'     => array(
					'title'    => __( 'History', 'saga-manager' ),
					'icon'     => 'clock',
					'expanded' => false,
				),
			);
			break;

		case 'artifact':
			$sections = array(
				'description' => array(
					'title'    => __( 'Description', 'saga-manager' ),
					'icon'     => 'file-text',
					'expanded' => true,
				),
				'history'     => array(
					'title'    => __( 'History', 'saga-manager' ),
					'icon'     => 'clock',
					'expanded' => true,
				),
				'powers'      => array(
					'title'    => __( 'Powers', 'saga-manager' ),
					'icon'     => 'star',
					'expanded' => false,
				),
				'owners'      => array(
					'title'    => __( 'Owners', 'saga-manager' ),
					'icon'     => 'users',
					'expanded' => false,
				),
			);
			break;

		case 'concept':
			$sections = array(
				'definition'   => array(
					'title'    => __( 'Definition', 'saga-manager' ),
					'icon'     => 'book',
					'expanded' => true,
				),
				'significance' => array(
					'title'    => __( 'Significance', 'saga-manager' ),
					'icon'     => 'star',
					'expanded' => true,
				),
				'examples'     => array(
					'title'    => __( 'Examples', 'saga-manager' ),
					'icon'     => 'list',
					'expanded' => false,
				),
				'related'      => array(
					'title'    => __( 'Related Concepts', 'saga-manager' ),
					'icon'     => 'link',
					'expanded' => false,
				),
			);
			break;

		default:
			$sections = array();
	}

	/**
	 * Filter entity sections
	 *
	 * @param array $sections Section configurations
	 * @param string $entity_type Entity type
	 */
	return apply_filters( 'saga_entity_sections', $sections, $entity_type );
}

/**
 * Check if reduced motion is preferred
 *
 * @return bool True if user prefers reduced motion
 */
function saga_prefers_reduced_motion(): bool {
	// Can be extended to check user meta or site option
	return false;
}

/**
 * Get default section state for mobile
 *
 * @return bool True if sections should be collapsed on mobile by default
 */
function saga_mobile_collapsed_default(): bool {
	return apply_filters( 'saga_mobile_collapsed_default', true );
}

/**
 * Enqueue collapsible sections assets
 *
 * @return void
 */
function saga_enqueue_collapsible_assets(): void {
	// CSS
	wp_enqueue_style(
		'saga-collapsible-sections',
		get_template_directory_uri() . '/assets/css/collapsible-sections.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	// JavaScript
	wp_enqueue_script(
		'saga-collapsible-sections',
		get_template_directory_uri() . '/assets/js/collapsible-sections.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	// Localization
	wp_localize_script(
		'saga-collapsible-sections',
		'sagaCollapsible',
		array(
			'reducedMotion'   => saga_prefers_reduced_motion(),
			'mobileCollapsed' => saga_mobile_collapsed_default(),
			'pageId'          => get_the_ID(),
			'i18n'            => array(
				'expanded'    => __( 'Expanded', 'saga-manager' ),
				'collapsed'   => __( 'Collapsed', 'saga-manager' ),
				'expandAll'   => __( 'Expand all sections', 'saga-manager' ),
				'collapseAll' => __( 'Collapse all sections', 'saga-manager' ),
			),
		)
	);
}

// Auto-enqueue on entity pages
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_singular( 'saga_entity' ) ) {
			saga_enqueue_collapsible_assets();
		}
	}
);
