#!/bin/bash
set -e
cd "$(dirname "$0")"

PORT="${1:-8080}"

if ! command -v php &> /dev/null; then
    echo "Error: PHP not installed"
    exit 1
fi

php -S 0.0.0.0:$PORT fota.php
