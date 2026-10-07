<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Imports\ClientsImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\PropertyGuruScraper;
use App\Services\HLDscraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\SingaporeLandedScraper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\PlaywrightScraperService;
use App\Jobs\ScrapePropertyGuruJob;
use Illuminate\Support\Facades\Validator;

class PropertyFinderController extends Controller
{
    public function index()
    {
        return view('propertyfinder.index');
    }

    public function filter(Request $request)
    {
        $uniqueId = Str::uuid()->toString();
        $jsonFile = "pguru_{$uniqueId}.json";
        $slug      = $request->input('slug', 'holland');
        $maxPages  = $request->input('max_pages', 5);
        $maxPrice  = (int) $request->input('max_price', 999999999);
        $minSize   = (int) $request->input('min_size', 0);
        $bedrooms  = (int) $request->input('bedrooms', 0);
        $district = $request->input('district', null);
        $tenure = $request->input('tenure', null);
        $houseType = $request->input('house_type', null);
        $client_name = "Client name 1";
        $platform = 'propertyguru';

        $cached = DB::table('scraped_results')
                ->where('client_name', $client_name)
                ->where('district', $district)
                ->where('price', $maxPrice)
                ->where('bedrooms', $bedrooms)
                ->where('platform', $platform)
                ->where('land_size', $minSize)
                ->where('tenure', $tenure)
                ->where('house_type', $houseType)
                ->where('created_at', '>=', Carbon::now()->subHours(5))
                ->first();
        if ($cached) {
            $jsonFile = $cached->json_file;
            $path = storage_path("app/{$jsonFile}");
            if (!file_exists($path)) {
                return back()->with('error', "{$jsonFile} not found after scraping.");
            }

            $json = json_decode(file_get_contents($path), true);
            $listings = $json['listings'] ?? [];

            $filtered = collect($listings)->map(function ($item) {
                return [
                    'id'        => $item['listingId'] ?? $item['id'] ?? null,
                    'title'     => $item['listingTitle'] ?? $item['localizedTitle'] ?? 'No title',
                    'address'   => $item['fullAddress'] ?? ($item['districtName'] ?? ''),
                    'price'     => is_array($item['price'] ?? null)
                                    ? ($item['price']['pretty'] ?? number_format($item['price']['value'] ?? 0))
                                    : number_format($item['price'] ?? 0),
                    'price_raw' => is_array($item['price'] ?? null) ? ($item['price']['value'] ?? 0) : ($item['price'] ?? 0),
                    'thumbnail' => $item['thumbnail'] ?? null,
                    'url'       => $item['url'] ?? null,
                    'beds'      => $item['bedrooms'] ?? $item['beds'] ?? null,
                    'baths'     => $item['bathrooms'] ?? $item['baths'] ?? null,
                    'area'      => $item['floorArea'] ?? ($item['area'] ?? null),
                    'district'  => $item['additionalData']['districtCode'] ?? $item['districtName'] ?? null,
                    'districtText'  => $item['additionalData']['districtText'] ?? $item['districtName'] ?? null,
                ];
            })->toArray();

            return view('propertyfinder.guru_results', [
                'results'   => $filtered,
                'slug'      => $slug,
                'maxPrice'  => $maxPrice,
                'maxPages'  => $maxPages,
                'cached' => true,
            ]);
        }
        // dd('well');
        $exitCode = Artisan::call('update:pguru', [
            'slug'        => $slug,
            '--max-pages' => $maxPages,
            '--max-price' => $maxPrice,
            '--min-size'  => $minSize,
            '--bedrooms'  => $bedrooms,
            '--district'  => $district,
            '--tenure'    => $tenure,
            '--house-type'=> $houseType,
            '--file'      => $jsonFile,
        ]);

        if ($exitCode !== 0) return back()->with('error', 'Scraper command failed. Check logs.');

        DB::table('scraped_results')->insert([
            'client_name' => $client_name,
            'district' => $district,
            'house_type' => $houseType,
            'tenure' => $tenure,
            'price' => $maxPrice,
            'bedrooms' => $bedrooms,
            'platform' => $platform,
            'land_size' => $minSize,
            'json_file' => $jsonFile,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $path = storage_path("app/{$jsonFile}");
        if (!file_exists($path)) {
            return back()->with('error', "{$jsonFile} not found after scraping.");
        }

        $json = json_decode(file_get_contents($path), true);
        $listings = $json['listings'] ?? [];

        $filtered = collect($listings)->map(function ($item) {
            return [
                'id'        => $item['listingId'] ?? $item['id'] ?? null,
                'title'     => $item['listingTitle'] ?? $item['localizedTitle'] ?? 'No title',
                'address'   => $item['fullAddress'] ?? ($item['districtName'] ?? ''),
                'price'     => is_array($item['price'] ?? null)
                                ? ($item['price']['pretty'] ?? number_format($item['price']['value'] ?? 0))
                                : number_format($item['price'] ?? 0),
                'price_raw' => is_array($item['price'] ?? null) ? ($item['price']['value'] ?? 0) : ($item['price'] ?? 0),
                'thumbnail' => $item['thumbnail'] ?? null,
                'url'       => $item['url'] ?? null,
                'beds'      => $item['bedrooms'] ?? $item['beds'] ?? null,
                'baths'     => $item['bathrooms'] ?? $item['baths'] ?? null,
                'area'      => $item['floorArea'] ?? ($item['area'] ?? null),
                'district'  => $item['additionalData']['districtCode'] ?? $item['districtName'] ?? null,
                'districtText'  => $item['additionalData']['districtText'] ?? $item['districtName'] ?? null,
            ];
        })->toArray();

        return view('propertyfinder.guru_results', [
            'results'   => $filtered,
            'slug'      => $slug,
            'maxPrice'  => $maxPrice,
            'maxPages'  => $maxPages,
        ]);
    }

    public function upload(Request $request)
    {
        Log::info("Upload method called.", ['platform' => $request->input('platform', 'unknown')]);

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv,txt', // Added txt for some csv mime types
            'platform' => 'required',
        ]);

        if ($validator->fails()) {
            Log::error("Validation failed.", $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        try {
            Log::info("Validation passed.");

            // Save uploaded file temporarily
            $path = $request->file('file')->store('uploads');
            Log::info("File stored at: " . $path);

            // Load spreadsheet rows as array
            $rows = Excel::toCollection(null, storage_path('app/' . $path))->first();
            Log::info("Uploaded file processed.", [
                'path' => $path,
                'total_rows_found' => $rows->count(),
                'first_row' => $rows->first()
            ]);
        } catch (\Throwable $e) {
            Log::error("File upload or parsing failed: " . $e->getMessage());
            return back()->with('error', 'Failed to process file: ' . $e->getMessage());
        }

        $districtMap = [
            'd01' => 1, 'd02' => 2, 'd3' => 3, 'd4' => 4,
            'd05' => 5, 'd09' => 9, 'd10' => 10, 'd11' => 11, 'd13' => 13,
            'd15' => 15, 'd16' => 16, 'd17' => 17, 'd19' => 19, 'd20' => 20,
            'd21' => 21, 'd23' => 23, 'd28' => 28,
        ];
        $houseTypeMap = [
            'terrace' => 1, 'corner terrace' => 2, 'semi-d' => 3, 'detached' => 4,
            'good class bungalow' => 5, 'shophouse' => 6, 'land' => 7, 'cluster' => 8,
        ];
        // Helper functions moved to class methods


        // Map column headers properly
        $clients = [];
        foreach ($rows as $index => $row) {
            $firstCell = trim($row[0] ?? '');
            
            // Smart header detection
            if ($index === 0) {
                $isHeader = false;
                // Check first column for "Client Name" or "client_name"
                if (stripos($firstCell, 'Client Name') !== false || stripos($firstCell, 'client_name') !== false) {
                    $isHeader = true;
                }
                // Check if second column is "district" (strong signal)
                if (!$isHeader && isset($row[1]) && strtolower(trim($row[1])) === 'district') {
                    $isHeader = true;
                }
                
                if ($isHeader) {
                    Log::info("Header row detected and skipped.", ['row' => $row]);
                    continue;
                }
            }

            // Skip empty rows
            if (empty($firstCell)) {
                continue; 
            }

            $clients[] = [
                'client_name' => $firstCell,
                'district' => trim($row[1] ?? '') ?: null,
                'price' => trim($row[2] ?? '') ?: null,
                'near_to_mrt' => trim($row[3] ?? '') ?: null,
                'tenure' => trim($row[4] ?? '') ?: null,
                'house_type' => trim($row[5] ?? '') ?: null,
                'land_size' => trim($row[6] ?? '') ?: null,
                'condition' => trim($row[7] ?? '') ?: null,
                'bedrooms' => trim($row[8] ?? '') ?: null,
                'parking' => trim($row[9] ?? '') ?: null,
                'lift' => trim($row[10] ?? '') ?: null,
                'pool' => trim($row[11] ?? '') ?: null,
                'granny_room' => trim($row[12] ?? '') ?: null,
                'facing' => trim($row[13] ?? '') ?: null,
            ];
        }
        Log::info("Clients extracted from file.", ['count' => count($clients)]);

        // dd([
        //     'platform' => $request->platform,
        //     'clients' => $clients,
        // ]);

        // Now scrape for each client
        $results = [];

        // Only process HLD if platform is 'hld' or 'both'
        if ($request->platform == 'hld' || $request->platform == 'both') {
        foreach ($clients as $client) {
            try {
            // Map district IDs
            $districtParts = explode('/', $client['district']);
            $districtIds = [];
            foreach ($districtParts as $part) {
                $part = strtolower(trim($part));
                if (isset($districtMap[$part])) $districtIds[] = $districtMap[$part];
            }

            $houseTypeId = $houseTypeMap[strtolower($client['house_type'])] ?? null;
            $priceMax = $this->parsePrice($client['price'] ?? null);
            $landSizeMax = $this->parseLandSize($client['land_size'] ?? null);
            $bedrooms = $this->parseBedrooms($client['bedrooms'] ?? null);
            // $parkingMin = $client['parking'] ? intval($client['parking']) : null;
            $parkingMin = 1;

            if (!$houseTypeId || empty($districtIds)) {
                $results[] = [
                    'client' => $client['client_name'],
                    'criteria' => $client,
                    'found' => [],
                    'error' => 'Invalid district or house type mapping'
                ];
                continue;
            }

            // 7️⃣ Check cache within 1 week
            $cached = DB::table('scraped_results')
                ->where('client_name', $client['client_name'])
                ->where('district', $client['district'])
                ->where('house_type', $client['house_type'])
                ->where('price', $client['price'])
                ->where('bedrooms', $client['bedrooms'])
                ->where(function($query) {
                    $query->where('platform', 'hld')
                          ->orWhereNull('platform'); // Backward compatibility with old null values
                })
                ->where('land_size', $client['land_size'])
                ->where('tenure', $client['tenure'])
                ->where('created_at', '>=', Carbon::now()->subHours(5))
                ->first();

            $useCache = false;
            if ($cached) {
                $rawJson = Storage::get('scraped_json/'.$cached->json_file);
                $data = json_decode($rawJson, true);

                if (is_array($data)) {
                    $useCache = true;
                    // Post-process parking locally
                    if ($parkingMin > 0) {
                        $data = array_filter($data, function($item) use ($parkingMin) {
                            return isset($item['parking_number']) && intval($item['parking_number']) >= $parkingMin;
                        });
                    }
                    $labels = DB::table('listing_labels')
                        ->where('client_name', $client['client_name'])
                        ->where('platform', 'hld')
                        ->pluck('status', 'listing_id')
                        ->toArray();

                    foreach ($data as &$item) {
                        $item['status'] = $labels[$item['id']] ?? null;
                    }
                    $results[] = [
                        'client' => $client['client_name'],
                        'criteria' => $client,
                        'found' => $data,
                        'cached' => true,
                    ];
                    continue;
                } else {
                    // Corrupt cache (file missing or invalid JSON)
                    DB::table('scraped_results')->where('id', $cached->id)->delete();
                }
            }

            // 8️⃣ Scrape data from website
                $requestParams = [

                    'route_from' => 'undefined',
                    'districts' => $districtIds,
                    'house_type' => $houseTypeId,
                ];
                if($priceMax) $requestParams['price_max'] = $priceMax;
                if($landSizeMax) $requestParams['land_size_max'] = $landSizeMax;
                if(!empty($bedrooms)) $requestParams['bedrooms'] = $bedrooms;

                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ])->get('https://www.singaporelanded.com.sg/listings_search', $requestParams);

                $data = $response->json()['data'] ?? [];

                if ($parkingMin > 0) {
                    $data = array_filter($data, function($item) use ($parkingMin) {
                        return isset($item['parking_number']) && intval($item['parking_number']) >= $parkingMin;
                    });
                }

                // 9️⃣ Store full JSON in file
                $filename = 'scraped_'.Str::slug($client['client_name']).'_'.time().'.json';
                Storage::put('scraped_json/'.$filename, json_encode(array_values($data)));
                // Storage::put('scraped_json/'.$filename, json_encode($data));

                // 10️⃣ Insert DB record
                DB::table('scraped_results')->insert([
                    'client_name' => $client['client_name'],
                    'district' => $client['district'],
                    'house_type' => $client['house_type'],
                    'tenure' => $client['tenure'],
                    'price' => $client['price'],
                    'bedrooms' => $client['bedrooms'],
                    'land_size' => $client['land_size'],
                    'platform' => 'hld',
                    'json_file' => $filename,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $labels = DB::table('listing_labels')
                    ->where('client_name', $client['client_name'])
                    ->where('platform', 'hld')
                    ->pluck('status', 'listing_id')
                    ->toArray();

                foreach ($data as &$item) {
                    $item['status'] = $labels[$item['id']] ?? null;
                }

                $results[] = [
                    'client' => $client['client_name'],
                    'criteria' => $client,
                    'found' => $data,
                    'cached' => false,
                ];

            } catch (\Throwable $e) {
                $results[] = [
                    'client' => $client['client_name'],
                    'criteria' => $client,
                    'found' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }
        } // Close the if statement for HLD processing

        $filteredGU=[];
        if(($request->platform == 'both') || ($request->platform == 'pg')){
            $lastScrapeTime = null;
            $scrapeDelay = 5; // Minimum seconds between scrapes to avoid Chrome overload

            foreach ($clients as $client) {
                // Skip if client name is missing
                if (empty($client['client_name'])) {
                    continue;
                }

                if (isset($client['price'])) {
                    $price = strtolower($client['price']);
                    if (preg_match('/(\d+(\.\d+)?)\s*mil/', $price, $match)) {
                        $num = (float) $match[1];
                        $client['price'] = (int) ($num * 1000000);
                    } else {
                        $client['price'] = (int) preg_replace('/\D/', '', $client['price']);
                    }
                }
                if (isset($client['land_size'])) {
                    $client['land_size'] = preg_replace('/\D/', '', $client['land_size']);
                }
                if (isset($client['bedrooms'])) {
                    preg_match('/\d+/', $client['bedrooms'], $match);
                    $client['bedrooms'] = (int) ($match[0] ?? 0);
                } else {
                    $client['bedrooms'] = 0;
                }
                
                $price = (int) ($client['price'] ?? 0);
                $land_size = (int) ($client['land_size'] ?? 0);
                $bedrooms = (int) ($client['bedrooms'] ?? 0);

                $clientG = [
                    'client_name' => $client['client_name'] ?? 'Unknown',
                    'district'    => $client['district'] ?? '',
                    'price'       => $price,
                    'land_size'   => $land_size,
                    'bedrooms'    => $bedrooms,
                    'tenure'      => $client['tenure'] ?? null,
                    'house_type'  => $client['house_type'] ?? null,
                ];

                // Skip if required fields are missing
                if (empty($clientG['price']) || empty($clientG['land_size'])) {
                    $filteredGU[] = [
                        'client' => $clientG['client_name'],
                        'found' => [],
                        'error' => 'Missing required fields (price or land_size).'
                    ];
                    continue;
                }

                // Convert district string to integer code for PropertyGuru
                $districtCode = null;
                if (!empty($clientG['district'])) {
                    // Extract district numbers from formats like "D10/11", "D15-16", "D19"
                    preg_match_all('/D(\d+)/', $clientG['district'], $matches);
                    if (!empty($matches[1])) {
                        // Use the first district code found
                        $districtCode = (int) $matches[1][0];
                    }
                }

                $cached = DB::table('scraped_results')
                    ->where('client_name', $clientG['client_name'])
                    ->where('district', $clientG['district'])
                    ->where('price', $clientG['price'])
                    ->where('platform', 'propertyguru')
                    ->where('land_size', $clientG['land_size'])
                    ->where('tenure', $clientG['tenure'])
                    ->where('house_type', $clientG['house_type'])
                    ->where('created_at', '>=', Carbon::now()->subHours(5))
                    ->first();

                if ($cached) {
                    $jsonFile = $cached->json_file;
                    $path = storage_path("app/{$jsonFile}");
                    
                    if (file_exists($path)) {
                        $json = json_decode(file_get_contents($path), true);
                        $listings = $json['listings'] ?? [];

                        if (!empty($listings)) {
                            $data = collect($listings)->map(function ($item) {
                                return [
                                    'id'        => $item['listingId'] ?? $item['id'] ?? null,
                                    'title'     => $item['listingTitle'] ?? $item['localizedTitle'] ?? 'No title',
                                    'address'   => $item['fullAddress'] ?? ($item['districtName'] ?? ''),
                                    'price'     => is_array($item['price'] ?? null)
                                                    ? ($item['price']['pretty'] ?? number_format($item['price']['value'] ?? 0))
                                                    : number_format($item['price'] ?? 0),
                                    'price_raw' => is_array($item['price'] ?? null) ? ($item['price']['value'] ?? 0) : ($item['price'] ?? 0),
                                    'thumbnail' => $item['thumbnail'] ?? null,
                                    'url'       => $item['url'] ?? null,
                                    'beds'      => $item['bedrooms'] ?? $item['beds'] ?? null,
                                    'baths'     => $item['bathrooms'] ?? $item['baths'] ?? null,
                                    'area'      => $item['floorArea'] ?? ($item['area'] ?? null),
                                    'district'  => $item['additionalData']['districtCode'] ?? $item['districtName'] ?? null,
                                    'districtText'  => $item['additionalData']['districtText'] ?? $item['districtName'] ?? null,
                                ];
                            })->toArray();
                            $labels = DB::table('listing_labels')
                                ->where('client_name', $clientG['client_name'])
                                ->where('platform', 'propertyguru')
                                ->pluck('status', 'listing_id')
                                ->toArray();

                            foreach ($data as &$item) {
                                $item['status'] = $labels[$item['id']] ?? null;
                            }
                            $filteredGU[] = [
                                'client' => $clientG['client_name'],
                                'found' => $data,
                                'cached' => true,
                            ];
                            continue;
                        } else {
                            // Job finished but found 0 results. 
                            $filteredGU[] = [
                                'client' => $clientG['client_name'],
                                'found' => [],
                                'status' => 'no_results',
                                'message' => 'Scrape completed: No listings found for these criteria.'
                            ];
                            continue;
                        }
                    } else {
                        // File doesn't exist yet. Check if job is still fresh.
                        $cacheAge = Carbon::now()->diffInSeconds(Carbon::parse($cached->created_at));
                        if ($cacheAge < 300) { 
                            $filteredGU[] = [
                                'client' => $clientG['client_name'],
                                'found' => [],
                                'status' => 'processing',
                                'json_file' => $jsonFile,
                                'message' => 'Scraping in progress. Please refresh in a moment.'
                            ];
                            continue;
                        }
                        
                        // If older than 5 mins and no file, it likely failed. 
                        // Delete the stale record to allow a fresh retry on next refresh
                        DB::table('scraped_results')->where('id', $cached->id)->delete();
                        
                        $filteredGU[] = [
                            'client' => $clientG['client_name'],
                            'found' => [],
                            'error' => "Scraping timed out or failed. A fresh attempt will be made on your next refresh."
                        ];
                        continue;
                    }
                }
                // Add delay between scrapes to prevent Chrome overload
                if ($lastScrapeTime !== null) {
                    $elapsed = microtime(true) - $lastScrapeTime;
                    if ($elapsed < $scrapeDelay) {
                        $waitTime = ($scrapeDelay - $elapsed);
                        Log::info("Waiting {$waitTime} seconds before next scrape to avoid Chrome overload");
                        usleep((int)($waitTime * 1000000));
                    }
                }
                $lastScrapeTime = microtime(true);

                $uniqueId = Str::uuid()->toString();
                $jsonFile = "pguru_{$uniqueId}.json";

                Log::info("PropertyGuru scraping for client: {$clientG['client_name']}", [
                    'price' => $clientG['price'],
                    'land_size' => $clientG['land_size'],
                    'bedrooms' => $clientG['bedrooms'],
                    'district' => $districtCode,
                    'json_file' => $jsonFile,
                ]);

                // Check if we should use Playwright service (new) or Browsershot (old)
                $usePlaywright = config('scraper.use_playwright', false);
                $useQueue = config('scraper.use_queue', false);
                $queueConnection = config('queue.default', 'sync');

                if ($usePlaywright) {
                    // NEW: Use Playwright service with Decodo proxy
                    // Use queue job (async - returns immediately) or direct service call
                    if ($useQueue) {
                        // Dispatch job to async queue (returns immediately - no timeout!)
                        ScrapePropertyGuruJob::dispatch($clientG, $jsonFile, 3);
                        
                        Log::info("PropertyGuru scrape job dispatched to queue for client: {$clientG['client_name']}", [
                            'json_file' => $jsonFile,
                            'queue_connection' => $queueConnection
                        ]);
                        
                        // Insert job record into scraped_results (will be updated when job completes)
                        DB::table('scraped_results')->insert([
                            'client_name' => $clientG['client_name'],
                            'district' => $clientG['district'],
                            'house_type' => $clientG['house_type'],
                            'tenure' => $clientG['tenure'],
                            'price' => $clientG['price'],
                            'bedrooms' => $clientG['bedrooms'],
                            'platform' => 'propertyguru',
                            'land_size' => $clientG['land_size'],
                            'json_file' => $jsonFile,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        // For async queues, add placeholder result - file will be available when job completes
                        // User can refresh page or poll status to see results
                        $filteredGU[] = [
                            'client' => $clientG['client_name'],
                            'found' => [],
                            'status' => 'queued',
                            'json_file' => $jsonFile,
                            'message' => 'Scraping in progress. Results will be available shortly. Please refresh to see results.'
                        ];
                        continue; // Skip to next client - DON'T WAIT!
                    } else {
                        // Direct call to service (synchronous) - not recommended for production
                        // This bypasses the queue system
                        Log::warning("Using direct Playwright service call (not queued) for client: {$clientG['client_name']}");
                        
                        $scraperService = new PlaywrightScraperService();
                        
                        // Build URL similar to old method
                        $slug = $clientG['district'] ?: 'Singapore';
                        $url = "https://www.propertyguru.com.sg/property-for-sale?" . http_build_query([
                            '_freetextDisplay' => $slug,
                            'freetext' => $slug,
                            'maxprice' => $clientG['price'],
                            'minsize' => $clientG['land_size'] ?: null,
                            'beds[]' => $clientG['bedrooms'] ?: null,
                            'district_code[]' => $districtCode ? "D{$districtCode}" : null,
                            'tenure[]' => $clientG['tenure'] ? ($this->mapTenure($clientG['tenure'])) : null,
                            'property_type' => $this->mapPropertyType($clientG['house_type']),
                            'property_type_code[]' => $this->mapPropertyTypeCode($clientG['house_type'])
                        ]);
                        
                        $sessionId = "pg-" . Str::slug($clientG['client_name']) . "-" . time();
                        $result = $scraperService->scrape($url, [
                            'waitForSelector' => '#__NEXT_DATA__',
                            'extractScript' => '(() => { const script = document.querySelector("#__NEXT_DATA__"); return script ? JSON.parse(script.textContent) : null; })()'
                        ], $sessionId);
                        
                        if (!$result['success']) {
                            Log::error("Playwright scraper failed for client: {$clientG['client_name']}", [
                                'error' => $result['error'] ?? 'Unknown error',
                                'json_file' => $jsonFile
                            ]);
                            $filteredGU[] = [
                                'client' => $clientG['client_name'],
                                'found' => [],
                                'error' => 'Playwright scraper failed: ' . ($result['error'] ?? 'Unknown error')
                            ];
                            continue;
                        }
                        
                        // Process result and save file (similar to job)
                        $nextData = $result['json'] ?? null;
                        if (!$nextData) {
                            Log::error("No __NEXT_DATA__ found for client: {$clientG['client_name']}");
                            $filteredGU[] = [
                                'client' => $clientG['client_name'],
                                'found' => [],
                                'error' => 'No data found in response'
                            ];
                            continue;
                        }
                        
                        // Extract listings (simplified - full logic in job)
                        $listings = $this->extractListingsFromNextData($nextData);
                        
                        // Save file
                        file_put_contents(
                            storage_path("app/{$jsonFile}"),
                            json_encode([
                                'fetched_at' => date('c'),
                                'slug' => $slug,
                                'max_price' => $clientG['price'],
                                'min_size' => $clientG['land_size'] ?? 0,
                                'bedrooms' => $clientG['bedrooms'] ?? 0,
                                'collected' => count($listings),
                                'listings' => $listings
                            ], JSON_PRETTY_PRINT)
                        );
                    }
                } else {
                    // OLD: Use Browsershot (original method - kept for fallback)
                    // Capture Artisan output for debugging
                    $output = new \Symfony\Component\Console\Output\BufferedOutput();
                    $exitCode = Artisan::call('update:pguru', [
                        'slug'        => 'holland',
                        '--max-pages' => 3,
                        '--max-price' => $clientG['price'],
                        '--min-size'  => $clientG['land_size'] ?: 0,
                        '--bedrooms'  => $clientG['bedrooms'] ?: 0,
                        '--district'  => $districtCode, // Use converted integer district code
                        '--tenure'    => $clientG['tenure'] ?? null,
                        '--house-type'=> $clientG['house_type'] ?? null,
                        '--file'      => $jsonFile,
                    ], $output);

                    $commandOutput = $output->fetch();

                    if ($exitCode !== 0) {
                        Log::error("PropertyGuru scraper failed for client: {$clientG['client_name']}", [
                            'exit_code' => $exitCode,
                            'json_file' => $jsonFile,
                            'command_output' => $commandOutput,
                            'filters' => [
                                'price' => $clientG['price'],
                                'land_size' => $clientG['land_size'],
                                'bedrooms' => $clientG['bedrooms'],
                                'district' => $districtCode,
                            ]
                        ]);
                        $filteredGU[] = [
                            'client' => $clientG['client_name'],
                            'found' => [],
                            'error' => 'Scraper command failed. ' . (strlen($commandOutput) > 0 ? 'Error: ' . substr($commandOutput, 0, 200) : 'Check logs for details.')
                        ];
                        continue;
                    }
                }

                DB::table('scraped_results')->insert([
                    'client_name' => $clientG['client_name'],
                    'district' => $clientG['district'],
                    'house_type' => $clientG['house_type'],
                    'tenure' => $clientG['tenure'],
                    'price' => $clientG['price'],
                    'bedrooms' => $clientG['bedrooms'],
                    'platform' => 'propertyguru',
                    'land_size' => $clientG['land_size'],
                    'json_file' => $jsonFile,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $path = storage_path("app/{$jsonFile}");
                if (!file_exists($path)) {
                    $filteredGU[] = [
                        'client' => $clientG['client_name'],
                        'found' => [],
                        'error' => "File {$jsonFile} not found after scraping."
                    ];
                    continue;
                }

                $json = json_decode(file_get_contents($path), true);
                $listings = $json['listings'] ?? [];
                $collected = $json['collected'] ?? 0;

                Log::info("PropertyGuru scraping completed for client: {$clientG['client_name']}", [
                    'collected' => $collected,
                    'listings_count' => count($listings),
                    'json_file' => $jsonFile,
                ]);

                if (empty($listings)) {
                    $filteredGU[] = [
                        'client' => $clientG['client_name'],
                        'found' => [],
                        'error' => "No listings found from PropertyGuru. (Searched with: Price ≤ " . number_format($clientG['price']) . ", Size ≥ {$clientG['land_size']} sqft, Bedrooms = {$clientG['bedrooms']}, District = " . ($districtCode ? "D{$districtCode}" : "Any") . ")",
                    ];
                    continue;
                }

                $data = collect($listings)->map(function ($item) {
                    return [
                        'id'        => $item['listingId'] ?? $item['id'] ?? null,
                        'title'     => $item['listingTitle'] ?? $item['localizedTitle'] ?? 'No title',
                        'address'   => $item['fullAddress'] ?? ($item['districtName'] ?? ''),
                        'price'     => is_array($item['price'] ?? null)
                                        ? ($item['price']['pretty'] ?? number_format($item['price']['value'] ?? 0))
                                        : number_format($item['price'] ?? 0),
                        'price_raw' => is_array($item['price'] ?? null) ? ($item['price']['value'] ?? 0) : ($item['price'] ?? 0),
                        'thumbnail' => $item['thumbnail'] ?? null,
                        'url'       => $item['url'] ?? null,
                        'beds'      => $item['bedrooms'] ?? $item['beds'] ?? null,
                        'baths'     => $item['bathrooms'] ?? $item['baths'] ?? null,
                        'area'      => $item['floorArea'] ?? ($item['area'] ?? null),
                        'district'  => $item['additionalData']['districtCode'] ?? $item['districtName'] ?? null,
                        'districtText'  => $item['additionalData']['districtText'] ?? $item['districtName'] ?? null,
                    ];
                })->toArray();
                $labels = DB::table('listing_labels')
                    ->where('client_name', $clientG['client_name'])
                    ->where('platform', 'propertyguru')
                    ->pluck('status', 'listing_id')
                    ->toArray();

                foreach ($data as &$item) {
                    $item['status'] = $labels[$item['id']] ?? null;
                }
                $filteredGU[] = [
                    'client' => $clientG['client_name'],
                    'found' => $data
                ];
                continue;
            }
        }
        // dd($filteredGU,$results);

        // return view('propertyfinder.guru_results', [
        //     'results'   => $filteredGU,
        // ]);
        $hide = $request->hide_status ?? null;

        if ($hide) {
            $results = array_map(function ($item) use ($hide) {
                $item['found'] = array_filter($item['found'], function ($listing) use ($hide) {
                    return ($listing['status'] ?? null) !== $hide;
                });
                return $item;
            }, $results);
        }

        if ($hide && !empty($filteredGU)) {
            $filteredGU = array_map(function ($item) use ($hide) {
                $item['found'] = array_filter($item['found'], function ($listing) use ($hide) {
                    return ($listing['status'] ?? null) !== $hide;
                });
                return $item;
            }, $filteredGU);
        }

        // 7️⃣ Return Blade view with results
        return view('propertyfinder.scrape_results', compact('results','filteredGU'));
    }

    public function labelListing(Request $request)
    {
        $request->validate([
            'listing_id' => 'required',
            'platform' => 'required',
            'status' => 'required',
            'client_name' => 'required',
        ]);

        DB::table('listing_labels')->updateOrInsert(
            [
                'listing_id' => $request->listing_id,
                'client_name' => $request->client_name,
                'platform' => $request->platform,
            ],
            [
                'status' => $request->status,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Extract listings from __NEXT_DATA__ (helper method for direct service calls)
     * Full implementation is in ScrapePropertyGuruJob
     */
    protected function extractListingsFromNextData(array $nextData): array
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

    /**
     * Map tenure string to PropertyGuru code
     */
    protected function mapTenure(?string $tenure): ?string
    {
        if (!$tenure) return null;
        $t = strtolower($tenure);
        if (str_contains($t, 'freehold')) return 'F';
        if (str_contains($t, '999')) return 'L999';
        if (str_contains($t, '99')) return 'L99';
        return null;
    }

    /**
     * Map house type to PropertyGuru property_type
     */
    protected function mapPropertyType(?string $houseType): string
    {
        if (!$houseType) return 'R';
        $ht = strtolower($houseType);
        if (str_contains($ht, 'detached') || str_contains($ht, 'terrace') || str_contains($ht, 'semi-d')) {
            return 'L';
        }
        return 'R';
    }

    /**
     * Map house type to PropertyGuru property_type_code[]
     */
    protected function mapPropertyTypeCode(?string $houseType): ?string
    {
        if (!$houseType) return null;
        $ht = strtolower($houseType);
        if (str_contains($ht, 'detached')) return 'DETAC';
        if (str_contains($ht, 'semi-d')) return 'SEMI';
        if (str_contains($ht, 'terrace')) return 'TERRA';
        return null;
    }

    private function parsePrice($priceString) {
        $priceString = strtolower(trim($priceString));
        $priceString = str_replace(['$', ','], '', $priceString);
        if (strpos($priceString, 'mil') !== false) return floatval($priceString) * 1000000;
        elseif (strpos($priceString, 'k') !== false) return floatval($priceString) * 1000;
        else return floatval($priceString);
    }

    private function parseLandSize($landSizeString) {
        if (!$landSizeString) return null;
        $landSizeString = strtolower(str_replace(['sqft', ','], '', $landSizeString));
        return floatval($landSizeString);
    }

    private function parseBedrooms($bedroomString) {
        $bedroomString = strtolower(trim($bedroomString));
        $bedrooms = [];
        // Match "X and above"
        if (preg_match('/^(\d+)\s*and\s*above$/', $bedroomString, $matches)) {
            $start = intval($matches[1]);
            for ($i = $start; $i <= 7; $i++) {
                $bedrooms[] = (string)$i;
                $bedrooms[] = $i.'+1';
            }
            $bedrooms[] = '8';
            $bedrooms[] = '8 and more';
            return $bedrooms;
        }
        // Map individual numbers
        switch ($bedroomString) {
            case '1': return ['1'];
            case '2': return ['2'];
            case '3': return ['3'];
            case '4': return ['4'];
            case '5': return ['5','5+1'];
            case '6': return ['6','6+1'];
            case '7': return ['7','7+1'];
            case '8': return ['8','8 and more'];
            case '5 and above': return ['5','5+1','6','6+1','7','7+1','8','8 and more'];
            default: return [];
        }
    }
}
