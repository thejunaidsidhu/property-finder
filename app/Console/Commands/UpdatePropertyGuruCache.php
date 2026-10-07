<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;
use App\Services\PlaywrightScraperService;
use Illuminate\Support\Facades\Log;

class UpdatePropertyGuruCache extends Command
{
    protected $signature = 'update:pguru
                            {slug=holland : Search keyword}
                            {--max-pages=0 : Max pages (0 = auto)}
                            {--max-price=999999999 : Max price filter}
                            {--min-size=0 : Minimum size filter}
                            {--bedrooms=0 : Minimum bedrooms}
                            {--district= : District code (e.g., D06)}
                            {--tenure= : Tenure filter (e.g., Freehold)}
                            {--house-type= : House type filter (e.g., Detached)}
                            {--file=pguru_all.json : JSON filename to save results}';

    protected $description = 'Fetch PropertyGuru SRP pages with real pagination URLs, save per-page data, then merge listings.';

    public function handle()
    {
        set_time_limit(300); // avoid timeout

        // Ensure Node.js from NVM is accessible via symlinks (required for Browsershot)
        $nodePath = env('NODE_PATH');
        if ($nodePath) {
            $nodeBinary = $nodePath . '/node';
            $npmBinary = $nodePath . '/npm';

            // Verify binaries exist
            if (!file_exists($nodeBinary) || !file_exists($npmBinary)) {
                $this->error("Node.js binaries not found at: {$nodePath}");
                Log::error("Node.js binaries missing", [
                    'node_path' => $nodePath,
                    'node_exists' => file_exists($nodeBinary),
                    'npm_exists' => file_exists($npmBinary)
                ]);
                return 1;
            }

            // Create symlinks in user's bin directory (writable location)
            $homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: '/tmp');
            $userBinDir = $homeDir . '/bin';

            // Create ~/bin directory if it doesn't exist
            if (!is_dir($userBinDir)) {
                @mkdir($userBinDir, 0755, true);
            }

            $nodeSymlink = $userBinDir . '/node';
            $npmSymlink = $userBinDir . '/npm';

            // Check if we need to create/update symlinks
            $needsSymlink = false;

            if (!file_exists($nodeSymlink) || !is_link($nodeSymlink) || readlink($nodeSymlink) !== $nodeBinary) {
                $needsSymlink = true;
            }
            if (!file_exists($npmSymlink) || !is_link($npmSymlink) || readlink($npmSymlink) !== $npmBinary) {
                $needsSymlink = true;
            }

            if ($needsSymlink && is_writable($userBinDir)) {
                // Remove existing symlinks if they exist
                if (file_exists($nodeSymlink)) {
                    @unlink($nodeSymlink);
                }
                if (file_exists($npmSymlink)) {
                    @unlink($npmSymlink);
                }

                // Create new symlinks
                if (@symlink($nodeBinary, $nodeSymlink) && @symlink($npmBinary, $npmSymlink)) {
                    $this->info("Created symlinks in {$userBinDir}:");
                    $this->info("  {$nodeSymlink} -> {$nodeBinary}");
                    $this->info("  {$npmSymlink} -> {$npmBinary}");
                } else {
                    $this->warn("Could not create symlinks automatically.");
                    Log::warning("Symlink creation failed", [
                        'node_binary' => $nodeBinary,
                        'npm_binary' => $npmBinary,
                        'symlink_dir' => $userBinDir
                    ]);
                }
            }

            // Add ~/bin and nodePath to PATH if not already there (must be first for Browsershot)
            $currentPath = getenv('PATH') ?: '';
            $newPath = $nodePath . ':' . $userBinDir;
            if (strpos($currentPath, $userBinDir) === false) {
                $newPath .= ':' . $currentPath;
            } else {
                // Remove userBinDir from current path and prepend it
                $currentPath = str_replace($userBinDir . ':', '', $currentPath);
                $currentPath = str_replace(':' . $userBinDir, '', $currentPath);
                $newPath .= ':' . $currentPath;
            }
            // Remove nodePath from middle if it exists, we want it first
            $newPath = str_replace(':' . $nodePath . ':', ':', $newPath);
            $newPath = str_replace($nodePath . ':', '', $newPath);
            $newPath = $nodePath . ':' . $userBinDir . ':' . $currentPath;

            putenv('PATH=' . $newPath);
            $_ENV['PATH'] = $newPath;
            $_SERVER['PATH'] = $newPath;

            $this->info("PATH configured: " . substr($newPath, 0, 100) . "...");

            // Verify Node.js is accessible (check both symlink and direct path)
            $nodeCheck = shell_exec('which node 2>&1');
            $npmCheck = shell_exec('which npm 2>&1');

            // If not found via which, try direct path
            if (empty($nodeCheck) || !file_exists(trim($nodeCheck))) {
                // Try using the direct binary path
                if (file_exists($nodeBinary) && file_exists($npmBinary)) {
                    $this->info("Using Node.js directly from: {$nodeBinary}");
                    $this->info("Using NPM directly from: {$npmBinary}");
                    // Set PATH to include nodePath first
                    $currentPath = getenv('PATH') ?: '';
                    $newPath = $nodePath . ':' . $userBinDir . ':' . $currentPath;
                    putenv('PATH=' . $newPath);
                    $_ENV['PATH'] = $newPath;
                } else {
                    $this->error("Node.js not found. Please check NODE_PATH in .env");
                    $this->error("Expected: {$nodeBinary}");
                    Log::error("Node.js not accessible", [
                        'node_path' => $nodePath,
                        'which_node' => $nodeCheck,
                        'which_npm' => $npmCheck,
                        'node_binary_exists' => file_exists($nodeBinary),
                        'npm_binary_exists' => file_exists($npmBinary)
                    ]);
                    return 1;
                }
            } else {
                $this->info("Node.js found at: " . trim($nodeCheck));
                $this->info("NPM found at: " . trim($npmCheck));
                // Ensure nodePath is in PATH
                $currentPath = getenv('PATH') ?: '';
                if (strpos($currentPath, $nodePath) === false) {
                    putenv('PATH=' . $nodePath . ':' . $currentPath);
                    $_ENV['PATH'] = $nodePath . ':' . $currentPath;
                }
            }
        } else {
            $this->warn("NODE_PATH not set in .env. Browsershot may fail if Node.js is not in system PATH.");
        }

