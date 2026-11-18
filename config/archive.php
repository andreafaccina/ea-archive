<?php

return [
    'locales' => ['it' => 'Italiano', 'en' => 'English'],
    'default_locale' => env('APP_LOCALE', 'it'),
    'files_disk' => env('EA_FILESYSTEM_DISK', 'local'),
    'compiler_token' => env('COMPILER_API_TOKEN'),
    'lease_seconds' => 600,
    'max_attempts' => 3,
];
