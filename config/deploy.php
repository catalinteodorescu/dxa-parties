<?php

// Deploy la cerere (GitHub Actions → FTP). Cheia secretă a rutei /deploy/{cheie}; fără ea ruta răspunde 404. Vezi docs/DEPLOY.md.
return [
    'key' => env('DEPLOY_KEY'),

    // Cât timp rulează deploy-ul se șterg cache-urile compilate din bootstrap/cache și storage/framework/views (dezactivat în teste).
    'clean_cache' => true,
];