        $slug      = $this->argument('slug');
        $cap       = (int) $this->option('max-pages');
        $maxPrice  = (int) $this->option('max-price');
        $minSize   = (int) $this->option('min-size');
        $bedrooms  = (int) $this->option('bedrooms');
        $district  = $this->option('district'); // Keep as string/int for flexible handling
        $tenure    = $this->option('tenure');
        $houseType = $this->option('house-type');
        $fileName = $this->option('file') ?? 'pguru_all.json';

        $cookieHeader = env('PG_COOKIE_HEADER', null);

        // Check if we should use Playwright service (new) or Browsershot (old)
        $usePlaywright = config('scraper.use_playwright', false);
        $playwrightService = $usePlaywright ? new PlaywrightScraperService() : null;

        // URL builder with filters
        $buildUrl = function(int $page) use ($slug, $maxPrice, $minSize, $bedrooms, $district, $tenure, $houseType) {
            $base = 'https://www.propertyguru.com.sg/property-for-sale';
            if ($page > 1) $base .= '/' . $page;
            
            // Map district code if present
            $districtCode = null;
            if ($district) {
                if (is_numeric($district)) {
                    $districtCode = $district;
                } elseif (preg_match('/D(\d+)/i', $district, $m)) {
                    $districtCode = (int)$m[1];
                }
            }

            $params = [
                '_freetextDisplay' => $slug,
                'freetext'         => $slug,
                'maxprice'         => $maxPrice,
            ];

            if ($districtCode) {
                $params['district_code[]'] = "D{$districtCode}";
            }

            if ($minSize > 0) {
                $params['minsize'] = $minSize;
            }

            if ($bedrooms > 0) {
                $params['beds[]'] = $bedrooms;
            }

            // --- New Filter Implementations ---
            
            // Tenure Mapping
            if ($tenure) {
                $t = strtolower($tenure);
                if (str_contains($t, 'freehold')) {
                    $params['tenure[]'] = 'F';
                } elseif (str_contains($t, '999')) {
                    $params['tenure[]'] = 'L999';
                } elseif (str_contains($t, '99')) {
                    $params['tenure[]'] = 'L99';
                }
            }

            // Property Type / House Type Mapping
            if ($houseType) {
                $ht = strtolower($houseType);
                // Main category Landed
                if (str_contains($ht, 'detached') || str_contains($ht, 'terrace') || str_contains($ht, 'semi-d')) {
                    $params['property_type'] = 'L';
                }
                
                // Specific sub-types
                if (str_contains($ht, 'detached')) {
                    $params['property_type_code[]'] = 'DETAC';
                } elseif (str_contains($ht, 'semi-d')) {
                    $params['property_type_code[]'] = 'SEMI';
                } elseif (str_contains($ht, 'terrace')) {
                    $params['property_type_code[]'] = 'TERRA';
                }
            } else {
                // Default to Residential if no type specified to avoid commercial etc.
                if (!isset($params['property_type'])) {
                    $params['property_type'] = 'R'; 
                }
            }

            return $base . '?' . http_build_query($params);
        };

