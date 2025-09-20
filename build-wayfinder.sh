#!/bin/bash
set -e

echo "=== WAYFINDER GENERATION AND BUILD SCRIPT ==="
echo "Working directory: $(pwd)"
echo "User: $(whoami)"
echo "PHP version: $(php --version | head -1)"

echo "Step 1: Clear Laravel caches"
php artisan config:clear
php artisan route:clear

echo "Step 2: Verify environment setup"
echo "Laravel environment: $(php artisan env)"

echo "Step 3: Check if registration routes are available"
WAYFINDER_BUILD=true APP_ENV=local php artisan route:list --name=register || echo "No register routes found"

echo "Step 4: Generate wayfinder files"
WAYFINDER_BUILD=true APP_ENV=local php artisan wayfinder:generate --verbose --env=local

echo "Step 5: Verify actions directory was created"
if [ -d "resources/js/actions" ]; then
    echo "✓ Actions directory exists"
    ls -la resources/js/actions/
else
    echo "✗ Actions directory not found"
    exit 1
fi

echo "Step 6: Check for RegisteredUserController.ts specifically"
TARGET_FILE="resources/js/actions/App/Http/Controllers/Auth/RegisteredUserController.ts"
if [ -f "$TARGET_FILE" ]; then
    echo "✓ RegisteredUserController.ts found!"
    ls -la "$TARGET_FILE"
    echo "File size: $(wc -c < "$TARGET_FILE") bytes"
    echo "First few lines:"
    head -3 "$TARGET_FILE"
else
    echo "✗ RegisteredUserController.ts NOT FOUND!"
    echo "Auth directory contents:"
    ls -la resources/js/actions/App/Http/Controllers/Auth/ || echo "Auth directory doesn't exist"
    echo "All .ts files in actions:"
    find resources/js/actions -name "*.ts" | head -10
    exit 1
fi

echo "Step 7: Set proper permissions"
chmod -R 755 resources/js/actions

echo "Step 8: Final pre-build verification"
echo "RegisteredUserController.ts still exists: $(test -f "$TARGET_FILE" && echo "YES" || echo "NO")"

echo "Step 9: Start npm build"
npm run build

echo "=== BUILD COMPLETED SUCCESSFULLY ==="
