<?php

return [
    'white_label' => [
        'cache_ttl' => env('WHITE_LABEL_CACHE_TTL', 3600),
        'sass_debug' => env('WHITE_LABEL_SASS_DEBUG', false),
        'default_theme' => [
            'primary_color' => '#3b82f6',
            'secondary_color' => '#1f2937',
            'accent_color' => '#10b981',
            'background_color' => '#ffffff',
            'text_color' => '#1f2937',
            'sidebar_color' => '#f9fafb',
            'border_color' => '#e5e7eb',
            'success_color' => '#10b981',
            'warning_color' => '#f59e0b',
            'error_color' => '#ef4444',
            'info_color' => '#3b82f6',
            'font_family' => 'Inter, sans-serif',
        ],
    ],
];
