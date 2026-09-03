<?php

return [

    'shared_database' => (bool) env('SHARED_DATABASE', false),

    'base_url' => env('KUTUROGI_BASE_URL', 'http://localhost:8080'),

    'api_key' => env('KUTUROGI_API_KEY'),

    'webhook_secret' => env('KUTUROGI_WEBHOOK_SECRET'),

    'inbound_webhook_secret' => env('KUTUROGI_INBOUND_WEBHOOK_SECRET'),

    'timeout' => env('KUTUROGI_API_TIMEOUT', 10),

    'inventory_horizon_months' => (int) env('KUTUROGI_INVENTORY_HORIZON_MONTHS', 12),

    'endpoints' => [
        'inventories' => '/api/integration/inventories',
        'rooms' => '/api/integration/rooms',
        'plans' => '/api/integration/plans',
        'reservations' => '/api/integration/reservations',
        'users' => '/api/integration/users',
        'pricing_settings' => '/api/integration/pricing-settings',
    ],

];
