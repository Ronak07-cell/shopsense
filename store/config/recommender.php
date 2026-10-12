<?php

return [
    // Base URL of the Python recommender service.
    'url' => env('RECOMMENDER_URL', 'http://127.0.0.1:8001'),

    // Seconds to wait before giving up and showing best sellers instead.
    'timeout' => (float) env('RECOMMENDER_TIMEOUT', 2),

    // Seconds to cache each recommendation response.
    'cache_ttl' => (int) env('RECOMMENDER_CACHE_TTL', 300),
];
