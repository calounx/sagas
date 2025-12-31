<?php
/**
 * Template Name: Relationship Graph Demo
 * Template Post Type: page
 *
 * Demonstrates the relationship graph feature
 *
 * @package SagaManager
 * @since 1.0.0
 */

get_header();
?>

<main id="primary" class="site-main">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>

            <section class="saga-graph-section">
                <h2>Interactive Relationship Graph</h2>
                <p>Explore entity relationships in an interactive force-directed graph. Use filters to focus on specific types, zoom and pan to navigate, and click nodes to visit entity pages.</p>

                <?php
                // Get entity ID from query parameter (if provided)
                $entity_id = isset($_GET['entity_id']) ? absint($_GET['entity_id']) : 0;

                // Display the graph
                echo do_shortcode(sprintf(
                    '[saga_relationship_graph entity_id="%d" depth="2" height="700"]',
                    $entity_id
                ));
                ?>
            </section>

            <section class="saga-graph-instructions" style="margin-top: 3rem; padding: 2rem; background: #f8f9fa; border-radius: 8px;">
                <h3>How to Use</h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 1.5rem;">
                    <div>
                        <h4>🖱️ Mouse Controls</h4>
                        <ul>
                            <li><strong>Click & Drag</strong>: Move nodes</li>
                            <li><strong>Scroll</strong>: Zoom in/out</li>
                            <li><strong>Click Node</strong>: Visit entity page</li>
                            <li><strong>Double-Click</strong>: Release fixed position</li>
                            <li><strong>Hover</strong>: Show entity preview</li>
                        </ul>
                    </div>

                    <div>
                        <h4>⌨️ Keyboard Controls</h4>
                        <ul>
                            <li><strong>Tab</strong>: Navigate between nodes</li>
                            <li><strong>Enter/Space</strong>: Visit entity page</li>
                            <li><strong>Escape</strong>: Clear selection</li>
                        </ul>
                    </div>

                    <div>
                        <h4>📱 Touch Controls</h4>
                        <ul>
                            <li><strong>Pinch</strong>: Zoom in/out</li>
                            <li><strong>Drag</strong>: Pan around</li>
                            <li><strong>Tap</strong>: Select node</li>
                            <li><strong>Double-tap</strong>: Visit page</li>
                        </ul>
                    </div>

                    <div>
                        <h4>🔧 Features</h4>
                        <ul>
                            <li><strong>Filters</strong>: Type & relationship filters</li>
                            <li><strong>Export</strong>: Save as PNG or SVG</li>
                            <li><strong>Fullscreen</strong>: Expand to full view</li>
                            <li><strong>Table View</strong>: Alternative accessible view</li>
                        </ul>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding: 1rem; background: white; border-left: 4px solid #0173B2;">
                    <h4>Accessibility</h4>
                    <p>This graph is fully accessible with keyboard navigation, screen reader support, and an alternative table view. Use the "View as Table" button below the graph to access the data in a non-visual format.</p>
                </div>
            </section>

            <section class="saga-graph-examples" style="margin-top: 3rem;">
                <h3>Shortcode Examples</h3>

                <div style="margin-top: 1.5rem;">
                    <h4>Basic Usage</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>[saga_relationship_graph]</code></pre>

                    <h4>Focus on Specific Entity</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>[saga_relationship_graph entity_id="123" depth="2"]</code></pre>

                    <h4>Filter by Type</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>[saga_relationship_graph entity_type="character" relationship_type="ally"]</code></pre>

                    <h4>Full Customization</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>[saga_relationship_graph
    entity_id="123"
    depth="2"
    entity_type="character"
    limit="50"
    height="800"
    show_filters="true"
    show_legend="true"
    show_table="true"
]</code></pre>
                </div>
            </section>

            <section class="saga-graph-api" style="margin-top: 3rem; padding: 2rem; background: #f8f9fa; border-radius: 8px;">
                <h3>REST API Examples</h3>

                <div style="margin-top: 1.5rem;">
                    <h4>Get Entity Relationships</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>GET <?php echo esc_url(rest_url('saga/v1/entities/123/relationships?depth=2&limit=100')); ?></code></pre>

                    <h4>Get All Entities</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>GET <?php echo esc_url(rest_url('saga/v1/graph/all?entity_type=character')); ?></code></pre>

                    <h4>Get Relationship Types</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>GET <?php echo esc_url(rest_url('saga/v1/graph/types')); ?></code></pre>

                    <div style="margin-top: 1.5rem;">
                        <a
                            href="<?php echo esc_url(rest_url('saga/v1/graph/types')); ?>"
                            target="_blank"
                            class="button button-primary"
                            style="display: inline-block; padding: 0.75rem 1.5rem; background: #0173B2; color: white; text-decoration: none; border-radius: 4px;"
                        >
                            Try API in Browser
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </article>
</main>

<?php
get_footer();
?>
