<?php

return [
    'throttling' => [
        'allow' => env('RESIZER_THROTTLING_ALLOW', 100),
        'every' => env('RESIZER_THROTTLING_EVERY', 10),
    ],

    'max-image-size' => 3000,
    'abandon-job-at' => '+1 hour',
];
