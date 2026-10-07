<?php

namespace App\Jobs;

use App\Services\PlaywrightScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScrapePropertyGuruJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // Increased to 5 minutes for long-running scraping operations
    public $tries = 3;

    protected $clientData;
    protected $jsonFile;
    protected $maxPages;

    public function __construct(array $clientData, string $jsonFile, int $maxPages = 3)
    {
        $this->clientData = $clientData;
        $this->jsonFile = $jsonFile;
        $this->maxPages = $maxPages;
    }

    public function handle(PlaywrightScraperService $scraper)
    {
        $clientName = $this->clientData['client_name'];
        $maxPrice = (int) ($this->clientData['price'] ?? 999999999);
        $minSize = (int) ($this->clientData['land_size'] ?? 0);
        $bedrooms = (int) ($this->clientData['bedrooms'] ?? 0);
        $district = $this->clientData['district'] ?? null;
        $slug = $district ?: 'Singapore';
        $districtCode = $this->convertDistrictToCode($district);

        Log::info("Starting PropertyGuru scrape job", [
            'client' => $clientName,
            'json_file' => $this->jsonFile
        ]);

        // Build URL for first page
        $url = $this->buildPropertyGuruUrl($slug, 1, [
            'max_price' => $maxPrice,
            'min_size' => $minSize,
            'bedrooms' => $bedrooms,
            'district_code' => $districtCode,
            'tenure' => $this->clientData['tenure'] ?? null,
            'house_type' => $this->clientData['house_type'] ?? null,
        ]);

        // Generate session ID for sticky proxy session
        $sessionId = "pg-" . Str::slug($clientName) . "-" . time();

        // Scrape first page
        $result = $scraper->scrape($url, [
            'waitForSelector' => '#__NEXT_DATA__',
            'extractScript' => '(() => { const script = document.querySelector("#__NEXT_DATA__"); return script ? JSON.parse(script.textContent) : null; })()'
        ], $sessionId);

        if (!$result['success']) {
            throw new \Exception("Scraping failed: " . ($result['error'] ?? 'Unknown error'));
        }

        // Extract listings from __NEXT_DATA__
        $nextData = $result['json'] ?? null;
        if (!$nextData) {
            throw new \Exception("__NEXT_DATA__ not found in response");
        }

        $allListings = [];
        $pageListings = $this->extractListings($nextData);
        $allListings[] = $pageListings;

        // Detect pagination
        $json = json_encode($nextData);
        preg_match('/"resultCount":\s*(\d+)/', $json, $m1);
        preg_match('/"limit":\s*(\d+)/', $json, $m2);
        $resultCount = $m1[1] ?? null;
        $perPage = $m2[1] ?? 20;
        $totalPages = $resultCount ? ceil($resultCount / $perPage) : 1;
        if ($this->maxPages > 0) {
            $totalPages = min($totalPages, $this->maxPages);
        }

        Log::info("PropertyGuru pagination detected", [
            'total_pages' => $totalPages,
            'result_count' => $resultCount,
            'per_page' => $perPage
        ]);

        // Scrape remaining pages
        for ($p = 2; $p <= $totalPages; $p++) {
            // Add delay between page fetches
            usleep(500000); // 0.5 seconds

            $pageUrl = $this->buildPropertyGuruUrl($slug, $p, [
                'max_price' => $maxPrice,
                'min_size' => $minSize,
                'bedrooms' => $bedrooms,
                'district_code' => $districtCode,
                'tenure' => $this->clientData['tenure'] ?? null,
                'house_type' => $this->clientData['house_type'] ?? null,
            ]);

            Log::info("Scraping page {$p}", ['url' => $pageUrl]);

            $pageResult = $scraper->scrape($pageUrl, [
                'waitForSelector' => '#__NEXT_DATA__',
                'extractScript' => '(() => { const script = document.querySelector("#__NEXT_DATA__"); return script ? JSON.parse(script.textContent) : null; })()'
            ], $sessionId);

            if (!$pageResult['success']) {
                Log::warning("Failed to scrape page {$p}, stopping pagination", [
                    'error' => $pageResult['error'] ?? 'Unknown error'
                ]);
                break;
            }

            $pageNextData = $pageResult['json'] ?? null;
            if (!$pageNextData) {
                Log::warning("No __NEXT_DATA__ found on page {$p}, stopping pagination");
                break;
            }

            $pageListings = $this->extractListings($pageNextData);
            $allListings[] = $pageListings;
        }

        // Merge listings (deduplicate by listingId)
        $merged = [];
        foreach ($allListings as $pageIndex => $chunk) {
            foreach ($chunk as $idx => $item) {
                $lid = $item['listingId'] ?? $item['id'] ?? null;
                $key = $lid ?: md5(json_encode($item) . "_{$pageIndex}_{$idx}");
                $merged[$key] = $item;
            }
        }

        $final = array_values($merged);

        // Save to file
        $finalData = [
            'fetched_at' => date('c'),
            'slug' => $slug,
            'max_price' => $maxPrice,
            'min_size' => $minSize,
            'bedrooms' => $bedrooms,
            'tenure' => $this->clientData['tenure'] ?? null,
            'house_type' => $this->clientData['house_type'] ?? null,
            'collected' => count($final),
            'listings' => $final
        ];

        file_put_contents(
            storage_path("app/{$this->jsonFile}"),
            json_encode($finalData, JSON_PRETTY_PRINT)
        );

        Log::info("PropertyGuru scrape job completed", [
            'client' => $clientName,
            'listings_count' => count($final),
            'json_file' => $this->jsonFile
        ]);
    }

    /**
     * Build PropertyGuru URL
     */
    protected function buildPropertyGuruUrl(string $slug, int $page = 1, array $filters = []): string
    {
        $baseUrl = "https://www.propertyguru.com.sg/property-for-sale";
        
        // Add page number to path
        if ($page > 1) {
            $baseUrl .= '/' . $page;
        }
        
        // PropertyGuru uses specific array-style parameters
        $params = [
            '_freetextDisplay' => $slug,
            'freetext' => $slug,
            'maxprice' => $filters['max_price'] ?? 999999999,
        ];
        
        if (!empty($filters['district_code'])) {
            $params['district_code[]'] = "D" . $filters['district_code'];
        }
        
        if (!empty($filters['min_size']) && $filters['min_size'] > 0) {
            $params['minsize'] = $filters['min_size'];
        }
        
        if (!empty($filters['bedrooms']) && $filters['bedrooms'] > 0) {
            $params['beds[]'] = $filters['bedrooms'];
        }

        // --- New Filter Implementations ---
        
        // Tenure Mapping
        if (!empty($filters['tenure'])) {
            $tenure = strtolower($filters['tenure']);
            if (str_contains($tenure, 'freehold')) {
                $params['tenure[]'] = 'F';
            } elseif (str_contains($tenure, '999')) {
                $params['tenure[]'] = 'L999';
            } elseif (str_contains($tenure, '99')) {
                $params['tenure[]'] = 'L99';
            }
        }

        // Property Type / House Type Mapping
        if (!empty($filters['house_type'])) {
            $type = strtolower($filters['house_type']);
            // Main category Landed
            if (str_contains($type, 'detached') || str_contains($type, 'terrace') || str_contains($type, 'semi-d')) {
                $params['property_type'] = 'L';
            }
            
            // Specific sub-types
            if (str_contains($type, 'detached')) {
                $params['property_type_code[]'] = 'DETAC';
            } elseif (str_contains($type, 'semi-d')) {
                $params['property_type_code[]'] = 'SEMI';
            } elseif (str_contains($type, 'terrace')) {
                $params['property_type_code[]'] = 'TERRA';
            }
        } else {
            // Default to Residential if no type specified to avoid commercial etc.
            // Only if not already set by house_type
            if (!isset($params['property_type'])) {
                $params['property_type'] = 'R'; 
            }
        }

        $queryString = http_build_query($params);
        return $baseUrl . '?' . $queryString;
    }

    /**
     * Convert district string to code
     */
    protected function convertDistrictToCode(?string $district): ?int
    {
        if (!$district) {
            return null;
        }
        
        // Match patterns like "D13", "d13", "13", "District 13"
        if (preg_match('/D(\d+)/i', $district, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/(\d+)/', $district, $m)) {
            return (int)$m[1];
        }
        
        return null;
    }

    /**
     * Extract listings from __NEXT_DATA__ recursively
     */
    protected function extractListings(array $nextData): array
    {
        $results = [];
        $recurse = function($node) use (&$recurse, &$results) {
            if (is_array($node)) {
                foreach ($node as $v) {
                    if (is_array($v) && isset($v['mediaCarousel'])) {
                        $results[] = $v;
                    }
                    $recurse($v);
                }
            }
        };
        $recurse($nextData);
        return $results;
    }
}

