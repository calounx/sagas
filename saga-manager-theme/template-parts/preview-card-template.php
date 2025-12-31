<?php
declare(strict_types=1);

/**
 * Preview Card Template
 *
 * Template for entity hover preview cards
 * This is rendered in PHP and used as a template by JavaScript
 *
 * @package SagaManagerTheme
 */
?>

<!-- Hidden template for JavaScript to clone -->
<template id="saga-preview-card-template">
    <div class="saga-preview-card" role="tooltip" aria-hidden="true">
        <div class="saga-preview-arrow"></div>
        <div class="saga-preview-content">
            <div class="saga-preview-loading">
                <div class="saga-preview-spinner"></div>
                <span class="saga-preview-loading-text">Loading...</span>
            </div>
            <div class="saga-preview-loaded" style="display: none;">
                <div class="saga-preview-header">
                    <div class="saga-preview-thumbnail">
                        <img src="" alt="" loading="lazy" />
                    </div>
                    <div class="saga-preview-header-text">
                        <h4 class="saga-preview-title"></h4>
                        <span class="saga-preview-type-badge"></span>
                    </div>
                </div>
                <div class="saga-preview-body">
                    <p class="saga-preview-excerpt"></p>
                    <dl class="saga-preview-attributes">
                        <!-- Attributes populated by JavaScript -->
                    </dl>
                </div>
                <div class="saga-preview-footer">
                    <a href="#" class="saga-preview-link">
                        View full details
                        <svg class="saga-preview-link-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 11L11 1M11 1H3M11 1V9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
