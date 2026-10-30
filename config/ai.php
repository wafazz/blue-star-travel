<?php

/*
 * AI assistant configuration (see enhancement/design.md).
 *
 * Settings made in HQ > AI Assistant are stored in the `settings` table and take
 * precedence over these values. Environment values are only a fallback, so the
 * assistant can be configured without touching the database.
 *
 * Model names are NOT hardcoded for OpenAI: they change often and a stale ID fails at
 * runtime. Check each provider's pricing/models page before enabling it.
 */

return [

    // Kill switch default. Off until HQ turns it on.
    'enabled' => (bool) env('AI_ASSISTANT_ENABLED', false),

    // Order of providers tried for each message. Primary first, then fallbacks.
    'providers' => ['anthropic', 'openai', 'gemini'],

    'drivers' => [
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'api_key' => env('ANTHROPIC_API_KEY'),
            // Claude default per the claude-api skill: claude-opus-5-5.
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
            'timeout' => 25,
        ],
        'openai' => [
            'label' => 'OpenAI',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL'), // required: set in HQ settings after checking current IDs
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 25,
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 25,
        ],
    ],

    // Limits (editable in HQ settings).
    'limits' => [
        'per_customer_hour' => (int) env('AI_LIMIT_PER_CUSTOMER_HOUR', 10),
        'per_day_total' => (int) env('AI_LIMIT_PER_DAY_TOTAL', 200),
    ],

    // Max tokens the model may write for one reply. Keeps cost bounded.
    'max_reply_tokens' => 800,

    // Max characters a visitor can send in one message.
    'max_input_chars' => 500,
];
