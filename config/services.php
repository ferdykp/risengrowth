<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ollama' => [
        'enabled' => (bool) env('OLLAMA_ENABLED', false),
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL'),
    ],

    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'probe_daily_runs' => (int) env('APIFY_PROBE_DAILY_RUNS', 1),
        'probe_max_charge_usd' => (float) env('APIFY_PROBE_MAX_CHARGE_USD', 0.05),
        'monthly_budget_usd' => (float) env('APIFY_MONTHLY_BUDGET_USD', 4.50),
        'excluded_usernames' => array_values(array_filter(array_map('trim', explode(',', env('THREADS_EXCLUDED_USERNAMES', 'risencode.dev,risendev'))))),
    ],

    'comment_brand' => [
        'mention' => '@risendev',
        'website' => 'https://risencode.dev',
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
    ],

    'threads' => [
        'app_id' => env('THREADS_APP_ID'),
        'app_secret' => env('THREADS_APP_SECRET'),
        'redirect_uri' => env(
            'THREADS_REDIRECT_URI',
            'https://growth.risencode.dev/auth/threads/callback'
        ),
        'api_base_url' => env(
            'THREADS_API_BASE_URL',
            'https://graph.threads.net'
        ),
        'scopes' => [
            'threads_basic',
            'threads_keyword_search',
        ],
    ],

    'risengrowth_python' => [
        'sources' => base_path('scraper/sources.json'),
        'script' => base_path('scraper/main.py'),
        'bin' => env('RISENGROWTH_PYTHON', base_path('scraper/.venv/bin/python')),
    ],
];
