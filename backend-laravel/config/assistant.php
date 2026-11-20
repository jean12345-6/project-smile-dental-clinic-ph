<?php

return [
    // Free-text questions that no rule matches are answered by the language model
    // when a key is present. With no key the assistant escalates to staff as before.
    'enabled' => env('ASSISTANT_AI', true),

    'provider' => env('ASSISTANT_PROVIDER'),   // 'anthropic' or 'gemini'; empty = whichever key is set

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ASSISTANT_MODEL', 'claude-sonnet-5-5'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('ASSISTANT_GEMINI_MODEL', 'gemini-3.5-flash'),
    ],

    // The reply must come back within this many seconds or the assistant escalates (NFR-03).
    'timeout' => (int) env('ASSISTANT_TIMEOUT', 9),
];