        // Fetch HTML - uses Playwright service if enabled, otherwise Browsershot
        $fetchHtml = function(string $url) use ($usePlaywright, $playwrightService, $cookieHeader) {
            // Use Playwright service if enabled
            if ($usePlaywright && $playwrightService) {
                $maxRetries = 2;
                $retryDelay = 2; // seconds

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {
                        $sessionId = 'update-pguru-' . time();
                        $result = $playwrightService->scrape($url, [
                            'waitForSelector' => '#__NEXT_DATA__'
                        ], $sessionId);

                        if ($result['success'] && !empty($result['html'])) {
                            return $result['html'];
                        }

                        $errorMsg = $result['error'] ?? 'Unknown error';
                        if ($attempt < $maxRetries) {
                            Log::warning("Playwright scraper failed, retrying...", [
                                'url' => $url,
                                'attempt' => $attempt,
                                'error' => $errorMsg
                            ]);
                            sleep($retryDelay);
                            continue;
                        }

                        Log::error("PropertyGuru Playwright scraper failed", [
                            'url' => $url,
                            'attempt' => $attempt,
                            'error' => $errorMsg
                        ]);

                        return null;
                    } catch (\Throwable $e) {
                        $errorMsg = "Playwright service error (attempt {$attempt}/{$maxRetries}): " . $e->getMessage();
                        Log::error("PropertyGuru Playwright service exception", [
                            'url' => $url,
                            'attempt' => $attempt,
                            'error' => $e->getMessage(),
                            'trace' => substr($e->getTraceAsString(), 0, 500)
                        ]);

                        if ($attempt < $maxRetries) {
                            sleep($retryDelay);
                            continue;
                        }

                        return null;
                    }
                }

                return null;
            }

