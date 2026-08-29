<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Bulk scoring runs on the cheap model; only the daily shortlist earns the
    | better one. Both are swappable without touching code.
    |
    */

    'models' => [
        'scoring' => env('VANTAGE_SCORING_MODEL', 'claude-haiku-4-5'),
        'analysis' => env('VANTAGE_ANALYSIS_MODEL', 'claude-sonnet-5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost control
    |--------------------------------------------------------------------------
    |
    | Scoring is dispatched through the Batch API at half price — a daily
    | digest has no latency requirement. The monthly ceiling is enforced before
    | dispatch, not after the bill arrives.
    |
    */

    'batch_api' => env('VANTAGE_USE_BATCH_API', true),

    'monthly_budget_usd' => (float) env('VANTAGE_MONTHLY_BUDGET_USD', 15),

    'deep_analysis_limit' => (int) env('VANTAGE_DEEP_ANALYSIS_LIMIT', 15),

    /*
    | USD per million tokens. Used to record actual spend against each score so
    | the dashboard shows real numbers rather than an estimate. Batch requests
    | bill at half these rates.
    */
    'pricing' => [
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
        'claude-sonnet-5' => ['input' => 2.00, 'output' => 10.00],
        'claude-opus-5' => ['input' => 5.00, 'output' => 25.00],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingestion
    |--------------------------------------------------------------------------
    |
    | Stage one is deterministic and free: work authorisation, seniority band,
    | stack, salary floor, posting age. Nothing reaches a model until it has
    | survived this, which is what keeps the bill at single digits.
    |
    */

    'ingestion' => [
        'max_posting_age_days' => 30,
        'dedupe_similarity_threshold' => 0.82,
        'per_source_page_limit' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Source credentials
    |--------------------------------------------------------------------------
    |
    | The ATS board adapters (Greenhouse, Lever, Ashby, Workable) need no
    | credentials at all. Leave an aggregator blank to disable that source.
    |
    */

    'sources' => [
        'adzuna' => [
            'app_id' => env('ADZUNA_APP_ID'),
            'app_key' => env('ADZUNA_APP_KEY'),
        ],
        'jooble' => [
            'api_key' => env('JOOBLE_API_KEY'),
        ],
        'jsearch' => [
            'rapidapi_key' => env('RAPIDAPI_KEY'),
        ],
        'mailbox' => [
            'secret' => env('INBOUND_MAIL_SECRET'),
        ],
    ],

];
