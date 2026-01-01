#!/bin/bash
# Verification script for Entity Extractor implementation

echo "========================================="
echo "Entity Extractor Setup Verification"
echo "========================================="
echo ""

THEME_DIR="/home/calounx/repositories/sagas/saga-manager-theme"
ERRORS=0

# Check files exist
echo "Checking files..."
FILES=(
    "inc/ajax/extraction-ajax.php"
    "inc/admin/extraction-admin-init.php"
    "page-templates/admin-extraction-page.php"
    "page-templates/partials/extraction-preview.php"
    "assets/js/extraction-dashboard.js"
    "assets/css/extraction-dashboard.css"
)

for file in "${FILES[@]}"; do
    if [ -f "$THEME_DIR/$file" ]; then
        size=$(ls -lh "$THEME_DIR/$file" | awk '{print $5}')
        echo "✓ $file ($size)"
    else
        echo "✗ MISSING: $file"
        ((ERRORS++))
    fi
done

echo ""
echo "Checking functions.php integration..."
if grep -q "extraction-admin-init.php" "$THEME_DIR/functions.php"; then
    echo "✓ extraction-admin-init.php loaded in functions.php"
else
    echo "✗ extraction-admin-init.php NOT loaded in functions.php"
    ((ERRORS++))
fi

echo ""
echo "Checking AJAX endpoints..."
ENDPOINTS=(
    "saga_start_extraction"
    "saga_get_extraction_progress"
    "saga_load_extracted_entities"
    "saga_approve_entity"
    "saga_reject_entity"
    "saga_bulk_approve_entities"
    "saga_batch_create_approved"
    "saga_resolve_duplicate"
    "saga_load_job_history"
    "saga_cancel_extraction_job"
    "saga_get_extraction_stats"
    "saga_estimate_extraction_cost"
)

for endpoint in "${ENDPOINTS[@]}"; do
    if grep -q "'wp_ajax_$endpoint'" "$THEME_DIR/inc/ajax/extraction-ajax.php"; then
        echo "✓ $endpoint"
    else
        echo "✗ MISSING: $endpoint"
        ((ERRORS++))
    fi
done

echo ""
echo "Checking security features..."
if grep -q "check_ajax_referer" "$THEME_DIR/inc/ajax/extraction-ajax.php"; then
    echo "✓ Nonce verification implemented"
else
    echo "✗ Missing nonce verification"
    ((ERRORS++))
fi

if grep -q "current_user_can" "$THEME_DIR/inc/ajax/extraction-ajax.php"; then
    echo "✓ Capability checks implemented"
else
    echo "✗ Missing capability checks"
    ((ERRORS++))
fi

if grep -q "sanitize_text_field\|absint\|wp_kses_post" "$THEME_DIR/inc/ajax/extraction-ajax.php"; then
    echo "✓ Input sanitization implemented"
else
    echo "✗ Missing input sanitization"
    ((ERRORS++))
fi

echo ""
echo "========================================="
if [ $ERRORS -eq 0 ]; then
    echo "✓ ALL CHECKS PASSED!"
    echo "Entity Extractor is ready for use."
else
    echo "✗ FOUND $ERRORS ERRORS"
    echo "Please fix issues before using."
fi
echo "========================================="
