#!/bin/bash
# Clean test runner (suppresses external library deprecations)
docker exec laravel-insights-fpm-1 bash -c 'php -d display_errors=0 -d error_reporting=0 ./vendor/bin/phpunit "$@"' -- "$@"
