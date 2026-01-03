<?php
declare(strict_types=1);

/**
 * Analytics Tracking Class
 *
 * Privacy-first tracking with GDPR compliance
 *
 * @package Saga_Manager_Theme
 */

class Saga_Analytics {

	private const SALT = 'saga_analytics_salt_v1'; // Change per installation

	/**
	 * Track entity view
	 *
	 * @param int    $entity_id Entity post ID
	 * @param string $visitor_id Anonymous visitor ID
	 * @return bool Success
	 */
	public static function track_view( int $entity_id, string $visitor_id ): bool {
		global $wpdb;

		// Respect Do Not Track header
		if ( self::is_dnt_enabled() ) {
			return false;
		}

		// Validate inputs
		if ( ! self::validate_entity_id( $entity_id ) ) {
			return false;
		}

		$visitor_id = sanitize_text_field( $visitor_id );
		if ( strlen( $visitor_id ) !== 36 ) { // UUID length
			return false;
		}

		$table = $wpdb->prefix . 'saga_view_log';

		// Check if already viewed in last hour (prevent spam)
		$recent_view = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
            WHERE entity_id = %d
            AND visitor_id = %s
            AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            LIMIT 1",
				$entity_id,
				$visitor_id
			)
		);

		if ( $recent_view ) {
			return false; // Already tracked recently
		}

		// Insert view log
		$inserted = $wpdb->insert(
			$table,
			array(
				'entity_id'       => $entity_id,
				'visitor_id'      => $visitor_id,
				'ip_hash'         => self::anonymize_ip( self::get_client_ip() ),
				'user_agent_hash' => self::anonymize_user_agent( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
				'viewed_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			// Update stats (increment total views)
			self::increment_total_views( $entity_id );

			// Check if unique visitor
			if ( self::is_unique_visitor( $entity_id, $visitor_id ) ) {
				self::increment_unique_views( $entity_id );
			}

			// Invalidate cache
			wp_cache_delete( "entity_stats_{$entity_id}", 'saga_analytics' );
		}

		return (bool) $inserted;
	}

	/**
	 * Track time spent on page
	 *
	 * @param int    $entity_id Entity post ID
	 * @param string $visitor_id Anonymous visitor ID
	 * @param int    $duration Duration in seconds
	 * @return bool Success
	 */
	public static function track_duration( int $entity_id, string $visitor_id, int $duration ): bool {
		global $wpdb;

		if ( ! self::validate_entity_id( $entity_id ) || $duration < 5 || $duration > 7200 ) {
			return false; // Ignore bounces and unrealistic durations
		}

		$table = $wpdb->prefix . 'saga_view_log';

		// Update most recent view for this visitor
		$updated = $wpdb->update(
			$table,
			array( 'time_on_page' => $duration ),
			array(
				'entity_id'  => $entity_id,
				'visitor_id' => sanitize_text_field( $visitor_id ),
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		if ( $updated ) {
			// Update average time on page
			self::update_avg_time_on_page( $entity_id );
			wp_cache_delete( "entity_stats_{$entity_id}", 'saga_analytics' );
		}

		return (bool) $updated;
	}

	/**
	 * Update bookmark count for entity
	 *
	 * @param int $entity_id Entity post ID
	 * @param int $delta Change amount (+1 or -1)
	 */
	public static function update_bookmark_count( int $entity_id, int $delta = 1 ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_entity_stats';

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (entity_id, bookmark_count, updated_at)
            VALUES (%d, %d, NOW())
            ON DUPLICATE KEY UPDATE
            bookmark_count = GREATEST(0, bookmark_count + %d),
            updated_at = NOW()",
				$entity_id,
				max( 0, $delta ),
				$delta
			)
		);

		wp_cache_delete( "entity_stats_{$entity_id}", 'saga_analytics' );
	}

	/**
	 * Update annotation count for entity
	 *
	 * @param int $entity_id Entity post ID
	 * @param int $delta Change amount (+1 or -1)
	 */
	public static function update_annotation_count( int $entity_id, int $delta = 1 ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_entity_stats';

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (entity_id, annotation_count, updated_at)
            VALUES (%d, %d, NOW())
            ON DUPLICATE KEY UPDATE
            annotation_count = GREATEST(0, annotation_count + %d),
            updated_at = NOW()",
				$entity_id,
				max( 0, $delta ),
				$delta
			)
		);

		wp_cache_delete( "entity_stats_{$entity_id}", 'saga_analytics' );
	}

	/**
	 * Get entity statistics
	 *
	 * @param int $entity_id Entity post ID
	 * @return array|null Stats array or null if not found
	 */
	public static function get_entity_stats( int $entity_id ): ?array {
		$cache_key = "entity_stats_{$entity_id}";
		$stats     = wp_cache_get( $cache_key, 'saga_analytics' );

		if ( false !== $stats ) {
			return $stats;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'saga_entity_stats';

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE entity_id = %d",
				$entity_id
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			return null;
		}

		wp_cache_set( $cache_key, $stats, 'saga_analytics', 300 ); // 5 min TTL
		return $stats;
	}

	/**
	 * Increment total views
	 */
	private static function increment_total_views( int $entity_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_entity_stats';

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (entity_id, total_views, last_viewed, updated_at)
            VALUES (%d, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            total_views = total_views + 1,
            last_viewed = NOW(),
            updated_at = NOW()",
				$entity_id
			)
		);
	}

	/**
	 * Increment unique views
	 */
	private static function increment_unique_views( int $entity_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_entity_stats';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
            SET unique_views = unique_views + 1,
                updated_at = NOW()
            WHERE entity_id = %d",
				$entity_id
			)
		);
	}

	/**
	 * Check if visitor is unique (first view)
	 */
	private static function is_unique_visitor( int $entity_id, string $visitor_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_view_log';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
            WHERE entity_id = %d AND visitor_id = %s",
				$entity_id,
				$visitor_id
			)
		);

		return (int) $count === 1; // Only the current view
	}

	/**
	 * Update average time on page
	 */
	private static function update_avg_time_on_page( int $entity_id ): void {
		global $wpdb;
		$log_table   = $wpdb->prefix . 'saga_view_log';
		$stats_table = $wpdb->prefix . 'saga_entity_stats';

		$avg_time = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(time_on_page) FROM {$log_table}
            WHERE entity_id = %d AND time_on_page IS NOT NULL",
				$entity_id
			)
		);

		if ( $avg_time !== null ) {
			$wpdb->update(
				$stats_table,
				array( 'avg_time_on_page' => (int) $avg_time ),
				array( 'entity_id' => $entity_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Anonymize IP address (GDPR compliant)
	 */
	private static function anonymize_ip( string $ip ): string {
		// Hash IP with salt
		return hash_hmac( 'sha256', $ip, self::SALT );
	}

	/**
	 * Anonymize user agent
	 */
	private static function anonymize_user_agent( string $user_agent ): string {
		// Extract only browser family and OS (no version fingerprinting)
		$parsed  = self::parse_user_agent( $user_agent );
		$generic = $parsed['browser'] . '_' . $parsed['os'];
		return hash( 'sha256', $generic );
	}

	/**
	 * Simple user agent parser
	 */
	private static function parse_user_agent( string $ua ): array {
		$browser = 'unknown';
		$os      = 'unknown';

		// Browser detection
		if ( strpos( $ua, 'Firefox' ) !== false ) {
			$browser = 'Firefox';
		} elseif ( strpos( $ua, 'Chrome' ) !== false ) {
			$browser = 'Chrome';
		} elseif ( strpos( $ua, 'Safari' ) !== false ) {
			$browser = 'Safari';
		} elseif ( strpos( $ua, 'Edge' ) !== false ) {
			$browser = 'Edge';
		}

		// OS detection
		if ( strpos( $ua, 'Windows' ) !== false ) {
			$os = 'Windows';
		} elseif ( strpos( $ua, 'Mac' ) !== false ) {
			$os = 'Mac';
		} elseif ( strpos( $ua, 'Linux' ) !== false ) {
			$os = 'Linux';
		} elseif ( strpos( $ua, 'Android' ) !== false ) {
			$os = 'Android';
		} elseif ( strpos( $ua, 'iOS' ) !== false ) {
			$os = 'iOS';
		}

		return array(
			'browser' => $browser,
			'os'      => $os,
		);
	}

	/**
	 * Get client IP address
	 */
	private static function get_client_ip(): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = $_SERVER[ $header ];
				// Get first IP if comma-separated list
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Check if Do Not Track is enabled
	 */
	private static function is_dnt_enabled(): bool {
		return isset( $_SERVER['HTTP_DNT'] ) && $_SERVER['HTTP_DNT'] === '1';
	}

	/**
	 * Validate entity ID exists
	 */
	private static function validate_entity_id( int $entity_id ): bool {
		return get_post_status( $entity_id ) !== false;
	}
}
