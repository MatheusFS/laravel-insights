#!/bin/bash
php -d error_reporting=E_ALL -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$kernel = \$app->make(\Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

\$downloader = \$app->make(MatheusFS\Laravel\Insights\Services\Domain\S3ALBLogDownloader::class);

echo \"Testing log download for 2026-02-02...\n\";
try {
    \$result = \$downloader->downloadForDate(\Carbon\Carbon::parse('2026-02-02'), ['force' => true]);
    echo \"Success! Total requests API: \" . (\$result['by_request_type']['API']['total_requests'] ?? 0) . \"\n\";
    echo \"Errors 5xx API: \" . (\$result['by_request_type']['API']['errors_5xx'] ?? 0) . \"\n\";
} catch (\Exception \$e) {
    echo \"Error: \" . \$e->getMessage() . \"\n\";
}
"
