#!/bin/bash
# Debug S3 download
set -ex

php artisan tinker --execute="
\$incidentData = [
    'started_at' => '2026-01-15T10:00:00Z',
    'restored_at' => '2026-01-15T10:30:00Z'
];

\$service = app(\MatheusFS\Laravel\Insights\Services\Infrastructure\S3LogDownloaderService::class);

try {
    \$result = \$service->downloadLogsForIncident(
        'INC-2026-001',
        \Carbon\Carbon::parse(\$incidentData['started_at']),
        \Carbon\Carbon::parse(\$incidentData['restored_at'])
    );
    
    dump('Download Result:', \$result);
} catch (\Exception \$e) {
    dump('Error:', \$e->getMessage(), \$e->getTraceAsString());
}
"
