#!/bin/bash

# Script de teste rápido para análise de incidentes
# Usage: ./run-integration-test.sh [incident_id] [--force]

set -e

INCIDENT_ID="${1:-INC-2026-001}"
FORCE_FLAG="${2:-}"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🧪 Laravel Insights - Integration Test"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Incident ID: $INCIDENT_ID"
echo "Force redownload: ${FORCE_FLAG:-no}"
echo ""

# Verificar se está no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Must be run from package root directory"
    exit 1
fi

# Verificar se credenciais AWS estão configuradas
if [ -z "$AWS_ACCESS_KEY_ID" ]; then
    echo "⚠️  Warning: AWS_ACCESS_KEY_ID not set in environment"
    echo "   Make sure it's configured in .env"
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Option 1: Manual Test (Artisan Command)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "php artisan insights:test-incident $INCIDENT_ID $FORCE_FLAG"
echo ""
php artisan insights:test-incident $INCIDENT_ID $FORCE_FLAG
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Option 2: Automated Test (PHPUnit)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "vendor/bin/phpunit tests/Feature/S3LogDownloadIntegrationTest.php"
echo ""
vendor/bin/phpunit tests/Feature/S3LogDownloadIntegrationTest.php --testdox
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ All Tests Completed"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Check results:"
echo "• JSON file: storage/app/incidents/$INCIDENT_ID/alb_logs_analysis.json"
echo "• Raw logs: storage/app/incidents/.raw_logs/$INCIDENT_ID/"
echo ""
