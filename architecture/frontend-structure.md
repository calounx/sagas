# Saga Manager Display - Frontend Plugin Structure

```
saga-manager-display/
├── saga-manager-display.php           # Main plugin file
├── uninstall.php                      # Cleanup on uninstall
├── composer.json
├── package.json                       # For Gutenberg blocks build
├── webpack.config.js
├── phpcs.xml
├── phpunit.xml
│
├── assets/
│   ├── css/
│   │   ├── frontend.css               # Main frontend styles
│   │   ├── entity-card.css            # Entity display component
│   │   ├── timeline.css               # Timeline display
│   │   ├── relationship-graph.css     # D3/Vis.js graph styles
│   │   └── search.css                 # Search results styling
│   │
│   ├── js/
│   │   ├── frontend.js                # Main frontend bundle
│   │   ├── search.js                  # AJAX search functionality
│   │   ├── timeline.js                # Interactive timeline
│   │   └── relationship-graph.js      # Graph visualization
│   │
│   └── images/
│       ├── entity-placeholders/       # Default entity images
│       └── icons/                     # UI icons
│
├── blocks/                            # Gutenberg blocks source
│   ├── entity-card/
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── save.js
│   │   ├── index.js
│   │   └── editor.scss
│   │
│   ├── entity-list/
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── save.js
│   │   ├── index.js
│   │   └── editor.scss
│   │
│   ├── timeline/
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── save.js
│   │   ├── index.js
│   │   └── editor.scss
│   │
│   ├── relationship-graph/
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── save.js
│   │   ├── index.js
│   │   └── editor.scss
│   │
│   ├── search-form/
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── save.js
│   │   ├── index.js
│   │   └── editor.scss
│   │
│   └── saga-archive/
│       ├── block.json
│       ├── edit.js
│       ├── save.js
│       ├── index.js
│       └── editor.scss
│
├── build/                             # Compiled block assets (gitignored)
│   └── ...
│
├── includes/
│   ├── class-activator.php
│   ├── class-deactivator.php
│   └── class-dependency-checker.php   # Verifies backend plugin
│
├── src/
│   ├── ApiClient/
│   │   ├── SagaApiClient.php          # REST API wrapper
│   │   ├── ApiResponse.php
│   │   ├── ApiException.php
│   │   ├── Endpoints/
│   │   │   ├── SagaEndpoint.php
│   │   │   ├── EntityEndpoint.php
│   │   │   ├── RelationshipEndpoint.php
│   │   │   ├── TimelineEndpoint.php
│   │   │   └── SearchEndpoint.php
│   │   │
│   │   └── Cache/
│   │       ├── ApiCacheInterface.php
│   │       └── TransientApiCache.php  # Caches API responses
│   │
│   ├── DTO/
│   │   ├── EntityDTO.php              # Mirrors backend DTO
│   │   ├── SagaDTO.php
│   │   ├── RelationshipDTO.php
│   │   ├── TimelineEventDTO.php
│   │   └── SearchResultDTO.php
│   │
│   ├── Shortcode/
│   │   ├── ShortcodeManager.php       # Registration
│   │   ├── AbstractShortcode.php      # Base class
│   │   ├── EntityShortcode.php        # [saga_entity id="123"]
│   │   ├── EntityListShortcode.php    # [saga_entities saga="1" type="character"]
│   │   ├── TimelineShortcode.php      # [saga_timeline saga="1"]
│   │   ├── RelationshipShortcode.php  # [saga_relationships entity="123"]
│   │   ├── SearchShortcode.php        # [saga_search saga="1"]
│   │   └── SagaArchiveShortcode.php   # [saga_archive]
│   │
│   ├── Block/
│   │   ├── BlockManager.php           # Block registration
│   │   ├── AbstractBlock.php
│   │   ├── EntityCardBlock.php
│   │   ├── EntityListBlock.php
│   │   ├── TimelineBlock.php
│   │   ├── RelationshipGraphBlock.php
│   │   ├── SearchFormBlock.php
│   │   └── SagaArchiveBlock.php
│   │
│   ├── Widget/
│   │   ├── WidgetManager.php
│   │   ├── RecentEntitiesWidget.php
│   │   ├── SagaListWidget.php
│   │   └── SearchWidget.php
│   │
│   ├── Template/
│   │   ├── TemplateLoader.php         # Template resolution
│   │   ├── TemplateRenderer.php       # Render with data
│   │   └── TemplateHooks.php          # Theme integration
│   │
│   └── Asset/
│       ├── AssetManager.php           # Script/style enqueueing
│       └── InlineDataManager.php      # wp_localize_script data
│
├── templates/
│   ├── shortcode/
│   │   ├── entity-single.php
│   │   ├── entity-list.php
│   │   ├── entity-card.php
│   │   ├── timeline.php
│   │   ├── timeline-event.php
│   │   ├── relationship-list.php
│   │   ├── relationship-graph.php
│   │   ├── search-form.php
│   │   ├── search-results.php
│   │   └── saga-archive.php
│   │
│   └── block/
│       ├── entity-card.php
│       ├── entity-list.php
│       ├── timeline.php
│       ├── relationship-graph.php
│       ├── search-form.php
│       └── saga-archive.php
│
├── languages/
│   └── saga-manager-display.pot
│
└── tests/
    ├── Unit/
    │   ├── ApiClient/
    │   └── Shortcode/
    │
    ├── Integration/
    │   ├── ApiIntegration/
    │   └── Block/
    │
    └── fixtures/
        └── mock-api-responses.php
```
