# Quick Release Guide

One-page reference for creating releases.

## Prerequisites

- [ ] All changes committed and pushed to main
- [ ] Version updated in `style.css`
- [ ] CHANGELOG.md updated with new version
- [ ] Tests passing locally

## Release Process (3 Steps)

### 1. Update Version

```bash
# Edit style.css
# Change: Version: 1.2.0 → Version: 1.2.1

# Edit CHANGELOG.md
# Add new section: ## [1.2.1] - YYYY-MM-DD

git add style.css CHANGELOG.md
git commit -m "chore: Bump version to 1.2.1"
git push origin main
```

### 2. Create Release

**Option A: Using release script (recommended)**
```bash
./release.sh
# Script will:
# - Auto-detect version from style.css
# - Run all validation checks
# - Create and push tag interactively
```

**Option B: Manual tag**
```bash
git tag -a theme/v1.2.1 -m "Release v1.2.1: Description here"
git push origin theme/v1.2.1
```

### 3. Monitor

```bash
# Check workflow status
gh run list --workflow=release.yml

# Or visit:
# https://github.com/calounx/sagas/actions
```

## Tag Format

**IMPORTANT:** Use `theme/v*.*.*` format

- ✅ `theme/v1.2.0`
- ✅ `theme/v1.2.1`
- ❌ `v1.2.0` (conflicts with plugin)
- ❌ `1.2.0` (won't trigger workflow)

## What Happens Automatically

1. GitHub Actions triggered on tag push
2. Version validated against style.css
3. ZIP archive created (excludes dev files)
4. Changelog extracted for release notes
5. GitHub Release created with:
   - Release notes
   - ZIP attachment
   - Auto-generated commit list
6. Artifact uploaded (90-day retention)

## Quick Checks

### Before Release
```bash
# Syntax check
find . -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# Version check
grep "^Version:" style.css

# Git status
git status
```

### After Release
```bash
# Verify tag exists
git tag -l theme/v1.2.1

# Check remote
git ls-remote --tags origin | grep theme/v1.2.1

# View release
gh release view theme/v1.2.1
```

## Troubleshooting

### Tag Already Exists
```bash
git push origin :refs/tags/theme/v1.2.1  # Delete remote
git tag -d theme/v1.2.1                  # Delete local
# Then recreate
```

### Workflow Failed
```bash
# Check logs
gh run list --workflow=release.yml --limit 1
gh run view <run-id>
```

### Wrong Version in ZIP
- Update style.css
- Commit and push
- Delete and recreate tag

## File Locations

| File | Purpose |
|------|---------|
| `.github/workflows/release.yml` | Release automation |
| `.github/workflows/build-test.yml` | CI testing |
| `CHANGELOG.md` | Version history |
| `.gitattributes` | Export exclusions |
| `release.sh` | Helper script |
| `prepare-release.sh` | Build script |

## Release Checklist

- [ ] Version in style.css updated
- [ ] CHANGELOG.md has new version section
- [ ] All changes committed and pushed
- [ ] Tag created with `theme/v*` format
- [ ] GitHub Actions workflow completed
- [ ] GitHub Release created
- [ ] ZIP file attached to release
- [ ] Release notes accurate

## Common Commands

```bash
# List all theme releases
git tag -l "theme/v*"

# Show latest release
git describe --tags --match "theme/v*" --abbrev=0

# View release details
git show theme/v1.2.0

# Test build locally
./prepare-release.sh

# View GitHub releases
gh release list

# Download latest release
gh release download theme/v1.2.0
```

## Support

- **Issues:** https://github.com/calounx/sagas/issues
- **Actions:** https://github.com/calounx/sagas/actions
- **Releases:** https://github.com/calounx/sagas/releases

---

**Last Updated:** 2025-12-31
