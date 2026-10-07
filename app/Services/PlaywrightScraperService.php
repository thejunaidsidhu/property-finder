<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlaywrightScraperService
{
    protected $serviceUrl;
    protected $timeout;

    public function __construct()
    {
        $this->serviceUrl = env('SCRAPER_SERVICE_URL', 'http://localhost:3000');
        $this->timeout = env('SCRAPER_SERVICE_TIMEOUT', 180); // Increased from 120 to 180 seconds (3 minutes)
    }

    /**
     * Scrape a URL using the Playwright service
     *
     * @param string $url
     * @param array $options
     * @param string|null $sessionId
     * @return array
     */
    public function scrape(string $url, array $options = [], ?string $sessionId = null): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->serviceUrl}/scrape", [
                    'url' => $url,
                    'options' => $options,
                    'sessionId' => $sessionId
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Playwright scraper success', [
                    'url' => $url,
                    'session_id' => $sessionId,
                    'proxy_used' => $data['metadata']['proxyUsed'] ?? null
                ]);

                return $data;
            }

            Log::error('Playwright scraper HTTP error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => "HTTP {$response->status()}: {$response->body()}"
            ];

        } catch (\Exception $e) {
            Log::error('Playwright scraper exception', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if service is healthy
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->serviceUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}

