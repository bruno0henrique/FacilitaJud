<?php

return [
    'version' => '0.3.9',
    'trial_enabled' => (bool) env('TRIAL_ENABLED', true),
    'presentation_email' => env('PRESENTATION_EMAIL'),
    'demo' => (bool) env('DEMO_MODE', false),
    'demo_docker_loopback' => (bool) env('DEMO_DOCKER_LOOPBACK', false),
    'auth_provider' => env('AUTH_PROVIDER', 'neon'),
    'neon_url' => env('NEON_AUTH_BASE_URL'),
    'neon_jwks' => env('NEON_AUTH_JWKS_URL'),
    'neon_issuer' => env('NEON_AUTH_ISSUER'),
    'neon_audience' => env('NEON_AUTH_AUDIENCE'),
    'judit_enabled' => (bool) env('JUDIT_ENABLED', false),
    'document_storage' => env('DOCUMENT_STORAGE', 'database'),
];
