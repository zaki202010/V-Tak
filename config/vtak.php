<?php

return [
    // simulation | live  (live baru boleh dipakai setelah provider & gateway resmi diintegrasikan)
    'mode' => env('VTAK_MODE', 'simulation'),
    'provider' => env('VTAK_PROVIDER', 'simulation'),
    'webhook_secret' => env('VTAK_WEBHOOK_SECRET', ''),
];
