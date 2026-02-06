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

        // Storage path para arquivos JSON de incidentes (não-sensível, versionado)
        'storage_path' => env('INSIGHTS_STORAGE_PATH', base_path('docs/software-management/reliability')),

        // AWS S3 Configuration for ALB Logs (sensível - no .env)
        's3_bucket' => env('AWS_INCIDENT_S3_BUCKET', 'refresher-logs'),
        's3_path' => env('AWS_INCIDENT_S3_PATH', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1'),
        'aws_region' => 'us-east-1', // Públicamente conhecido

        // IP Classification Thresholds (não-sensível, versionado)
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

        // Time Window para correlação (não-sensível, versionado)
        'default_lookback_hours' => 24,
    ],

    // ========================================
    // SRE METRICS (CONTINUOUS ALB LOGS)
    // ========================================

    // Storage path para arquivos de SRE metrics (não-sensível, versionado)
    'sre_metrics_storage' => storage_path('app/sre_metrics'),

    // Configuração de fonte de logs ALB (não-sensível, versionado)
    'alb_logs' => [
        // Fonte de logs ALB para SRE metrics: 'local', 's3', ou 'cloudwatch'
        'source' => env('ALB_LOG_SOURCE', 's3'),
        
        // AWS S3 Configuration for SRE Metrics (sensível - bucket no .env)
        's3' => [
            'bucket' => env('AWS_ALB_LOGS_BUCKET', 'refresher-logs'),
            'path' => env('AWS_ALB_LOGS_PATH', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1'),
            'region' => 'us-east-1', // Públicamente conhecido
        ],
        
        // CloudWatch Configuration (se usar cloudwatch como source)
        'cloudwatch' => [
            'log_group' => env('AWS_CLOUDWATCH_LOG_GROUP', '/aws/elasticloadbalancing/refresher'),
            'region' => 'us-east-1',
        ],
    ],

    // SLO/SLA targets por tipo de serviço (não-sensível, versionado)
    'sre_targets' => [
        'API' => [
            'slo' => 98.5,      // SLO (meta operacional) em %
            'sla' => 95.0,      // SLA (compromisso contratual) em %
        ],
        'UI' => [
            'slo' => 98.0,      // SLO para UI (geralmente mais relaxado)
            'sla' => 95.0,      // SLA para UI
        ],
        'BOT' => [
            'slo' => 95.0,      // SLO para bots (mais lenientes)
            'sla' => 90.0,      // SLA para bots
        ],
        'ASSETS' => [
            'slo' => 99.5,      // SLO para assets (CDN/high availability)
            'sla' => 98.0,      // SLA para assets
        ],
    ],

    // Configurações de análise de logs (não-sensível, versionado)
    'alb_analysis' => [
        'enabled' => true,
        'batch_size' => 10000,        // Processar X linhas por lote
        'timeout_seconds' => 300,     // Timeout máximo por lote
        'exclude_bots' => [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'curl',
            'wget',
        ],
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