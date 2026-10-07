<?php

namespace App\Services;

use Goutte\Client;

class SingaporeLandedScraper
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function searchForClient($clientData)
    {
        // Build base URL
        $url = 'https://www.singaporelanded.com.sg/properties?';

        // Build query parameters based on client data
        $query = [];

        if (!empty($clientData['district'])) {
            $query['district'] = $clientData['district'];
        }

        if (!empty($clientData['house_type'])) {
            $query['type'] = strtolower(str_replace(' ', '-', $clientData['house_type']));
        }

        if (!empty($clientData['price'])) {
            // Example: parse "$2mil" → "2000000"
            $query['max_price'] = $this->parsePrice($clientData['price']);
        }

        // Construct final URL
        $finalUrl = $url . http_build_query($query);

        // Scrape page
        $crawler = $this->client->request('GET', $finalUrl);

        // Extract listings
        $results = $crawler->filter('.property-item')->each(function ($node) {
            return [
                'title' => $node->filter('.property-title')->text(''),
                'price' => $node->filter('.property-price')->text(''),
                'address' => $node->filter('.property-location')->text(''),
                'link' => $node->filter('a')->attr('href'),
            ];
        });

        return [
            'client_name' => $clientData['client_name'],
            'listings' => $results,
        ];
    }

    protected function parsePrice($priceStr)
    {
        $priceStr = strtolower($priceStr);
        if (str_contains($priceStr, 'mil')) {
            return (float) filter_var($priceStr, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) * 1000000;
        }
        return (float) filter_var($priceStr, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}
