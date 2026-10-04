<?php

return [
    // Enable on shared hosting where cron replaces a supervised queue worker.
    'cron_queue' => env('CPANEL_CRON_QUEUE', false),
];
