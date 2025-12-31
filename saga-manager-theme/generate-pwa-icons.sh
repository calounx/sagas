#!/bin/bash
###
# PWA Icon Generator Script
#
# Generates placeholder PWA icons from a source image
# Requirements: ImageMagick (convert command)
#
# Usage:
#   ./generate-pwa-icons.sh [source-image.png]
#
# If no source image provided, creates placeholder SVG icons
###

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGES_DIR="$SCRIPT_DIR/assets/images"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}PWA Icon Generator${NC}"
echo "================================"

# Check if ImageMagick is installed
if ! command -v convert &> /dev/null; then
    echo -e "${YELLOW}Warning: ImageMagick not found. Installing placeholder SVG icons instead.${NC}"
    USE_SVG=true
else
    USE_SVG=false
fi

# Create images directory if it doesn't exist
mkdir -p "$IMAGES_DIR"

# Source image
SOURCE_IMAGE="$1"

if [ -z "$SOURCE_IMAGE" ] || [ ! -f "$SOURCE_IMAGE" ]; then
    echo -e "${YELLOW}No source image provided or file not found.${NC}"
    echo "Creating SVG placeholder icons..."
    USE_SVG=true
fi

# Function to create SVG icon
create_svg_icon() {
    local size=$1
    local output=$2

    cat > "$output" << EOF
<svg width="$size" height="$size" viewBox="0 0 $size $size" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="$size" height="$size" fill="url(#grad1)" rx="$(($size / 8))"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="$(($size / 2))" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">S</text>
</svg>
EOF

    # Convert SVG to PNG if ImageMagick is available
    if [ "$USE_SVG" = false ]; then
        convert "$output" "${output%.svg}.png"
        rm "$output"
        echo -e "${GREEN}✓${NC} Generated ${output%.svg}.png"
    else
        # Rename to PNG extension even though it's SVG (for compatibility)
        mv "$output" "${output%.svg}.png"
        echo -e "${GREEN}✓${NC} Generated ${output%.svg}.png (SVG placeholder)"
    fi
}

# Function to generate icon from source
generate_icon() {
    local size=$1
    local output=$2

    if [ "$USE_SVG" = true ]; then
        create_svg_icon "$size" "$output"
    else
        convert "$SOURCE_IMAGE" -resize "${size}x${size}" -background none -gravity center -extent "${size}x${size}" "$output"
        echo -e "${GREEN}✓${NC} Generated $output"
    fi
}

echo ""
echo "Generating standard icons..."

# Standard icons
generate_icon 72 "$IMAGES_DIR/icon-72.png"
generate_icon 96 "$IMAGES_DIR/icon-96.png"
generate_icon 128 "$IMAGES_DIR/icon-128.png"
generate_icon 144 "$IMAGES_DIR/icon-144.png"
generate_icon 152 "$IMAGES_DIR/icon-152.png"
generate_icon 192 "$IMAGES_DIR/icon-192.png"
generate_icon 384 "$IMAGES_DIR/icon-384.png"
generate_icon 512 "$IMAGES_DIR/icon-512.png"

echo ""
echo "Generating maskable icons..."

# Maskable icons (with safe zone padding)
if [ "$USE_SVG" = true ]; then
    create_svg_icon 192 "$IMAGES_DIR/icon-maskable-192.png"
    create_svg_icon 512 "$IMAGES_DIR/icon-maskable-512.png"
else
    # Add 20% padding for safe zone
    convert "$SOURCE_IMAGE" -resize 154x154 -background none -gravity center -extent 192x192 "$IMAGES_DIR/icon-maskable-192.png"
    convert "$SOURCE_IMAGE" -resize 410x410 -background none -gravity center -extent 512x512 "$IMAGES_DIR/icon-maskable-512.png"
    echo -e "${GREEN}✓${NC} Generated maskable icons"
fi

echo ""
echo "Generating badge icon..."

# Badge icon
generate_icon 72 "$IMAGES_DIR/badge-72.png"

echo ""
echo "Generating shortcut icons..."

# Shortcut icons (using same design for now)
generate_icon 96 "$IMAGES_DIR/shortcut-search.png"
generate_icon 96 "$IMAGES_DIR/shortcut-sagas.png"
generate_icon 96 "$IMAGES_DIR/shortcut-timeline.png"
generate_icon 96 "$IMAGES_DIR/shortcut-bookmarks.png"

echo ""
echo "Generating Apple splash screens..."

# Apple splash screens
if [ "$USE_SVG" = false ]; then
    generate_icon 640 "$IMAGES_DIR/splash-640x1136.png"
    generate_icon 750 "$IMAGES_DIR/splash-750x1334.png"
    generate_icon 828 "$IMAGES_DIR/splash-828x1792.png"
    generate_icon 1125 "$IMAGES_DIR/splash-1125x2436.png"
    generate_icon 1242 "$IMAGES_DIR/splash-1242x2208.png"
    generate_icon 1242 "$IMAGES_DIR/splash-1242x2688.png"
    generate_icon 1536 "$IMAGES_DIR/splash-1536x2048.png"
    generate_icon 1668 "$IMAGES_DIR/splash-1668x2388.png"
    generate_icon 2048 "$IMAGES_DIR/splash-2048x2732.png"
else
    echo -e "${YELLOW}Skipping splash screens (requires ImageMagick)${NC}"
fi

echo ""
echo "Generating screenshots..."

if [ "$USE_SVG" = false ]; then
    # Desktop screenshot placeholder
    convert -size 1280x720 xc:white \
        -font Arial -pointsize 48 -fill '#1f2937' \
        -gravity center -annotate +0+0 'Saga Manager\nDesktop View' \
        "$IMAGES_DIR/screenshot-desktop.png"

    # Mobile screenshot placeholder
    convert -size 750x1334 xc:white \
        -font Arial -pointsize 36 -fill '#1f2937' \
        -gravity center -annotate +0+0 'Saga Manager\nMobile View' \
        "$IMAGES_DIR/screenshot-mobile.png"

    echo -e "${GREEN}✓${NC} Generated screenshots"
else
    echo -e "${YELLOW}Skipping screenshots (requires ImageMagick)${NC}"
fi

echo ""
echo "Creating logo SVG..."

# Logo SVG
cat > "$IMAGES_DIR/logo.svg" << 'EOF'
<svg width="200" height="60" viewBox="0 0 200 60" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="logoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect x="5" y="10" width="40" height="40" fill="url(#logoGrad)" rx="8"/>
    <text x="25" y="35" font-family="Arial, sans-serif" font-size="30" font-weight="bold" fill="white" text-anchor="middle">S</text>
    <text x="55" y="40" font-family="Arial, sans-serif" font-size="28" font-weight="bold" fill="#1f2937">Saga Manager</text>
</svg>
EOF

echo -e "${GREEN}✓${NC} Generated logo.svg"

echo ""
echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}All icons generated successfully!${NC}"
echo ""

if [ "$USE_SVG" = true ]; then
    echo -e "${YELLOW}Note: Placeholder icons created.${NC}"
    echo "For production, run this script with a source image:"
    echo "  ./generate-pwa-icons.sh path/to/your-icon.png"
    echo ""
    echo "Recommended source image specifications:"
    echo "  - Size: 1024x1024 or larger"
    echo "  - Format: PNG with transparency"
    echo "  - Safe zone: 40% padding for maskable icons"
fi

echo ""
echo "Next steps:"
echo "1. Review generated icons in: $IMAGES_DIR"
echo "2. Replace placeholders with branded icons if needed"
echo "3. Test PWA installation on mobile devices"
echo "4. Verify icons appear correctly on all platforms"
