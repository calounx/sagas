<?php
/**
 * Entity Preview Card Template
 *
 * Template for rendering extracted entity cards.
 * This file defines the structure used by JavaScript to render entity cards.
 *
 * @package SagaManager
 * @subpackage Admin\Templates\Partials
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * This template is used as a reference by JavaScript.
 * The actual rendering is done via extraction-dashboard.js
 *
 * Entity Card Structure:
 * - Checkbox for bulk selection
 * - Entity type badge
 * - Canonical name
 * - Confidence score badge
 * - Description
 * - Attributes (key-value pairs)
 * - Context snippet
 * - Duplicate warning (if applicable)
 * - Approve/Reject buttons
 *
 * Entity data structure expected by JS:
 * {
 *   id: int,
 *   canonical_name: string,
 *   entity_type: string,
 *   description: string,
 *   attributes: object,
 *   context_snippet: string,
 *   confidence_score: float,
 *   status: string,
 *   is_duplicate: bool,
 *   duplicate_of: int|null,
 *   duplicate_similarity: float|null,
 *   duplicates: array
 * }
 */

// This function is called by JS to get the template structure
function saga_get_entity_card_template(): string {
    return <<<HTML
<div class="entity-card" data-entity-id="{{id}}" data-status="{{status}}">
    <div class="entity-card-header">
        <label class="entity-checkbox">
            <input type="checkbox" class="entity-select" value="{{id}}">
        </label>
        <span class="entity-type-badge entity-type-{{entity_type}}">{{entity_type}}</span>
        <span class="confidence-badge confidence-{{confidence_level}}">{{confidence_percent}}%</span>
    </div>

    <div class="entity-card-body">
        <h3 class="entity-name">{{canonical_name}}</h3>

        {{#if description}}
        <p class="entity-description">{{description}}</p>
        {{/if}}

        {{#if attributes}}
        <div class="entity-attributes">
            {{#each attributes}}
            <div class="attribute">
                <strong>{{key}}:</strong> {{value}}
            </div>
            {{/each}}
        </div>
        {{/if}}

        {{#if context_snippet}}
        <div class="entity-context">
            <strong>Context:</strong>
            <p class="context-snippet">{{context_snippet}}</p>
        </div>
        {{/if}}

        {{#if is_duplicate}}
        <div class="duplicate-warning">
            <span class="dashicons dashicons-warning"></span>
            <strong>Possible Duplicate</strong>
            <p>{{duplicate_similarity_percent}}% similar to: <strong>{{duplicate_name}}</strong></p>
            <button type="button" class="button button-small resolve-duplicate-btn" data-duplicate-id="{{duplicate_id}}">
                Resolve Duplicate
            </button>
        </div>
        {{/if}}

        {{#if duplicates}}
        <div class="duplicates-list">
            <strong>Potential Duplicates ({{duplicates_count}}):</strong>
            <ul>
                {{#each duplicates}}
                <li>
                    {{existing_entity_name}} ({{similarity_percent}}% similar)
                    <button type="button" class="button button-small resolve-duplicate-btn" data-duplicate-id="{{id}}">
                        Resolve
                    </button>
                </li>
                {{/each}}
            </ul>
        </div>
        {{/if}}
    </div>

    <div class="entity-card-footer">
        <button type="button" class="button button-primary approve-entity-btn" data-entity-id="{{id}}">
            Approve
        </button>
        <button type="button" class="button button-secondary reject-entity-btn" data-entity-id="{{id}}">
            Reject
        </button>
        <span class="entity-status-text">{{status}}</span>
    </div>
</div>
HTML;
}

// Expose template function for JS
if (is_admin()) {
    add_action('admin_footer', function() {
        // Template available in global scope for JS
        ?>
        <script type="text/template" id="entity-card-template">
            <?php echo saga_get_entity_card_template(); ?>
        </script>
        <?php
    });
}