            // OLD BROWSERSHOT CODE (COMMENTED OUT - KEPT FOR FUTURE USE)
            // Uncomment the code below and comment out the Playwright code above to switch back to Browsershot
            /*
            $maxRetries = 2;
            $retryDelay = 2; // seconds

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $bs = Browsershot::url($url)
                        ->windowSize(1366, 900)
                        ->timeout(120)
                        ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                        ->waitUntilNetworkIdle(true)  // Wait for network to be idle - important for Cloudflare
                        ->dismissDialogs()
                        ->setDelay(15000);  // Wait 15 seconds for Cloudflare challenge to complete

                    // Set Chrome path - prioritize Google Chrome (no snap dependency, works with www-data)
                    $chromePath = env('CHROME_PATH');
                    if (!$chromePath) {
                        // Try Google Chrome first (most reliable, no snap issues)
                        $possiblePaths = [
                            '/usr/bin/google-chrome-stable',  // Google Chrome (apt install)
                            '/usr/bin/google-chrome',         // Alternative Chrome path
                            '/usr/bin/chromium-browser',      // Chromium (may require snap)
                            '/usr/bin/chromium',              // Alternative Chromium
                        ];
                        foreach ($possiblePaths as $path) {
                            if (file_exists($path) && is_executable($path)) {
                                $chromePath = $path;
                                break;
                            }
                        }
                    }
                    
                    if ($chromePath && file_exists($chromePath) && is_executable($chromePath)) {
                        $bs->setChromePath($chromePath);
                    } else {
                        $this->warn("Chrome not found. Please install Google Chrome or set CHROME_PATH in .env");
                        Log::warning("Chrome path not found", [
                            'attempted_paths' => $possiblePaths ?? []
                        ]);
                    }

                    // Add Chromium arguments for better compatibility and to avoid Cloudflare detection
                    $bs->addChromiumArguments([
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--user-data-dir=/var/www/chromium-data',
                        '--disable-blink-features=AutomationControlled',  // Hide automation
                        '--disable-features=IsolateOrigins,site-per-process',
                        '--disable-web-security',
                        '--disable-features=VizDisplayCompositor',
                    ]);

                    // More realistic headers to avoid Cloudflare detection
                    $headers = [
                        'Referer' => 'https://www.propertyguru.com.sg/',
                        // 'Accept' header with all MIME types (use proper Accept header in actual code)
                        'Accept-Language' => 'en-US,en;q=0.9',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Connection' => 'keep-alive',
                        'Upgrade-Insecure-Requests' => '1',
                        'Sec-Fetch-Dest' => 'document',
                        'Sec-Fetch-Mode' => 'navigate',
                        'Sec-Fetch-Site' => 'none',
                        'Cache-Control' => 'max-age=0',
                    ];
                    if ($cookieHeader) $headers['Cookie'] = $cookieHeader;

                    $bs->setExtraHttpHeaders($headers);

                    $html = $bs->bodyHtml();
                    
                    // Check if we got Cloudflare challenge and log it
                    if (strpos($html, 'Just a moment') !== false || strpos($html, 'cf-browser-verification') !== false) {
                        $this->warn("Cloudflare challenge detected. HTML length: " . strlen($html));
                        Log::warning("Cloudflare challenge detected", [
                            'url' => $url,
                            'html_length' => strlen($html),
                            'html_preview' => substr($html, 0, 500)
                        ]);
                    }

                    if ($html && strlen($html) > 100) {
                        return $html;
                    }

                    // If HTML is too short, it might be an error page
                    if ($attempt < $maxRetries) {
                        $this->warn("Attempt {$attempt} returned short HTML, retrying...");
                        sleep($retryDelay);
                        continue;
                    }

                    return $html;
                } catch (\Throwable $e) {
                    $errorMsg = "Browsershot error (attempt {$attempt}/{$maxRetries}): " . $e->getMessage();
                    $this->error($errorMsg);

                    Log::error("PropertyGuru Browsershot failed", [
                        'url' => $url,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'trace' => substr($e->getTraceAsString(), 0, 500) // Limit trace length
                    ]);

                    if ($attempt < $maxRetries) {
                        $this->info("Retrying in {$retryDelay} seconds...");
                        sleep($retryDelay);
                        continue;
                    }

                    return null;
                }
            }

            return null;
            */
            
