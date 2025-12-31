<?php
/**
 * Template Name: Timeline Demo
 * Template for demonstrating the WebGPU Infinite Zoom Timeline
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

get_header();
?>

<div class="saga-timeline-demo-page">

    <div class="demo-header">
        <div class="container">
            <h1>WebGPU Infinite Zoom Timeline</h1>
            <p class="lead">
                Experience the next-generation timeline visualization with GPU-accelerated infinite zoom.
                Navigate through cosmic timescales from millennia down to hours with buttery smooth 60 FPS performance.
            </p>
        </div>
    </div>

    <div class="demo-content">
        <div class="container">

            <!-- Instructions -->
            <div class="demo-instructions">
                <h2>How to Use</h2>
                <div class="instruction-grid">
                    <div class="instruction-card">
                        <div class="instruction-icon">🖱️</div>
                        <h3>Pan & Zoom</h3>
                        <p>Drag to pan the timeline. Scroll or pinch to zoom in and out.</p>
                    </div>
                    <div class="instruction-card">
                        <div class="instruction-icon">⌨️</div>
                        <h3>Keyboard</h3>
                        <p>Arrow keys to navigate. +/- to zoom. Ctrl+F to search.</p>
                    </div>
                    <div class="instruction-card">
                        <div class="instruction-icon">🔍</div>
                        <h3>Search</h3>
                        <p>Click the search icon to find specific events.</p>
                    </div>
                    <div class="instruction-card">
                        <div class="instruction-icon">🔖</div>
                        <h3>Bookmarks</h3>
                        <p>Save important moments with Ctrl+B.</p>
                    </div>
                </div>
            </div>

            <!-- Star Wars Timeline Example -->
            <div class="demo-timeline-section">
                <h2>Example: Star Wars Timeline</h2>
                <p>Events spanning from 32 BBY to 35 ABY</p>

                <?php
                // Get Star Wars saga (example)
                global $wpdb;
                $star_wars = $wpdb->get_row(
                    "SELECT id FROM {$wpdb->prefix}saga_sagas WHERE name LIKE '%Star Wars%' LIMIT 1"
                );

                if ($star_wars):
                    echo do_shortcode('[saga_timeline saga_id="' . $star_wars->id . '" height="700px" theme="dark"]');
                else:
                    echo '<div class="demo-placeholder">
                        <p>Star Wars saga not found. Please create a saga with timeline events to see this demo.</p>
                        <p><a href="' . admin_url('admin.php?page=saga-manager') . '" class="button">Create Saga</a></p>
                    </div>';
                endif;
                ?>
            </div>

            <!-- LOTR Timeline Example -->
            <div class="demo-timeline-section">
                <h2>Example: Lord of the Rings Timeline</h2>
                <p>Events across the Third Age of Middle-earth</p>

                <?php
                // Get LOTR saga (example)
                $lotr = $wpdb->get_row(
                    "SELECT id FROM {$wpdb->prefix}saga_sagas WHERE name LIKE '%Lord%Rings%' OR name LIKE '%Middle%earth%' LIMIT 1"
                );

                if ($lotr):
                    echo do_shortcode('[saga_timeline saga_id="' . $lotr->id . '" height="700px" theme="light"]');
                else:
                    echo '<div class="demo-placeholder">
                        <p>LOTR saga not found. Please create a saga with timeline events to see this demo.</p>
                        <p><a href="' . admin_url('admin.php?page=saga-manager') . '" class="button">Create Saga</a></p>
                    </div>';
                endif;
                ?>
            </div>

            <!-- Feature Highlights -->
            <div class="demo-features">
                <h2>Features</h2>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h3>⚡ GPU Accelerated</h3>
                        <p>WebGPU rendering for smooth 60 FPS with 10,000+ events</p>
                    </div>
                    <div class="feature-card">
                        <h3>🔄 Auto Fallback</h3>
                        <p>Automatic Canvas 2D fallback for older browsers</p>
                    </div>
                    <div class="feature-card">
                        <h3>📅 Custom Calendars</h3>
                        <p>Support for BBY/ABY, AG, Third Age, and more</p>
                    </div>
                    <div class="feature-card">
                        <h3>🎨 Beautiful Design</h3>
                        <p>Dark/light themes with stunning gradients</p>
                    </div>
                    <div class="feature-card">
                        <h3>♿ Accessible</h3>
                        <p>WCAG 2.1 AA compliant with screen reader support</p>
                    </div>
                    <div class="feature-card">
                        <h3>📱 Responsive</h3>
                        <p>Touch gestures on mobile devices</p>
                    </div>
                </div>
            </div>

            <!-- Technical Specs -->
            <div class="demo-tech-specs">
                <h2>Technical Specifications</h2>
                <div class="specs-grid">
                    <div class="spec-item">
                        <strong>Performance:</strong>
                        <span>60 FPS with 10,000+ events</span>
                    </div>
                    <div class="spec-item">
                        <strong>Zoom Range:</strong>
                        <span>0.0001× to 1000× (years to hours)</span>
                    </div>
                    <div class="spec-item">
                        <strong>Browser Support:</strong>
                        <span>Chrome 113+, Edge 113+, Firefox (with flag), Safari (fallback)</span>
                    </div>
                    <div class="spec-item">
                        <strong>Data Structure:</strong>
                        <span>Quadtree spatial indexing</span>
                    </div>
                    <div class="spec-item">
                        <strong>Rendering:</strong>
                        <span>WebGPU with Canvas 2D fallback</span>
                    </div>
                    <div class="spec-item">
                        <strong>Memory:</strong>
                        <span>Efficient culling of off-screen events</span>
                    </div>
                </div>
            </div>

            <!-- Quick Start Guide -->
            <div class="demo-quick-start">
                <h2>Quick Start</h2>
                <div class="code-example">
                    <h3>Basic Usage</h3>
                    <pre><code>[saga_timeline saga_id="1"]</code></pre>
                </div>

                <div class="code-example">
                    <h3>With Custom Options</h3>
                    <pre><code>[saga_timeline
    saga_id="1"
    height="800px"
    theme="dark"
    show_controls="true"
    show_minimap="true"
    initial_zoom="1"
]</code></pre>
                </div>

                <div class="code-example">
                    <h3>Programmatic Access</h3>
                    <pre><code>// Get timeline instance
const container = document.getElementById('saga-timeline-123');
const timeline = container.timelineInstance;

// Navigate to timestamp
timeline.goToTimestamp(1234567890, true);

// Zoom controls
timeline.zoomIn();
timeline.zoomOut();
timeline.fitToEvents();</code></pre>
                </div>
            </div>

            <!-- Browser Compatibility -->
            <div class="demo-browser-compat">
                <h2>Browser Compatibility</h2>
                <table class="compat-table">
                    <thead>
                        <tr>
                            <th>Browser</th>
                            <th>WebGPU Support</th>
                            <th>Canvas Fallback</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Chrome 113+</td>
                            <td>✅ Yes</td>
                            <td>✅ Yes</td>
                            <td><strong>Fully Supported</strong></td>
                        </tr>
                        <tr>
                            <td>Edge 113+</td>
                            <td>✅ Yes</td>
                            <td>✅ Yes</td>
                            <td><strong>Fully Supported</strong></td>
                        </tr>
                        <tr>
                            <td>Firefox 121+</td>
                            <td>🚧 Flag Required</td>
                            <td>✅ Yes</td>
                            <td><strong>Supported (Fallback)</strong></td>
                        </tr>
                        <tr>
                            <td>Safari</td>
                            <td>❌ Not Yet</td>
                            <td>✅ Yes</td>
                            <td><strong>Supported (Fallback)</strong></td>
                        </tr>
                        <tr>
                            <td>Mobile Chrome</td>
                            <td>✅ Yes (Android)</td>
                            <td>✅ Yes</td>
                            <td><strong>Fully Supported</strong></td>
                        </tr>
                        <tr>
                            <td>Mobile Safari</td>
                            <td>❌ Not Yet</td>
                            <td>✅ Yes</td>
                            <td><strong>Supported (Fallback)</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<style>
/* Demo page styles */
.saga-timeline-demo-page {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    min-height: 100vh;
    color: #fff;
}

