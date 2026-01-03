<?php
/**
 * Search Shortcode
 *
 * Shortcode for embedding semantic search anywhere in content.
 * Usage: [saga_search]
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Shortcodes;

class SearchShortcode {

	/**
	 * Initialize shortcode
	 */
	public function __construct() {
		add_shortcode( 'saga_search', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode
	 *
	 * @param array  $atts    Shortcode attributes
	 * @param string $content Shortcode content
	 * @return string HTML output
	 */
	public function render( $atts = array(), $content = '' ): string {
		$atts = shortcode_atts(
			array(
				'placeholder'  => __( 'Search saga entities...', 'saga-manager' ),
				'show_filters' => 'true',
				'show_voice'   => 'true',
				'show_results' => 'true',
				'max_results'  => '10',
				'saga_id'      => '',
				'types'        => '',
				'compact'      => 'false',
				'inline'       => 'false',
			),
			$atts,
			'saga_search'
		);

		// Convert string booleans
		$show_filters = filter_var( $atts['show_filters'], FILTER_VALIDATE_BOOLEAN );
		$show_voice   = filter_var( $atts['show_voice'], FILTER_VALIDATE_BOOLEAN );
		$show_results = filter_var( $atts['show_results'], FILTER_VALIDATE_BOOLEAN );
		$compact      = filter_var( $atts['compact'], FILTER_VALIDATE_BOOLEAN );
		$inline       = filter_var( $atts['inline'], FILTER_VALIDATE_BOOLEAN );

		$max_results = absint( $atts['max_results'] );
		$saga_id     = absint( $atts['saga_id'] );

		// Parse types
		$types = array();
		if ( ! empty( $atts['types'] ) ) {
			$types = array_map( 'trim', explode( ',', $atts['types'] ) );
		}

		ob_start();
		?>
		<div class="saga-search-shortcode-container <?php echo $inline ? 'saga-search-inline' : ''; ?>">
			<?php
			$this->render_search_form(
				array(
					'placeholder'  => $atts['placeholder'],
					'show_filters' => $show_filters,
					'show_voice'   => $show_voice,
					'show_results' => $show_results,
					'max_results'  => $max_results,
					'saga_id'      => $saga_id,
					'types'        => $types,
					'compact'      => $compact,
				)
			);
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render search form
	 */
	private function render_search_form( array $options ): void {
		$form_class = 'saga-search-form saga-shortcode-search';
		if ( $options['compact'] ) {
			$form_class .= ' saga-search-compact';
		}

		$form_id = 'saga-search-' . wp_generate_password( 8, false );
		?>
		<div class="saga-search-container">
			<form class="<?php echo esc_attr( $form_class ); ?>"
					id="<?php echo esc_attr( $form_id ); ?>"
					role="search"
					method="get"
					action="<?php echo esc_url( home_url( '/' ) ); ?>"
					data-max-results="<?php echo esc_attr( $options['max_results'] ); ?>"
					data-saga-id="<?php echo esc_attr( $options['saga_id'] ); ?>"
					data-types="<?php echo esc_attr( implode( ',', $options['types'] ) ); ?>">

				<div class="saga-search-input-wrapper">
					<i class="saga-search-icon" aria-hidden="true">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</i>

					<input type="search"
							name="s"
							class="saga-search-input"
							placeholder="<?php echo esc_attr( $options['placeholder'] ); ?>"
							autocomplete="off"
							aria-label="<?php esc_attr_e( 'Search', 'saga-manager' ); ?>"
							aria-expanded="false"
							aria-autocomplete="list"
							aria-controls="<?php echo esc_attr( $form_id ); ?>-results">

					<?php if ( $options['show_voice'] ) : ?>
						<button type="button"
								class="saga-voice-search-btn"
								aria-label="<?php esc_attr_e( 'Voice search', 'saga-manager' ); ?>"
								title="<?php esc_attr_e( 'Voice search', 'saga-manager' ); ?>">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 2a4 4 0 0 0-4 4v6a4 4 0 0 0 8 0V6a4 4 0 0 0-4-4z" stroke="currentColor" stroke-width="2"/>
								<path d="M4 12a8 8 0 0 0 16 0M12 20v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</button>
					<?php endif; ?>

					<button type="button"
							class="saga-search-clear"
							aria-label="<?php esc_attr_e( 'Clear search', 'saga-manager' ); ?>"
							style="display: none;">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>

				<?php if ( $options['show_filters'] ) : ?>
					<div class="saga-search-filters">
						<button type="button" class="saga-search-filters-toggle">
							<span><?php esc_html_e( 'Advanced Filters', 'saga-manager' ); ?></span>
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>

						<div class="saga-search-filters-content" style="display: none;">
							<?php $this->render_filters( $options ); ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $options['show_results'] ) : ?>
					<div class="saga-search-results"
						id="<?php echo esc_attr( $form_id ); ?>-results"
						role="region"
						aria-live="polite"
						aria-label="<?php esc_attr_e( 'Search results', 'saga-manager' ); ?>">
					</div>
				<?php endif; ?>
			</form>

			<div class="saga-search-live-region" role="status" aria-live="polite" aria-atomic="true"></div>
		</div>
		<?php
	}

	/**
	 * Render search filters
	 */
	private function render_filters( array $options ): void {
		?>
		<div class="saga-filter-group">
			<label class="saga-filter-label"><?php esc_html_e( 'Entity Type', 'saga-manager' ); ?></label>
			<div class="saga-filter-types">
				<?php
				$all_types = array(
					'character' => __( 'Characters', 'saga-manager' ),
					'location'  => __( 'Locations', 'saga-manager' ),
					'event'     => __( 'Events', 'saga-manager' ),
					'faction'   => __( 'Factions', 'saga-manager' ),
					'artifact'  => __( 'Artifacts', 'saga-manager' ),
					'concept'   => __( 'Concepts', 'saga-manager' ),
				);

				foreach ( $all_types as $type => $label ) :
					$checked = empty( $options['types'] ) || in_array( $type, $options['types'], true );
					?>
					<div class="saga-filter-type-option">
						<input type="checkbox"
								id="saga-filter-type-<?php echo esc_attr( $type ); ?>-<?php echo esc_attr( uniqid() ); ?>"
								class="saga-filter-type"
								name="types[]"
								value="<?php echo esc_attr( $type ); ?>"
								<?php checked( $checked ); ?>>
						<label for="saga-filter-type-<?php echo esc_attr( $type ); ?>-<?php echo esc_attr( uniqid() ); ?>">
							<?php echo esc_html( $label ); ?>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="saga-filter-group">
			<label class="saga-filter-label"><?php esc_html_e( 'Importance Score', 'saga-manager' ); ?></label>
			<div class="saga-filter-importance-range">
				<div class="saga-filter-importance-inputs">
					<input type="number"
							class="saga-filter-importance-min"
							name="importance_min"
							min="0"
							max="100"
							placeholder="<?php esc_attr_e( 'Min', 'saga-manager' ); ?>"
							aria-label="<?php esc_attr_e( 'Minimum importance', 'saga-manager' ); ?>">
					<span>-</span>
					<input type="number"
							class="saga-filter-importance-max"
							name="importance_max"
							min="0"
							max="100"
							placeholder="<?php esc_attr_e( 'Max', 'saga-manager' ); ?>"
							aria-label="<?php esc_attr_e( 'Maximum importance', 'saga-manager' ); ?>">
				</div>
			</div>
		</div>

		<div class="saga-filter-group">
			<label class="saga-filter-label"><?php esc_html_e( 'Sort By', 'saga-manager' ); ?></label>
			<select class="saga-search-sort" name="sort">
				<option value="relevance"><?php esc_html_e( 'Relevance', 'saga-manager' ); ?></option>
				<option value="name"><?php esc_html_e( 'Name', 'saga-manager' ); ?></option>
				<option value="date"><?php esc_html_e( 'Date', 'saga-manager' ); ?></option>
				<option value="importance"><?php esc_html_e( 'Importance', 'saga-manager' ); ?></option>
			</select>
		</div>
		<?php
	}
}

// Initialize shortcode
new SearchShortcode();
