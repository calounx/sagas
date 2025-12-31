#!/bin/bash

set -e

echo "========================================="
echo "Saga Manager Theme - Release Preparation"
echo "========================================="

# Configuration
THEME_SLUG="saga-manager-theme"
ZIP_FILE="${THEME_SLUG}.zip"
BUILD_DIR="build"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored messages
print_status() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Get version from style.css
THEME_VERSION=$(grep "^Version:" style.css | awk '{print $2}' | tr -d '\r\n')

if [ -z "$THEME_VERSION" ]; then
    print_error "Could not extract version from style.css"
    exit 1
fi

print_status "Theme version: $THEME_VERSION"

# Clean previous build
if [ -d "$BUILD_DIR" ]; then
    print_status "Cleaning previous build directory..."
    rm -rf "$BUILD_DIR"
fi

if [ -f "$ZIP_FILE" ]; then
    print_status "Removing previous ZIP file..."
    rm -f "$ZIP_FILE"
fi

# Create build directory
print_status "Creating build directory..."
mkdir -p "$BUILD_DIR/$THEME_SLUG"

# Copy theme files using git archive (respects .gitattributes)
print_status "Copying theme files..."

# Check if we can use git archive (check if any files are tracked)
if git ls-tree -r HEAD --name-only . 2>/dev/null | grep -q .; then
    # Directory is tracked, use git archive
    git archive HEAD | tar -x -C "$BUILD_DIR/$THEME_SLUG"
else
    # Directory is untracked, use rsync/cp with exclusions
    print_warning "Directory not tracked in git, using manual copy..."

    # Use rsync if available, otherwise cp
    if command -v rsync >/dev/null 2>&1; then
        rsync -aq --exclude='.git' --exclude='node_modules' --exclude='build' \
              --exclude='.github' --exclude='prepare-release.sh' \
              --exclude='*.log' --exclude='.DS_Store' --exclude='Thumbs.db' \
              --exclude='example-*.php' --exclude='*.md' --exclude='docs' \
              --exclude='SCREENSHOT.txt' --exclude='generate-pwa-icons.sh' \
              . "$BUILD_DIR/$THEME_SLUG/"
    else
        cp -r . "$BUILD_DIR/$THEME_SLUG/"
    fi
fi

# Verify required files exist in build
print_status "Verifying required files..."
REQUIRED_FILES=(
    "style.css"
    "functions.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$BUILD_DIR/$THEME_SLUG/$file" ]; then
        print_error "Required file missing: $file"
        exit 1
    fi
done

print_status "All required files present"

# Remove development files that might have been copied
print_status "Removing development files..."
cd "$BUILD_DIR/$THEME_SLUG"

# Remove common development files
rm -rf \
    .git \
    .github \
    .gitignore \
    .gitattributes \
    node_modules \
    .DS_Store \
    Thumbs.db \
    .editorconfig \
    .eslintrc.js \
    .prettierrc \
    package-lock.json \
    yarn.lock \
    composer.lock \
    phpunit.xml \
    .phpcs.xml \
    tests \
    *.log \
    2>/dev/null || true

cd ../..

# Create ZIP file
print_status "Creating ZIP archive..."
cd "$BUILD_DIR"
zip -r -q "../$ZIP_FILE" "$THEME_SLUG" -x "*.DS_Store" "*/Thumbs.db"
cd ..

# Verify ZIP was created
if [ ! -f "$ZIP_FILE" ]; then
    print_error "Failed to create ZIP file"
    exit 1
fi

# Get ZIP file size
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
print_status "ZIP file created: $ZIP_FILE ($ZIP_SIZE)"

# Clean up build directory
print_status "Cleaning up build directory..."
rm -rf "$BUILD_DIR"

# Verify ZIP contents
print_status "Verifying ZIP contents..."

# Check that excluded files are not in ZIP
EXCLUDED_PATTERNS=(
    ".git/"
    ".github/"
    "node_modules/"
    "tests/"
    ".gitignore"
)

HAS_EXCLUDED=0
for pattern in "${EXCLUDED_PATTERNS[@]}"; do
    if unzip -l "$ZIP_FILE" | grep -q "$pattern"; then
        print_warning "ZIP contains excluded pattern: $pattern"
        HAS_EXCLUDED=1
    fi
done

if [ $HAS_EXCLUDED -eq 1 ]; then
    print_error "ZIP contains excluded files/directories"
    exit 1
fi

# Check that required files are in ZIP
MISSING_FILES=0
for file in "${REQUIRED_FILES[@]}"; do
    if ! unzip -l "$ZIP_FILE" | grep -q "$THEME_SLUG/$file"; then
        print_error "ZIP missing required file: $file"
        MISSING_FILES=1
    fi
done

if [ $MISSING_FILES -eq 1 ]; then
    exit 1
fi

print_status "ZIP verification passed"

# Display summary
echo ""
echo "========================================="
echo "Release Package Summary"
echo "========================================="
echo "Theme:    $THEME_SLUG"
echo "Version:  $THEME_VERSION"
echo "ZIP File: $ZIP_FILE"
echo "Size:     $ZIP_SIZE"
echo ""
echo "Package ready for release!"
echo "========================================="

exit 0
