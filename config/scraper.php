<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scraper Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Playwright-based scraping service with Decodo proxy
    |
    */

    'service_url' => env('SCRAPER_SERVICE_URL', 'http://localhost:3000'),
    
    'timeout' => env('SCRAPER_SERVICE_TIMEOUT', 120),
    
    'use_queue' => env('SCRAPER_USE_QUEUE', true),
    
    'use_playwright' => env('USE_PLAYWRIGHT_SERVICE', false),

];

