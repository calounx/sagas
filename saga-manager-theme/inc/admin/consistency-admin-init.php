<?php
/**
 * AI Consistency Guardian Admin Initialization
 *
 * Registers admin menus, enqueues assets, and integrates the consistency guardian
 * into WordPress admin interface
 *
 * @package SagaManager\Admin
 * @version 1.4.0
 */

declare(strict_types=1);

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saga_Consistency_Admin_Init Class
 *
 * Handles all admin initialization for consistency guardian
 */
final class Saga_Consistency_Admin_Init {

	/**
	 * @var string Page slug
	 */
	private const PAGE_SLUG = 'saga-consistency-guardian';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register admin menu
		add_action( 'admin_menu', array( $this, 'registerAdminMenu' ) );

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_saga-manager-theme/functions.php', array( $this, 'addSettingsLink' ) );

		// Add admin notices
		add_action( 'admin_notices', array( $this, 'showAdminNotices' ) );
	}

	/**
	 * Register admin menu
	 */
	public function registerAdminMenu(): void {
		// Main menu item
		add_menu_page(
			__( 'AI Consistency Guardian', 'saga-manager-theme' ),
			__( 'AI Guardian', 'saga-manager-theme' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'renderAdminPage' ),
			'dashicons-shield-alt',
			30
		);

		// Settings submenu
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Guardian Settings', 'saga-manager-theme' ),
			__( 'Settings', 'saga-manager-theme' ),
			'manage_options',
			'saga-consistency-settings',
			array( $this, 'renderSettingsPage' )
		);

		// Scan history submenu
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Scan History', 'saga-manager-theme' ),
			__( 'Scan History', 'saga-manager-theme' ),
			'manage_options',
			'saga-consistency-history',
			array( $this, 'renderHistoryPage' )
		);
	}

	/**
	 * Render main admin page
	 */
	public function renderAdminPage(): void {
		$template = get_template_directory() . '/page-templates/admin-consistency-page.php';

		if ( file_exists( $template ) ) {
			require_once $template;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'AI Consistency Guardian', 'saga-manager-theme' ) . '</h1>';
			echo '<p>' . esc_html__( 'Template file not found.', 'saga-manager-theme' ) . '</p></div>';
		}
	}

	/**
	 * Render settings page
	 */
	public function renderSettingsPage(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Consistency Guardian Settings', 'saga-manager-theme' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'saga_consistency_settings' );
				do_settings_sections( 'saga_consistency_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render history page
	 */
	public function renderHistoryPage(): void {
		global $wpdb;
		$tableName = $wpdb->prefix . 'saga_consistency_issues';

		// Get scan statistics
		$stats = $wpdb->get_row(
			"
            SELECT
                COUNT(*) as total_scans,
                COUNT(DISTINCT DATE(detected_at)) as scan_days,
                MIN(detected_at) as first_scan,
                MAX(detected_at) as last_scan
            FROM {$tableName}
        "
		);

		// Get recent scans (grouped by day)
		$recentScans = $wpdb->get_results(
			"
            SELECT
                DATE(detected_at) as scan_date,
                COUNT(*) as issues_found,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
            FROM {$tableName}
            GROUP BY DATE(detected_at)
            ORDER BY scan_date DESC
            LIMIT 20
        "
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Scan History', 'saga-manager-theme' ); ?></h1>

			<div class="scan-history-stats">
				<div class="stat-box">
					<span class="stat-count"><?php echo esc_html( $stats->total_scans ?? 0 ); ?></span>
					<span class="stat-label"><?php esc_html_e( 'Total Issues Found', 'saga-manager-theme' ); ?></span>
				</div>
				<div class="stat-box">
					<span class="stat-count"><?php echo esc_html( $stats->scan_days ?? 0 ); ?></span>
					<span class="stat-label"><?php esc_html_e( 'Scan Days', 'saga-manager-theme' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $recentScans ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'saga-manager-theme' ); ?></th>
							<th><?php esc_html_e( 'Issues Found', 'saga-manager-theme' ); ?></th>
							<th><?php esc_html_e( 'Resolved', 'saga-manager-theme' ); ?></th>
							<th><?php esc_html_e( 'Resolution Rate', 'saga-manager-theme' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recentScans as $scan ) : ?>
							<?php
							$resolutionRate = $scan->issues_found > 0
								? round( ( $scan->resolved_count / $scan->issues_found ) * 100, 1 )
								: 0;
							?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $scan->scan_date ) ) ); ?></td>
								<td><?php echo esc_html( $scan->issues_found ); ?></td>
								<td><?php echo esc_html( $scan->resolved_count ); ?></td>
								<td>
									<div class="progress-bar-mini">
										<div class="progress-fill" style="width: <?php echo esc_attr( $resolutionRate ); ?>%;"></div>
									</div>
									<span><?php echo esc_html( $resolutionRate ); ?>%</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No scan history found.', 'saga-manager-theme' ); ?></p>
			<?php endif; ?>
		</div>

		<style>
			.scan-history-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin: 20px 0;
			}

			.scan-history-stats .stat-box {
				background: white;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
				text-align: center;
			}

			.progress-bar-mini {
				display: inline-block;
				width: 100px;
				height: 10px;
				background: #f0f0f1;
				border-radius: 5px;
				overflow: hidden;
				margin-right: 10px;
				vertical-align: middle;
			}

			.progress-bar-mini .progress-fill {
				height: 100%;
				background: #16a34a;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueueAdminAssets( string $hook ): void {
		// Only load on consistency guardian pages
		if ( ! $this->isConsistencyPage( $hook ) ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'saga-consistency-dashboard',
			get_template_directory_uri() . '/assets/css/consistency-dashboard.css',
			array(),
			'1.4.0'
		);

		// Enqueue JavaScript
		wp_enqueue_script(
			'saga-consistency-dashboard',
			get_template_directory_uri() . '/assets/js/consistency-dashboard.js',
			array( 'jquery' ),
			'1.4.0',
			true
		);

		// Localize script
		wp_localize_script(
			'saga-consistency-dashboard',
			'sagaConsistencyL10n',
			array(
				'confirmDelete' => __( 'Are you sure you want to delete this issue?', 'saga-manager-theme' ),
				'confirmBulk'   => __( 'Are you sure you want to perform this action?', 'saga-manager-theme' ),
				'scanRunning'   => __( 'Scan is running...', 'saga-manager-theme' ),
				'scanComplete'  => __( 'Scan completed successfully', 'saga-manager-theme' ),
				'scanFailed'    => __( 'Scan failed. Please try again.', 'saga-manager-theme' ),
				'networkError'  => __( 'Network error. Please check your connection.', 'saga-manager-theme' ),
			)
		);
	}

	/**
	 * Check if current page is a consistency page
	 */
	private function isConsistencyPage( string $hook ): bool {
		return in_array(
			$hook,
			array(
				'toplevel_page_' . self::PAGE_SLUG,
				'ai-guardian_page_saga-consistency-settings',
				'ai-guardian_page_saga-consistency-history',
			),
			true
		);
	}

	/**
	 * Add settings link to plugins page
	 */
	public function addSettingsLink( array $links ): array {
		$settingsLink = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			__( 'AI Guardian', 'saga-manager-theme' )
		);

		array_unshift( $links, $settingsLink );

		return $links;
	}

	/**
	 * Show admin notices
	 */
	public function showAdminNotices(): void {
		// Check if AI is enabled
		$aiEnabled = get_option( 'saga_ai_consistency_enabled', false );

		if ( ! $aiEnabled && $this->isConsistencyPage( get_current_screen()->id ?? '' ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'AI Analysis Disabled', 'saga-manager-theme' ); ?></strong>
					<?php esc_html_e( 'AI-powered consistency analysis is currently disabled. Only rule-based checks will be performed.', 'saga-manager-theme' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=saga-consistency-settings' ) ); ?>">
						<?php esc_html_e( 'Enable AI Analysis', 'saga-manager-theme' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		// Check for recent critical issues
		$this->showCriticalIssuesNotice();
	}

	/**
	 * Show notice for critical issues
	 */
	private function showCriticalIssuesNotice(): void {
		// Only show on dashboard
		$screen = get_current_screen();
		if ( $screen->id !== 'dashboard' ) {
			return;
		}

		// Check transient to avoid repeated queries
		$criticalCount = get_transient( 'saga_critical_issues_count' );

		if ( $criticalCount === false ) {
			global $wpdb;
			$tableName = $wpdb->prefix . 'saga_consistency_issues';

			$criticalCount = (int) $wpdb->get_var(
				"
                SELECT COUNT(*)
                FROM {$tableName}
                WHERE severity = 'critical'
                AND status = 'open'
                AND detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            "
			);

			set_transient( 'saga_critical_issues_count', $criticalCount, 300 ); // 5 minutes
		}

		if ( $criticalCount > 0 ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Critical Consistency Issues Detected', 'saga-manager-theme' ); ?></strong>
					<?php
					printf(
						esc_html(
							_n(
								'%d critical consistency issue needs attention.',
								'%d critical consistency issues need attention.',
								$criticalCount,
								'saga-manager-theme'
							)
						),
						$criticalCount
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>" class="button button-small">
						<?php esc_html_e( 'View Issues', 'saga-manager-theme' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}
}

// Initialize
new Saga_Consistency_Admin_Init();

/**
 * Register settings
 */
function saga_register_consistency_settings(): void {
	register_setting( 'saga_consistency_settings', 'saga_ai_consistency_enabled' );
	register_setting( 'saga_consistency_settings', 'saga_ai_api_key' );
	register_setting( 'saga_consistency_settings', 'saga_ai_model' );
	register_setting( 'saga_consistency_settings', 'saga_scan_schedule' );

	add_settings_section(
		'saga_consistency_ai_section',
		__( 'AI Settings', 'saga-manager-theme' ),
		'saga_consistency_ai_section_callback',
		'saga_consistency_settings'
	);

	add_settings_field(
		'saga_ai_consistency_enabled',
		__( 'Enable AI Analysis', 'saga-manager-theme' ),
		'saga_ai_enabled_field_callback',
		'saga_consistency_settings',
		'saga_consistency_ai_section'
	);

	add_settings_field(
		'saga_ai_api_key',
		__( 'OpenAI API Key', 'saga-manager-theme' ),
		'saga_ai_api_key_field_callback',
		'saga_consistency_settings',
		'saga_consistency_ai_section'
	);

	add_settings_field(
		'saga_ai_model',
		__( 'AI Model', 'saga-manager-theme' ),
		'saga_ai_model_field_callback',
		'saga_consistency_settings',
		'saga_consistency_ai_section'
	);

	add_settings_section(
		'saga_consistency_scan_section',
		__( 'Scan Settings', 'saga-manager-theme' ),
		'saga_consistency_scan_section_callback',
		'saga_consistency_settings'
	);

	add_settings_field(
		'saga_scan_schedule',
		__( 'Automatic Scan Schedule', 'saga-manager-theme' ),
		'saga_scan_schedule_field_callback',
		'saga_consistency_settings',
		'saga_consistency_scan_section'
	);
}
add_action( 'admin_init', 'saga_register_consistency_settings' );

/**
 * Settings section callbacks
 */
function saga_consistency_ai_section_callback(): void {
	echo '<p>' . esc_html__( 'Configure AI-powered consistency analysis settings.', 'saga-manager-theme' ) . '</p>';
}

function saga_consistency_scan_section_callback(): void {
	echo '<p>' . esc_html__( 'Configure automatic consistency scanning.', 'saga-manager-theme' ) . '</p>';
}

/**
 * Settings field callbacks
 */
function saga_ai_enabled_field_callback(): void {
	$enabled = get_option( 'saga_ai_consistency_enabled', false );
	?>
	<label>
		<input type="checkbox" name="saga_ai_consistency_enabled" value="1" <?php checked( $enabled, true ); ?>>
		<?php esc_html_e( 'Enable AI-powered semantic analysis', 'saga-manager-theme' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'When enabled, the system will use AI to detect complex consistency issues that rule-based checks might miss.', 'saga-manager-theme' ); ?>
	</p>
	<?php
}

function saga_ai_api_key_field_callback(): void {
	$apiKey = get_option( 'saga_ai_api_key', '' );
	?>
	<input type="password" name="saga_ai_api_key" value="<?php echo esc_attr( $apiKey ); ?>" class="regular-text">
	<p class="description">
		<?php esc_html_e( 'Enter your OpenAI API key. Get one at', 'saga-manager-theme' ); ?>
		<a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
	</p>
	<?php
}

function saga_ai_model_field_callback(): void {
	$model  = get_option( 'saga_ai_model', 'gpt-4-turbo-preview' );
	$models = array(
		'gpt-4-turbo-preview' => 'GPT-4 Turbo (Recommended)',
		'gpt-4'               => 'GPT-4',
		'gpt-3.5-turbo'       => 'GPT-3.5 Turbo (Faster, less accurate)',
	);
	?>
	<select name="saga_ai_model">
		<?php foreach ( $models as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $model, $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php esc_html_e( 'Select the AI model to use for analysis. GPT-4 provides better results but is slower and more expensive.', 'saga-manager-theme' ); ?>
	</p>
	<?php
}

function saga_scan_schedule_field_callback(): void {
	$schedule  = get_option( 'saga_scan_schedule', 'manual' );
	$schedules = array(
		'manual'  => __( 'Manual only', 'saga-manager-theme' ),
		'daily'   => __( 'Daily', 'saga-manager-theme' ),
		'weekly'  => __( 'Weekly', 'saga-manager-theme' ),
		'monthly' => __( 'Monthly', 'saga-manager-theme' ),
	);
	?>
	<select name="saga_scan_schedule">
		<?php foreach ( $schedules as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schedule, $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php esc_html_e( 'Schedule automatic consistency scans. Manual scans can always be run from the main page.', 'saga-manager-theme' ); ?>
	</p>
	<?php
}
