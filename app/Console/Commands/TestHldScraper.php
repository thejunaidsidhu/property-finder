<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class TestHldScraper extends Command
{
    protected $signature = 'test:hld';
    protected $description = 'Test SingaporeLanded.com.sg HTML scraping';

    public function handle()
    {
        $this->info('🔍 Fetching SingaporeLanded property listings...');

        // Example search URL (you can modify filters later)
        $url = 'https://www.singaporelanded.com.sg/properties?type=sale&property_type[]=Semi-Detached&price_min=1000000&price_max=8000000&district[]=15';

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer' => 'https://www.singaporelanded.com.sg/',
        ])->timeout(60)->get($url);

        if ($response->failed()) {
            $this->error("❌ Request failed. Status: " . $response->status());
            return;
        }

        $html = $response->body();

        $crawler = new Crawler($html);
        $listings = [];

        // Adjust selector if structure changes — this works as of latest site
        $crawler->filter('.listing-item')->each(function (Crawler $node) use (&$listings) {
            $title = $node->filter('.listing-title a')->count() ? $node->filter('.listing-title a')->text() : 'N/A';
            $url = $node->filter('.listing-title a')->count() ? $node->filter('.listing-title a')->attr('href') : '';
            $price = $node->filter('.listing-price')->count() ? trim($node->filter('.listing-price')->text()) : 'N/A';
            $details = $node->filter('.listing-detail')->count() ? trim($node->filter('.listing-detail')->text()) : '';

            $listings[] = [
                'title' => $title,
                'url' => $url,
                'price' => $price,
                'details' => $details,
            ];
        });

        if (empty($listings)) {
            $this->warn('⚠️ No listings found. Check selector or structure.');
            file_put_contents(storage_path('app/hld.html'), $html);
            $this->info('💾 Saved raw HTML to storage/app/hld.html for debugging.');
            return;
        }

        $this->info("✅ Found " . count($listings) . " listings.\n");

        foreach (array_slice($listings, 0, 5) as $listing) {
            $this->line("🏠 {$listing['title']} ({$listing['price']})");
            $this->line("🔗 {$listing['url']}");
            $this->line('');
        }

        $this->info("💾 Saved first 5 listings successfully.");
    }
}