.demo-header {
    padding: 60px 0;
    text-align: center;
    background: linear-gradient(135deg, #0f3460 0%, #533483 100%);
}

.demo-header h1 {
    font-size: 48px;
    margin: 0 0 16px 0;
    font-weight: 700;
    letter-spacing: -1px;
}

.demo-header .lead {
    font-size: 18px;
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.9);
}

.demo-content {
    padding: 60px 0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.demo-instructions {
    margin-bottom: 60px;
}

.demo-instructions h2 {
    font-size: 32px;
    margin-bottom: 24px;
    text-align: center;
}

.instruction-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 32px;
}

.instruction-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
}

.instruction-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.instruction-card h3 {
    font-size: 18px;
    margin: 0 0 8px 0;
}

.instruction-card p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.demo-timeline-section {
    margin-bottom: 80px;
}

.demo-timeline-section h2 {
    font-size: 32px;
    margin-bottom: 8px;
}

.demo-timeline-section > p {
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 24px;
}

.demo-placeholder {
    background: rgba(255, 255, 255, 0.05);
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 60px 40px;
    text-align: center;
}

.demo-features {
    margin-bottom: 60px;
}

.demo-features h2 {
    font-size: 32px;
    margin-bottom: 32px;
    text-align: center;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.feature-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 24px;
}

.feature-card h3 {
    font-size: 18px;
    margin: 0 0 8px 0;
    color: #e94560;
}

.feature-card p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.demo-tech-specs {
    margin-bottom: 60px;
}

.demo-tech-specs h2 {
    font-size: 32px;
    margin-bottom: 24px;
    text-align: center;
}

.specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
}

.spec-item {
    background: rgba(255, 255, 255, 0.05);
    border-left: 3px solid #e94560;
    padding: 16px;
    border-radius: 4px;
}

.spec-item strong {
    display: block;
    margin-bottom: 4px;
    color: #e94560;
}

.spec-item span {
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
}

.demo-quick-start {
    margin-bottom: 60px;
}

.demo-quick-start h2 {
    font-size: 32px;
    margin-bottom: 24px;
    text-align: center;
}

.code-example {
    margin-bottom: 24px;
}

.code-example h3 {
    font-size: 18px;
    margin-bottom: 8px;
    color: #e94560;
}

.code-example pre {
    background: #0a0e27;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 16px;
    overflow-x: auto;
}

.code-example code {
    color: #57cc99;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.5;
}

.demo-browser-compat {
    margin-bottom: 60px;
}

.demo-browser-compat h2 {
    font-size: 32px;
    margin-bottom: 24px;
    text-align: center;
}

.compat-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    overflow: hidden;
}

.compat-table th,
.compat-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.compat-table th {
    background: rgba(233, 69, 96, 0.2);
    font-weight: 600;
}

.compat-table tr:last-child td {
    border-bottom: none;
}
</style>

<?php
get_footer();
?>
