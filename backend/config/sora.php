<?php

// Server-side-only Sora configuration. Nothing in this file is ever sent to
// the frontend bundle; the private admin path is injected exclusively into
// the admin shell document rendered by AdminPanelController.

return [

    // Private URL segment under which the admin panel shell is served.
    // Leave empty to disable the shell entirely (all admin API endpoints
    // remain protected by authentication + role authorization regardless).
    'admin_path' => env('ADMIN_PATH'),

    // Vite dev server used to load frontend assets while developing.
    'vite_dev_url' => env('VITE_DEV_SERVER_URL', 'http://127.0.0.1:5173'),

    // Base URL of the public SPA. It is used server-side to compose the
    // consultant password setup link; the value never reaches the client
    // except inside the out-of-band setup URL itself.
    'frontend_url' => env('FRONTEND_URL'),
];
