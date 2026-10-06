<?php

return [
    // Bcrypt hash of the super admin password. Wrap it in single quotes in .env because it contains "$".
    // Leave empty to disable /suadmin entirely.
    'password_hash' => env('SUADMIN_PASSWORD_HASH', ''),

    // Sign the super admin out after this many minutes without a request.
    'idle_timeout_minutes' => (int) env('SUADMIN_IDLE_TIMEOUT_MINUTES', 30),

    // Hard limit on one super admin session regardless of activity.
    'max_session_minutes' => (int) env('SUADMIN_MAX_SESSION_MINUTES', 480),
];
