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
    // SRE METRICS & INCIDENT CORRELATION
    // (UNIFIED CONFIGURATION - ALB LOGS)
    // ========================================

    /**
     * UNIFIED ACCESS LOGS DIRECTORY
     * 
     * Todos os logs .log (independente de fonte, horário, incidente ou comando)
     * são baixados e descompactados NESTE DIRETÓRIO COMPARTILHADO.
     * 
     * Benefício: Reutilização de logs quando há intersecção de períodos
     * entre diferentes incidentes ou comandos (cache inteligente).
     * 
     * Estrutura:
     * {access_logs_path}/
     *   ├── alb_logs_2026_02_05_00.log      (descompactado de .gz)
     *   ├── alb_logs_2026_02_05_01.log
     *   └── ...
     */
    'access_logs_path' => env(
        'INSIGHTS_ACCESS_LOGS_PATH',
        storage_path('insights/access-logs')
    ),

    /**
     * INCIDENT ANALYSIS OUTPUT DIRECTORY
     * 
     * JSONs calculados a partir dos logs são salvos aqui, organizados por incidente.
     * Os JSONs são criados APÓS análise dos logs do access_logs_path.
     * 
     * Estrutura:
     * {incidents_path}/
     *   ├── INC-2026-001/
     *   │   ├── alb_logs_analysis.json      (resultado da análise de logs)
     *   │   ├── malicious_ips.json
     *   │   └── incident_impact.json
     *   ├── INC-2026-002/
     *   │   └── ...
     *   └── ...
     */
    'incidents_path' => env(
        'INSIGHTS_INCIDENTS_PATH',
        storage_path('insights/reliability/incidents')
    ),

    /**
     * SRE METRICS OUTPUT DIRECTORY
     * 
     * Métricas agregadas e calculadas a partir dos logs (não por incidente específico).
     * Usado para relatórios de SLA/SLO, uptime, etc.
     * 
     * Estrutura:
     * {sre_metrics_path}/
     *   ├── 2026-02/
     *   │   ├── 2026-02-01.json
     *   │   ├── 2026-02-02.json
     *   │   ├── ...
     *   │   └── monthly_aggregate.json
     *   ├── 2026-03/
     *   │   └── ...
     *   └── ...
     */
    'sre_metrics_path' => env(
        'INSIGHTS_SRE_METRICS_PATH',
        storage_path('insights/reliability/sre-metrics')
    ),

    // Configuração de fonte de logs ALB
    'alb_logs' => [
        // Fonte de logs ALB: 'local', 's3', ou 'cloudwatch'
        'source' => env('ALB_LOG_SOURCE', 's3'),
        
        // AWS S3 Configuration (sensível - bucket no .env)
        's3' => [
            'bucket' => env('AWS_ALB_LOGS_BUCKET', 'refresher-logs'),
            'path' => env('AWS_ALB_LOGS_PATH', 'AWSLogs/624082998591/elasticloadbalancing/us-east-1'),
            'region' => env('AWS_REGION', 'us-east-1'),
        ],
        
        // CloudWatch Configuration (se usar cloudwatch como source)
        'cloudwatch' => [
            'log_group' => env('AWS_CLOUDWATCH_LOG_GROUP', '/aws/elasticloadbalancing/refresher'),
            'region' => env('AWS_REGION', 'us-east-1'),
        ],
    ],

    // IP Classification Thresholds para incident correlation
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

    // User impact thresholds
    'user_impact' => [
        // Percentual de erro por usuário para marcar como crítico
        'critical_error_rate_min' => 10.0,
    ],

    // Time Window para correlação de incidentes
    'default_lookback_hours' => 24,

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