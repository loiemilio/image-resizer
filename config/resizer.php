<?php

return [
    'disk' => env('RESIZER_DISK', 'shared'),

    'throttling' => [
        'allow' => env('RESIZER_THROTTLING_ALLOW', 100),
        'every' => env('RESIZER_THROTTLING_EVERY', 10),
    ],

    'allowed-mimetypes' => [
        'image/png',
        'image/gif',
        'image/jpg',
        'image/jpeg',
    ],

    'max-image-size' => env('RESIZER_MAX_IMAGE_SIZE', 3000),
    'abandon-job-at' => env('RESIZER_MAX_JOB_LIFETIME', '+1 hour'),
];
