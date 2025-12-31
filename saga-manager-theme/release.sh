#!/bin/bash

# Saga Manager Theme - Complete Release Script
# Validates version, runs checks, creates tag, and optionally pushes to trigger release

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Saga Manager Theme - Release${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# Check if version is provided
if [ -z "$1" ]; then
    # Try to get version from style.css
    VERSION=$(grep -oP '(?<=Version: )\d+\.\d+\.\d+' style.css || echo "")
    if [ -z "$VERSION" ]; then
        echo -e "${RED}Error: Could not determine version${NC}"
        echo "Usage: $0 <version>"
        echo "Example: $0 1.2.0"
        exit 1
    fi
    echo -e "${YELLOW}Using version from style.css: $VERSION${NC}"
else
    VERSION="$1"
fi

TAG_NAME="v${VERSION}"

# Validate version format
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}Error: Invalid version format${NC}"
    echo "Version must be in format: MAJOR.MINOR.PATCH (e.g., 1.2.0)"
    exit 1
fi

echo -e "${BLUE}Version:${NC} $VERSION"
echo -e "${BLUE}Tag:${NC} $TAG_NAME"
echo ""

# Check for uncommitted changes
echo -e "${YELLOW}[1/7]${NC} Checking working directory..."
if ! git diff-index --quiet HEAD --; then
    echo -e "${RED}Error: Uncommitted changes detected${NC}"
    git status --short
    exit 1
fi
echo -e "${GREEN}✓ Working directory clean${NC}"

# Check if tag exists
echo -e "${YELLOW}[2/7]${NC} Checking tag availability..."
if git rev-parse "$TAG_NAME" >/dev/null 2>&1; then
    echo -e "${RED}Error: Tag $TAG_NAME already exists${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Tag $TAG_NAME is available${NC}"

# Validate version in style.css
echo -e "${YELLOW}[3/7]${NC} Validating style.css..."
STYLE_VERSION=$(grep -oP '(?<=Version: )\d+\.\d+\.\d+' style.css || echo "")
if [ "$STYLE_VERSION" != "$VERSION" ]; then
    echo -e "${RED}Error: Version mismatch!${NC}"
    echo "  style.css: $STYLE_VERSION"
    echo "  Target:    $VERSION"
    exit 1
fi
echo -e "${GREEN}✓ style.css version: $STYLE_VERSION${NC}"

# Check CHANGELOG
echo -e "${YELLOW}[4/7]${NC} Checking CHANGELOG.md..."
if [ -f "CHANGELOG.md" ]; then
    if grep -q "\[${VERSION}\]" CHANGELOG.md; then
        echo -e "${GREEN}✓ CHANGELOG.md contains version $VERSION${NC}"
    else
        echo -e "${YELLOW}⚠ CHANGELOG.md missing version $VERSION${NC}"
    fi
else
    echo -e "${YELLOW}⚠ CHANGELOG.md not found${NC}"
fi

# PHP syntax check
echo -e "${YELLOW}[5/7]${NC} Running PHP syntax checks..."
PHP_ERRORS=0
while IFS= read -r -d '' file; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "${RED}✗ $file${NC}"
        PHP_ERRORS=$((PHP_ERRORS + 1))
    fi
done < <(find . -name "*.php" -not -path "./vendor/*" -not -path "./build/*" -print0)

if [ $PHP_ERRORS -gt 0 ]; then
    echo -e "${RED}Error: $PHP_ERRORS PHP syntax errors${NC}"
    exit 1
fi
echo -e "${GREEN}✓ All PHP files valid${NC}"

# Check required files
echo -e "${YELLOW}[6/7]${NC} Checking required files..."
REQUIRED=("style.css" "functions.php" "README.md")
for file in "${REQUIRED[@]}"; do
    if [ ! -f "$file" ]; then
        echo -e "${RED}✗ Missing: $file${NC}"
        exit 1
    fi
done
echo -e "${GREEN}✓ All required files present${NC}"

# File statistics
echo -e "${YELLOW}[7/7]${NC} Gathering statistics..."
PHP_COUNT=$(find . -name "*.php" -not -path "./vendor/*" -not -path "./build/*" | wc -l)
JS_COUNT=$(find . -name "*.js" -not -path "./node_modules/*" -not -path "./vendor/*" | wc -l)
CSS_COUNT=$(find . -name "*.css" -not -path "./node_modules/*" | wc -l)
echo -e "${GREEN}✓ Files: $PHP_COUNT PHP, $JS_COUNT JS, $CSS_COUNT CSS${NC}"

echo ""
echo -e "${BLUE}======================================${NC}"
echo -e "${GREEN}All checks passed!${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# Ask to create tag
read -p "Create tag $TAG_NAME? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "${YELLOW}Enter release description:${NC}"
    read -r DESCRIPTION

    if [ -z "$DESCRIPTION" ]; then
        DESCRIPTION="Release $TAG_NAME: Complete UX Feature Implementation"
    fi

    git tag -a "$TAG_NAME" -m "$DESCRIPTION"
    echo -e "${GREEN}✓ Tag created${NC}"

    read -p "Push tag to origin (triggers GitHub release)? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git push origin "$TAG_NAME"
        echo ""
        echo -e "${GREEN}✓ Tag pushed!${NC}"
        echo ""
        echo "GitHub Actions will now:"
        echo "  1. Build ZIP archive"
        echo "  2. Generate release notes"
        echo "  3. Create GitHub Release"
        echo "  4. Upload assets"
        echo ""
        echo "Monitor at: https://github.com/$(git config --get remote.origin.url | sed 's/.*://;s/.git$//')/actions"
    fi
fi

echo ""
echo -e "${GREEN}Done!${NC}"
