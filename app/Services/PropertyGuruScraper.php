<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Symfony\Component\DomCrawler\Crawler;

class PropertyGuruScraper
{
    protected $client;

    public function __construct()
    {
        $cookiePath = storage_path('app/cookies.txt'); // exported cookies file
        $jar = new FileCookieJar($cookiePath, true);

        $this->client = new Client([
            'cookies' => $jar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);
    }

    public function search($query = null)
    {
        // Example static URL (later build dynamically from client’s filters)
        $url = "https://www.propertyguru.com.sg/property-for-sale";

        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        $crawler = new Crawler($html);
        $listings = [];

        $crawler->filter('[da-listing-id]')->each(function ($node) use (&$listings) {
            $id = $node->attr('da-listing-id');
            $title = $node->filter('[da-id="listing-card-v2-title"]')->text('');
            $price = $node->filter('[da-id="listing-card-v2-price"]')->text('');
            $agent = $node->filter('[da-id="listing-card-v2-agent-name"]')->text('');
            $url = 'https://www.propertyguru.com.sg' . $node->filter('a.card-footer')->attr('href');

            $listings[] = compact('id', 'title', 'price', 'agent', 'url');
        });

        return $listings;
    }
}
