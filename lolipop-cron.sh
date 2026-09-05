#!/bin/sh
cd "$(dirname "$0")"
/usr/local/bin/php8.3 artisan schedule:run >> /dev/null 2>&1
