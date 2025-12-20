<?php

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use SagaManagerDisplay\ApiClient\SagaApiClient;

/**
 * Registers and manages all shortcodes
 */
final class ShortcodeManager
{
    private SagaApiClient $apiClient;

    /** @var AbstractShortcode[] */
    private array $shortcodes = [];

    public function __construct(SagaApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function register(): void
    {
        $this->shortcodes = [
            'saga_entity' => new EntityShortcode($this->apiClient),
            'saga_entities' => new EntityListShortcode($this->apiClient),
            'saga_timeline' => new TimelineShortcode($this->apiClient),
            'saga_relationships' => new RelationshipShortcode($this->apiClient),
            'saga_search' => new SearchShortcode($this->apiClient),
            'saga_archive' => new SagaArchiveShortcode($this->apiClient),
        ];

        foreach ($this->shortcodes as $tag => $shortcode) {
            add_shortcode($tag, [$shortcode, 'render']);
        }
    }

    public function getShortcode(string $tag): ?AbstractShortcode
    {
        return $this->shortcodes[$tag] ?? null;
    }
}
