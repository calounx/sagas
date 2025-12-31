<?php
/**
 * Template Name: Galaxy Showcase
 *
 * Full-page template demonstrating the 3D Semantic Galaxy visualization
 * with multiple examples and interactive features.
 *
 * @package SagaManagerTheme
 * @version 1.3.0
 */

get_header();
?>

<style>
/* Custom styles for showcase page */
.galaxy-showcase {
    max-width: 100%;
    padding: 0;
}

.galaxy-hero {
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
    padding: 4rem 2rem;
    text-align: center;
    color: #fff;
}

.galaxy-hero h1 {
    font-size: 3rem;
    margin-bottom: 1rem;
    background: linear-gradient(90deg, #4488ff, #ff4488);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.galaxy-hero p {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.8);
    max-width: 800px;
    margin: 0 auto 2rem;
}

.galaxy-section {
    padding: 3rem 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.galaxy-section h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: #1a1a1a;
}

.galaxy-section:nth-child(even) {
    background: #f8f8f8;
}

.galaxy-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.galaxy-feature {
    padding: 1.5rem;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.galaxy-feature h3 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
    color: #4488ff;
}

.galaxy-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.galaxy-stat {
    padding: 1.5rem;
    background: linear-gradient(135deg, #4488ff 0%, #ff4488 100%);
    border-radius: 8px;
    text-align: center;
    color: #fff;
}

.galaxy-stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    display: block;
}

.galaxy-stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.galaxy-demo-controls {
    display: flex;
    gap: 1rem;
    margin: 2rem 0;
    flex-wrap: wrap;
}

.galaxy-demo-btn {
    padding: 0.75rem 1.5rem;
    background: #4488ff;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.galaxy-demo-btn:hover {
    background: #3377ee;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(68, 136, 255, 0.4);
}

.galaxy-demo-btn:active {
    transform: translateY(0);
}

.code-example {
    background: #1a1a1a;
    color: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1rem 0;
    overflow-x: auto;
}

.code-example code {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .galaxy-hero h1 {
        font-size: 2rem;
    }

    .galaxy-section {
        padding: 2rem 1rem;
    }
}
</style>

<div class="galaxy-showcase">

    <!-- Hero Section -->
    <div class="galaxy-hero">
        <h1>3D Semantic Galaxy</h1>
        <p>
            Explore fictional universes in an immersive 3D space. Navigate entity relationships
            with intuitive controls, discover hidden connections, and experience your saga like never before.
        </p>

        <?php if (get_query_var('saga_id')) : ?>
        <div class="galaxy-stats">
            <?php
            $saga_id = absint(get_query_var('saga_id'));
            $stats = saga_get_galaxy_stats($saga_id);

            if ($stats) :
            ?>
                <div class="galaxy-stat">
                    <span class="galaxy-stat-value"><?php echo number_format($stats['node_count']); ?></span>
                    <span class="galaxy-stat-label">Entities</span>
                </div>
                <div class="galaxy-stat">
                    <span class="galaxy-stat-value"><?php echo number_format($stats['link_count']); ?></span>
                    <span class="galaxy-stat-label">Relationships</span>
                </div>
                <div class="galaxy-stat">
                    <span class="galaxy-stat-value"><?php echo count($stats['entity_types']); ?></span>
                    <span class="galaxy-stat-label">Entity Types</span>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Galaxy Visualization -->
    <div class="galaxy-section">
        <h2>Interactive 3D Visualization</h2>
        <p>
            Use your mouse to rotate, zoom, and pan through the galaxy. Click on entities to view details,
            search for specific characters, or filter by entity type.
        </p>

        <div id="main-galaxy">
            <?php
            $saga_id = get_query_var('saga_id') ?: 1;
            echo do_shortcode('[saga_galaxy saga_id="' . absint($saga_id) . '" height="700"]');
            ?>
        </div>

        <!-- Interactive Controls -->
        <div class="galaxy-demo-controls">
            <button class="galaxy-demo-btn" id="demo-characters">Show Characters Only</button>
            <button class="galaxy-demo-btn" id="demo-locations">Show Locations Only</button>
            <button class="galaxy-demo-btn" id="demo-all">Show All</button>
            <button class="galaxy-demo-btn" id="demo-rotate">Toggle Auto-Rotate</button>
            <button class="galaxy-demo-btn" id="demo-reset">Reset View</button>
        </div>

        <script>
        (function($) {
            $(document).ready(function() {
                const container = $('#main-galaxy .saga-galaxy-container')[0];

                container.addEventListener('galaxy:graphCreated', function(e) {
                    const galaxy = e.detail.galaxy;

                    $('#demo-characters').on('click', function() {
                        galaxy.filterByType(['character']);
                    });

                    $('#demo-locations').on('click', function() {
                        galaxy.filterByType(['location']);
                    });

                    $('#demo-all').on('click', function() {
                        galaxy.filterByType([]);
                    });

                    $('#demo-rotate').on('click', function() {
                        galaxy.controls.autoRotate = !galaxy.controls.autoRotate;
                        $(this).toggleClass('active');
                    });

                    $('#demo-reset').on('click', function() {
                        galaxy.resetView();
                        galaxy.filterByType([]);
                    });
                });
            });
        })(jQuery);
        </script>
    </div>

    <!-- Features Section -->
    <div class="galaxy-section">
        <h2>Key Features</h2>

        <div class="galaxy-features">
            <div class="galaxy-feature">
                <h3>🌟 Force-Directed Layout</h3>
                <p>
                    Entities are positioned using physics-based simulation, creating natural
                    clusters and revealing relationship patterns.
                </p>
            </div>

            <div class="galaxy-feature">
                <h3>🎨 Color-Coded Types</h3>
                <p>
                    Different entity types are color-coded for easy identification:
                    Characters (blue), Locations (green), Events (orange), and more.
                </p>
            </div>

            <div class="galaxy-feature">
                <h3>🔍 Smart Search</h3>
                <p>
                    Find entities instantly with real-time search. Matching entities
                    are highlighted while others fade out.
                </p>
            </div>

            <div class="galaxy-feature">
                <h3>⚡ High Performance</h3>
                <p>
                    Optimized rendering handles 1000+ entities smoothly. Built with
                    Three.js for efficient WebGL-based graphics.
                </p>
            </div>

            <div class="galaxy-feature">
                <h3>♿ Accessible</h3>
                <p>
                    Full keyboard navigation, ARIA labels, and screen reader support.
                    Works with assistive technologies.
                </p>
            </div>

            <div class="galaxy-feature">
                <h3>📱 Responsive</h3>
                <p>
                    Touch controls for mobile devices. Pinch to zoom, drag to rotate.
                    Adapts to any screen size.
                </p>
            </div>
        </div>
    </div>

    <!-- Usage Examples -->
    <div class="galaxy-section">
        <h2>How to Use</h2>

        <div class="galaxy-features">
            <div class="galaxy-feature">
                <h3>Basic Shortcode</h3>
                <div class="code-example">
                    <code>[saga_galaxy saga_id="1"]</code>
                </div>
                <p>Add this shortcode to any page or post to display the galaxy visualization.</p>
            </div>

            <div class="galaxy-feature">
                <h3>Custom Height</h3>
                <div class="code-example">
                    <code>[saga_galaxy saga_id="1" height="800"]</code>
                </div>
                <p>Adjust the canvas height to fit your layout.</p>
            </div>

            <div class="galaxy-feature">
                <h3>Auto-Rotate</h3>
                <div class="code-example">
                    <code>[saga_galaxy saga_id="1" auto_rotate="true"]</code>
                </div>
                <p>Enable automatic rotation for presentations.</p>
            </div>

            <div class="galaxy-feature">
                <h3>Light Theme</h3>
                <div class="code-example">
                    <code>[saga_galaxy saga_id="1" theme="light"]</code>
                </div>
                <p>Use light theme for better visibility on light backgrounds.</p>
            </div>
        </div>
    </div>

    <!-- Keyboard Shortcuts -->
    <div class="galaxy-section">
        <h2>Keyboard Shortcuts</h2>

        <div class="galaxy-features">
            <div class="galaxy-feature">
                <h3>R</h3>
                <p>Reset camera to default view</p>
            </div>

            <div class="galaxy-feature">
                <h3>A</h3>
                <p>Toggle auto-rotation on/off</p>
            </div>

            <div class="galaxy-feature">
                <h3>Esc</h3>
                <p>Deselect currently selected entity</p>
            </div>

            <div class="galaxy-feature">
                <h3>?</h3>
                <p>Show keyboard shortcuts help</p>
            </div>
        </div>
    </div>

    <!-- Technical Details -->
    <div class="galaxy-section">
        <h2>Technical Details</h2>

        <div class="galaxy-features">
            <div class="galaxy-feature">
                <h3>Technology Stack</h3>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <li>Three.js (3D rendering)</li>
                    <li>WebGL (hardware acceleration)</li>
                    <li>Force-directed graph algorithm</li>
                    <li>WordPress REST API</li>
                </ul>
            </div>

            <div class="galaxy-feature">
                <h3>Browser Support</h3>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <li>Chrome/Edge 90+</li>
                    <li>Firefox 88+</li>
                    <li>Safari 14+</li>
                    <li>Mobile browsers (iOS/Android)</li>
                </ul>
            </div>

            <div class="galaxy-feature">
                <h3>Performance</h3>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <li>60+ FPS on desktop</li>
                    <li>30+ FPS on mobile</li>
                    <li>Handles 1000+ entities</li>
                    <li>Sub-50ms render time</li>
                </ul>
            </div>

            <div class="galaxy-feature">
                <h3>Data Sources</h3>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <li>Custom database tables</li>
                    <li>WordPress posts (fallback)</li>
                    <li>Cached with transients</li>
                    <li>Real-time updates</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="galaxy-section" style="text-align: center; background: linear-gradient(135deg, #4488ff 0%, #ff4488 100%); color: #fff;">
        <h2 style="color: #fff;">Ready to Explore?</h2>
        <p style="color: rgba(255, 255, 255, 0.9); font-size: 1.1rem;">
            Start visualizing your saga's entity relationships in 3D.
        </p>

        <?php if (current_user_can('edit_posts')) : ?>
        <div style="margin-top: 2rem;">
            <a href="<?php echo admin_url('post-new.php?post_type=saga_entity'); ?>"
               class="galaxy-demo-btn"
               style="display: inline-block; text-decoration: none; background: #fff; color: #4488ff;">
                Add Your First Entity
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Additional Examples -->
    <?php
    // Get other sagas to showcase
    $sagas = get_posts([
        'post_type' => 'saga',
        'posts_per_page' => 3,
        'post_status' => 'publish',
    ]);

    if (!empty($sagas)) :
    ?>
    <div class="galaxy-section">
        <h2>More Examples</h2>
        <p>Explore different saga universes:</p>

        <?php foreach ($sagas as $saga) : ?>
        <div style="margin: 2rem 0;">
            <h3><?php echo esc_html($saga->post_title); ?></h3>
            <?php echo do_shortcode('[saga_galaxy saga_id="' . $saga->ID . '" height="500"]'); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php
get_footer();
