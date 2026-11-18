<?php

return [
    // 'anthropic' or 'gemini'. Leave empty to use whichever key is filled in.
    'provider' => env('OCR_PROVIDER'),

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('OCR_MODEL', 'claude-sonnet-5-5'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_OCR_MODEL', 'gemini-3.5-flash'),
    ],
];
