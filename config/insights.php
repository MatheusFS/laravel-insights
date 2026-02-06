<?php

return [

    // ========================================
    // BASIC CONFIGURATION
    // ========================================

    'routes_name' => 'insights.',
    'routes_prefix' => 'insights',

    'user_model' => App\Models\User::class,
    'users_table' => 'users',
    'ignore_models' => [
        Encore\Admin\Auth\Database\Administrator::class
    ],

    'middlewares' => ['web'],

    // ========================================
    // EVENT TRACKING FEATURES
    // ========================================

    'features' => [
        'pageviews' => true,
        'logins' => true,
        'authenticated_requests' => true,
        'searches' => false, // deprecated
    ],

    // ========================================
    // INCIDENT CORRELATION
    // ========================================

    'incident_correlation' => [
        'enabled' => true,

        // Storage path para arquivos JSON de incidentes
        'storage_path' => env('INSIGHTS_STORAGE_PATH', base_path('docs/software-management/reliability')),

        // AWS S3 Configuration for ALB Logs
        's3_bucket' => env('AWS_INCIDENT_S3_BUCKET', 'refresher-logs'),
        's3_path' => env('AWS_INCIDENT_S3_PATH', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1'),
        'aws_region' => env('AWS_REGION', 'us-east-1'),

        // IP Classification Thresholds
        'ip_classification' => [
            'malicious' => [
                'error_rate_min' => 0.95, // 95% de erros
                'volume_min' => 200,      // Mínimo de requests
            ],
            'suspicious' => [
                'error_rate_min' => 0.90, // 90% de erros
                'path_scanning_threshold' => 100, // Unique paths
            ],
        ],

        // Time Window para correlação
        'default_lookback_hours' => 24,

        // Storage path para incident files
        'storage_path' => storage_path('app/incidents'),
    ],

    // ========================================
    // DEVICE DETECTION
    // ========================================

    'device_detection' => [
        'enabled' => true,
        'library' => 'jenssegers/agent', // ou 'mobile-detect/mobile-detect'
        'cache_ttl' => 3600, // 1 hora
    ],

    // ========================================
    // ANOMALY DETECTION
    // ========================================

    'anomaly_detection' => [
        'enabled' => false, // v2.0 feature (não implementada)
        'thresholds' => [
            'new_ip_alert' => 5, // alertar se > 5 novos IPs em 1 hora
            'failed_login_spike' => 10, // alertar se > 10 falhas em 5 min
            'geolocation_change' => true, // alertar mudanças bruscas de geo
        ],
    ],

    // ========================================
    // RETENTION POLICIES
    // ========================================

    'retention' => [
        'pageviews_days' => 90,
        'logins_days' => 365,
        'authenticated_requests_days' => 90,
        'incident_logs_days' => 730, // 2 anos
    ],

    // ========================================
    // PERFORMANCE OPTIMIZATION
    // ========================================

    'performance' => [
        'batch_insert' => true,
        'batch_size' => 1000,
        'queue_writes' => false,
        'cache_queries' => true,
        'cache_ttl' => 300, // 5 minutos
    ],

    // ========================================
    // FOREIGN KEYS
    // ========================================

    'foreign_keys' => [
        'enabled' => false, // Recomendado false para performance
    ],

    // ========================================
    // SERVICE PROVIDERS
    // ========================================

    'providers' => [
        MatheusFS\Laravel\Insights\ServiceProvider::class,
        MatheusFS\Laravel\Insights\Providers\EventServiceProvider::class,
        MatheusFS\Laravel\Insights\Providers\BladeServiceProvider::class,
    ],
];