            // If Playwright is not enabled, throw an error
            throw new \Exception("Playwright scraper service is not enabled. Please set USE_PLAYWRIGHT_SERVICE=true in .env or use Browsershot by uncommenting the code above.");
        };

        // Extract __NEXT_DATA__
        $extractNextData = function(string $html) {
            if (preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/s', $html, $m)) {
                return json_decode($m[1], true);
            }
            return null;
        };

        // Collect full listings recursively
        $collectListings = function(array $nextData) {
            $results = [];
            $recurse = function($node) use (&$recurse, &$results) {
                if (is_array($node)) {
                    foreach ($node as $v) {
                        if (is_array($v) && isset($v['mediaCarousel'])) $results[] = $v;
                        $recurse($v);
                    }
                }
            };
            $recurse($nextData);
            return $results;
        };

        // Fetch page 1
        $page1Url = $buildUrl(1);
        $this->info("Fetching Page 1 URL: " . $page1Url);
        $html = $fetchHtml($page1Url);
        if (!$html) {
            $errorMsg = "Failed to fetch page 1. Check logs for Browsershot errors.";
            $this->error($errorMsg);
            Log::error("PropertyGuru: Failed to fetch page 1", ['url' => $page1Url]);
            return 1;
        }
        file_put_contents(storage_path("app/pguru_page_1.html"), $html);

        $nextData = $extractNextData($html);
        if (!$nextData) {
            $errorMsg = "No __NEXT_DATA__ found on page 1. The page structure may have changed.";
            $this->error($errorMsg);
            Log::error("PropertyGuru: No __NEXT_DATA__ found", [
                'url' => $page1Url,
                'html_length' => strlen($html),
                'html_preview' => substr($html, 0, 500)
            ]);
            return 1;
        }
        file_put_contents(storage_path("app/pguru_next_page_1.json"), json_encode($nextData, JSON_PRETTY_PRINT));

        // Pagination detection
        $json = json_encode($nextData);
        preg_match('/"resultCount":\s*(\d+)/', $json, $m1);
        preg_match('/"limit":\s*(\d+)/', $json, $m2);
        $resultCount = $m1[1] ?? null;
        $perPage     = $m2[1] ?? 20;
        $totalPages  = $resultCount ? ceil($resultCount / $perPage) : 1;
        if ($cap > 0) $totalPages = min($totalPages, $cap);
        $this->info("Total pages: $totalPages");

        $allListings = [];
        $pageListings = $collectListings($nextData);
        file_put_contents(storage_path("app/pguru_listings_page_1.json"), json_encode($pageListings, JSON_PRETTY_PRINT));
        $allListings[] = $pageListings;

        // Remaining pages
        for ($p = 2; $p <= $totalPages; $p++) {
            // Add delay between page fetches to avoid overwhelming the server
            usleep(500000); // 0.5 seconds between pages
            $url = $buildUrl($p);
            $this->info("Fetching Page {$p} URL: " . $url);
            $html = $fetchHtml($url);
            if (!$html) {
                $this->warn("Failed to fetch page {$p}, stopping pagination");
                Log::warning("PropertyGuru: Failed to fetch page {$p}", ['url' => $url]);
                break;
            }
            file_put_contents(storage_path("app/pguru_page_{$p}.html"), $html);
            $nd = $extractNextData($html);
            if (!$nd) break;
            file_put_contents(storage_path("app/pguru_next_page_{$p}.json"), json_encode($nd, JSON_PRETTY_PRINT));
            $pageListings = $collectListings($nd);
            file_put_contents(storage_path("app/pguru_listings_page_{$p}.json"), json_encode($pageListings, JSON_PRETTY_PRINT));
            $allListings[] = $pageListings;
        }

        // Merge listings
        $merged = [];
        foreach ($allListings as $pageIndex => $chunk) {
            foreach ($chunk as $idx => $item) {
                $lid = $item['listingId'] ?? $item['id'] ?? null;
                $key = $lid ?: md5(json_encode($item) . "_{$pageIndex}_{$idx}");
                $merged[$key] = $item;
            }
        }

        $final = array_values($merged);
        file_put_contents(storage_path("app/{$fileName}"), json_encode([
            'fetched_at' => date('c'),
            'slug'       => $slug,
            'max_price'  => $maxPrice,
            'min_size'   => $minSize,
            'bedrooms'   => $bedrooms,
            'tenure'     => $tenure,
            'house_type' => $houseType,
            'collected'  => count($final),
            'listings'   => $final
        ], JSON_PRETTY_PRINT));

        $this->info("DONE — Final combined saved to storage/app/{$fileName}");
        return 0;
    }
}
