<?php

return [
    // Enable on shared hosting where cron replaces a supervised queue worker.
    'cron_queue' => env('CPANEL_CRON_QUEUE', false),

    // A validated parts import can contain 1,000 rows and is committed atomically.
    'import_timeout_seconds' => 300,
];
