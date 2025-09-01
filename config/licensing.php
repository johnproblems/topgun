<?php

return [
    /*
    |--------------------------------------------------------------------------
    | License Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the enterprise licensing system
    |
    */

    'grace_period_days' => env('LICENSE_GRACE_PERIOD_DAYS', 7),

    'cache_ttl' => env('LICENSE_CACHE_TTL', 300), // 5 minutes

    'rate_limits' => [
        'basic' => [
            'max_attempts' => 1000,
            'decay_minutes' => 60,
        ],
        'professional' => [
            'max_attempts' => 5000,
            'decay_minutes' => 60,
        ],
        'enterprise' => [
            'max_attempts' => 10000,
            'decay_minutes' => 60,
        ],
    ],

    'features' => [
        'server_provisioning' => 'Server Provisioning',
        'infrastructure_provisioning' => 'Infrastructure Provisioning',
        'terraform_integration' => 'Terraform Integration',
        'payment_processing' => 'Payment Processing',
        'domain_management' => 'Domain Management',
        'white_label_branding' => 'White Label Branding',
        'api_access' => 'API Access',
        'bulk_operations' => 'Bulk Operations',
        'advanced_monitoring' => 'Advanced Monitoring',
        'multi_cloud_support' => 'Multi-Cloud Support',
        'sso_integration' => 'SSO Integration',
        'audit_logging' => 'Audit Logging',
        'backup_management' => 'Backup Management',
        'ssl_management' => 'SSL Management',
        'load_balancing' => 'Load Balancing',
    ],

    'default_limits' => [
        'basic' => [
            'max_servers' => 5,
            'max_applications' => 10,
            'max_domains' => 3,
            'max_users' => 3,
            'max_cloud_providers' => 1,
            'max_concurrent_provisioning' => 1,
        ],
        'professional' => [
            'max_servers' => 25,
            'max_applications' => 100,
            'max_domains' => 25,
            'max_users' => 10,
            'max_cloud_providers' => 3,
            'max_concurrent_provisioning' => 3,
        ],
        'enterprise' => [
            'max_servers' => null, // unlimited
            'max_applications' => null,
            'max_domains' => null,
            'max_users' => null,
            'max_cloud_providers' => null,
            'max_concurrent_provisioning' => 10,
        ],
    ],

    'default_features' => [
        'basic' => [
            'server_provisioning',
            'api_access',
        ],
        'professional' => [
            'server_provisioning',
            'infrastructure_provisioning',
            'terraform_integration',
            'payment_processing',
            'domain_management',
            'api_access',
            'bulk_operations',
            'ssl_management',
        ],
        'enterprise' => [
            'server_provisioning',
            'infrastructure_provisioning',
            'terraform_integration',
            'payment_processing',
            'domain_management',
            'white_label_branding',
            'api_access',
            'bulk_operations',
            'advanced_monitoring',
            'multi_cloud_support',
            'sso_integration',
            'audit_logging',
            'backup_management',
            'ssl_management',
            'load_balancing',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Critical Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Define which routes require specific license features
    |
    */

    'route_features' => [
        // Server management routes
        'servers.create' => ['server_provisioning'],
        'servers.store' => ['server_provisioning'],
        'servers.provision' => ['server_provisioning', 'infrastructure_provisioning'],

        // Infrastructure provisioning routes
        'infrastructure.*' => ['infrastructure_provisioning', 'terraform_integration'],
        'terraform.*' => ['terraform_integration'],
        'cloud-providers.*' => ['infrastructure_provisioning'],

        // Payment processing routes
        'payments.*' => ['payment_processing'],
        'billing.*' => ['payment_processing'],
        'subscriptions.*' => ['payment_processing'],

        // Domain management routes
        'domains.*' => ['domain_management'],
        'dns.*' => ['domain_management'],

        // White label routes
        'branding.*' => ['white_label_branding'],
        'white-label.*' => ['white_label_branding'],

        // Advanced features
        'monitoring.advanced' => ['advanced_monitoring'],
        'audit.*' => ['audit_logging'],
        'sso.*' => ['sso_integration'],
        'load-balancer.*' => ['load_balancing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which middleware to apply to different route groups
    |
    */

    'middleware_groups' => [
        'basic_license' => ['auth', 'license'],
        'api_license' => ['auth:sanctum', 'api.license'],
        'server_provisioning' => ['auth', 'license', 'server.provision'],
        'infrastructure' => ['auth', 'license:infrastructure_provisioning,terraform_integration'],
        'payments' => ['auth', 'license:payment_processing'],
        'domains' => ['auth', 'license:domain_management'],
        'white_label' => ['auth', 'license:white_label_branding'],
    ],
];
