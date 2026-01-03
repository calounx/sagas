<?php
/**
 * AIClient
 *
 * OpenAI GPT-4 and Anthropic Claude API integration
 * Handles semantic consistency analysis using AI models
 *
 * @package SagaManager\AI
 * @version 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI;

use SagaManager\AI\Entities\ConsistencyIssue;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AIClient Class
 *
 * Manages AI API calls for consistency checking
 */
final class AIClient {

	/**
	 * @var string OpenAI API key
	 */
	private string $openaiKey;

	/**
	 * @var string Anthropic API key (fallback)
	 */
	private string $anthropicKey;

	/**
	 * @var string Primary provider (openai or anthropic)
	 */
	private string $provider;

	/**
	 * @var int Rate limit - max calls per hour
	 */
	private const RATE_LIMIT = 10;

	/**
	 * @var string Cache key prefix
	 */
	private const CACHE_PREFIX = 'saga_ai_consistency_';

	/**
	 * @var int Cache TTL (24 hours)
	 */
	private const CACHE_TTL = 86400;

	/**
	 * Constructor
	 *
	 * @param string $openaiKey    OpenAI API key
	 * @param string $anthropicKey Anthropic API key
	 * @param string $provider     Primary provider
	 */
	public function __construct(
		string $openaiKey = '',
		string $anthropicKey = '',
		string $provider = 'openai'
	) {
		$this->openaiKey    = $openaiKey ?: $this->getEncryptedOption( 'saga_ai_openai_key' );
		$this->anthropicKey = $anthropicKey ?: $this->getEncryptedOption( 'saga_ai_anthropic_key' );
		$this->provider     = $provider;
	}

