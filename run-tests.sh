#!/bin/bash

# Set memory limit for each PHP process
export PHP_INI_SCAN_DIR=""
export PHP_OPTIONS="-d memory_limit=512M"

# Run tests with increased memory limit
php -d memory_limit=512M artisan test --parallel --processes=4