	/**
	 * Analyze entities for consistency issues using AI
	 *
	 * @param int   $sagaId  Saga ID
	 * @param array $context Entity context data
	 * @return ConsistencyIssue[]
	 */
	public function analyzeConsistency( int $sagaId, array $context ): array {
		// Check rate limit
		if ( ! $this->checkRateLimit() ) {
			error_log( '[SAGA][AI] Rate limit exceeded for consistency checks' );
			return array();
		}

		// Check cache
		$cacheKey = $this->getCacheKey( $sagaId, $context );
		$cached   = get_transient( $cacheKey );

		if ( $cached !== false && is_array( $cached ) ) {
			return array_map( fn( $data ) => ConsistencyIssue::fromDatabase( $data ), $cached );
		}

		// Perform AI analysis
		try {
			$issues = $this->performAnalysis( $sagaId, $context );

			// Cache results
			$this->cacheResults( $cacheKey, $issues );

			// Increment rate limit counter
			$this->incrementRateLimit();

			return $issues;
		} catch ( \Exception $e ) {
			error_log( '[SAGA][AI][ERROR] AI analysis failed: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Perform actual AI analysis
	 *
	 * @param int   $sagaId  Saga ID
	 * @param array $context Context data
	 * @return ConsistencyIssue[]
	 */
	private function performAnalysis( int $sagaId, array $context ): array {
		$prompt = $this->buildPrompt( $context );

		if ( $this->provider === 'openai' && ! empty( $this->openaiKey ) ) {
			return $this->callOpenAI( $sagaId, $prompt );
		}

		if ( $this->provider === 'anthropic' && ! empty( $this->anthropicKey ) ) {
			return $this->callAnthropic( $sagaId, $prompt );
		}

		// Try fallback provider
		if ( ! empty( $this->anthropicKey ) ) {
			return $this->callAnthropic( $sagaId, $prompt );
		}

		if ( ! empty( $this->openaiKey ) ) {
			return $this->callOpenAI( $sagaId, $prompt );
		}

		throw new \RuntimeException( 'No AI provider configured' );
	}

	/**
	 * Call OpenAI GPT-4 API
	 *
	 * @param int    $sagaId Saga ID
	 * @param string $prompt Analysis prompt
	 * @return ConsistencyIssue[]
	 */
	private function callOpenAI( int $sagaId, string $prompt ): array {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->openaiKey,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'           => 'gpt-4-turbo-preview',
						'messages'        => array(
							array(
								'role'    => 'system',
								'content' => 'You are a consistency checker for fictional universe narratives. Analyze the provided entities and identify plot holes, timeline inconsistencies, character contradictions, and logical errors. Return results as JSON array.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature'     => 0.3,
						'response_format' => array( 'type' => 'json_object' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'OpenAI API error: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			throw new \RuntimeException( 'Invalid OpenAI API response' );
		}

		return $this->parseAIResponse( $sagaId, $data['choices'][0]['message']['content'] );
	}

	/**
	 * Call Anthropic Claude API
	 *
	 * @param int    $sagaId Saga ID
	 * @param string $prompt Analysis prompt
	 * @return ConsistencyIssue[]
	 */
	private function callAnthropic( int $sagaId, string $prompt ): array {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $this->anthropicKey,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'claude-3-sonnet-20240229',
						'max_tokens' => 4096,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Anthropic API error: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['content'][0]['text'] ) ) {
			throw new \RuntimeException( 'Invalid Anthropic API response' );
		}

		return $this->parseAIResponse( $sagaId, $data['content'][0]['text'] );
	}

	/**
	 * Build analysis prompt from context
	 *
	 * @param array $context Entity context
	 * @return string
	 */
	private function buildPrompt( array $context ): string {
		$entities      = $context['entities'] ?? array();
		$relationships = $context['relationships'] ?? array();
		$timeline      = $context['timeline'] ?? array();

		$prompt = "Analyze the following fictional universe data for consistency issues:\n\n";

		$prompt .= "## Entities\n";
		foreach ( $entities as $entity ) {
			$prompt .= sprintf(
				"- %s (%s): %s\n",
				$entity['name'],
				$entity['type'],
				$entity['description'] ?? ''
			);
		}

		$prompt .= "\n## Relationships\n";
		foreach ( $relationships as $rel ) {
			$prompt .= sprintf(
				"- %s → %s (%s)\n",
				$rel['source'],
				$rel['target'],
				$rel['type']
			);
		}

		$prompt .= "\n## Timeline Events\n";
		foreach ( $timeline as $event ) {
			$prompt .= sprintf(
				"- %s: %s\n",
				$event['date'],
				$event['description']
			);
		}

		$prompt .= "\n## Task\n";
		$prompt .= "Identify consistency issues and return JSON with this structure:\n";
		$prompt .= "{\n";
		$prompt .= '  "issues": [';
		$prompt .= "\n    {";
		$prompt .= "\n      \"type\": \"timeline|character|location|relationship|logical\",";
		$prompt .= "\n      \"severity\": \"critical|high|medium|low|info\",";
		$prompt .= "\n      \"description\": \"Issue description\",";
		$prompt .= "\n      \"entity\": \"Entity name (optional)\",";
		$prompt .= "\n      \"suggested_fix\": \"How to fix this issue\",";
		$prompt .= "\n      \"confidence\": 0.95";
		$prompt .= "\n    }";
		$prompt .= "\n  ]";
		$prompt .= "\n}";

		return $prompt;
	}

	/**
	 * Parse AI response into ConsistencyIssue objects
	 *
	 * @param int    $sagaId  Saga ID
	 * @param string $response AI response JSON
	 * @return ConsistencyIssue[]
	 */
	private function parseAIResponse( int $sagaId, string $response ): array {
		$data = json_decode( $response, true );

		if ( ! isset( $data['issues'] ) || ! is_array( $data['issues'] ) ) {
			return array();
		}

		$issues = array();

		foreach ( $data['issues'] as $issueData ) {
			try {
				$issues[] = new ConsistencyIssue(
					id: null,
					sagaId: $sagaId,
					issueType: $issueData['type'] ?? 'logical',
					severity: $issueData['severity'] ?? 'medium',
					entityId: null, // AI doesn't know entity IDs
					relatedEntityId: null,
					description: $issueData['description'] ?? '',
					context: array(
						'entity_name' => $issueData['entity'] ?? null,
						'ai_analysis' => true,
					),
					suggestedFix: $issueData['suggested_fix'] ?? null,
					aiConfidence: isset( $issueData['confidence'] ) ? (float) $issueData['confidence'] : 0.75
				);
			} catch ( \Exception $e ) {
				error_log( '[SAGA][AI] Failed to create issue from AI response: ' . $e->getMessage() );
			}
		}

		return $issues;
	}

	/**
	 * Check if rate limit allows more requests
	 *
	 * @return bool
	 */
	private function checkRateLimit(): bool {
		$key   = 'saga_ai_rate_limit_' . get_current_user_id();
		$count = get_transient( $key );

		if ( $count === false ) {
			return true;
		}

		return (int) $count < self::RATE_LIMIT;
	}

	/**
	 * Increment rate limit counter
	 *
	 * @return void
	 */
	private function incrementRateLimit(): void {
		$key   = 'saga_ai_rate_limit_' . get_current_user_id();
		$count = get_transient( $key );

		if ( $count === false ) {
			set_transient( $key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $key, (int) $count + 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Get cache key for context
	 *
	 * @param int   $sagaId  Saga ID
	 * @param array $context Context data
	 * @return string
	 */
	private function getCacheKey( int $sagaId, array $context ): string {
		return self::CACHE_PREFIX . $sagaId . '_' . md5( wp_json_encode( $context ) );
	}

	/**
	 * Cache AI results
	 *
	 * @param string             $key    Cache key
	 * @param ConsistencyIssue[] $issues Issues
	 * @return void
	 */
	private function cacheResults( string $key, array $issues ): void {
		$data = array_map( fn( $issue ) => $issue->toArray(), $issues );
		set_transient( $key, $data, self::CACHE_TTL );
	}

	/**
	 * Get encrypted option value
	 *
	 * @param string $optionName Option name
	 * @return string
	 */
	private function getEncryptedOption( string $optionName ): string {
		$encrypted = get_option( $optionName, '' );

		if ( empty( $encrypted ) ) {
			return '';
		}

		// Simple encryption using WordPress salts
		// In production, use proper encryption library
		return $this->decrypt( $encrypted );
	}

	/**
	 * Decrypt value using WordPress salts
	 *
	 * @param string $encrypted Encrypted value
	 * @return string
	 */
	private function decrypt( string $encrypted ): string {
		// Simplified decryption - in production use sodium_crypto_secretbox_open
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) {
			return '';
		}

		$key     = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY );
		$decoded = base64_decode( $encrypted );

		if ( $decoded === false ) {
			return '';
		}

		// Extract IV and ciphertext
		$ivLength = 16;
		if ( strlen( $decoded ) < $ivLength ) {
			return '';
		}

		$iv         = substr( $decoded, 0, $ivLength );
		$ciphertext = substr( $decoded, $ivLength );

		$decrypted = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return $decrypted !== false ? $decrypted : '';
	}

	/**
	 * Encrypt value using WordPress salts
	 *
	 * @param string $value Value to encrypt
	 * @return string
	 */
	public static function encrypt( string $value ): string {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) {
			return '';
		}

		$key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY );
		$iv  = openssl_random_pseudo_bytes( 16 );

		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( $encrypted === false ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}
}
