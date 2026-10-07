<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class HLDScraperTest extends TestCase
{
    public function test_hld_scraper_with_varying_rows()
    {
        // Mock HLD API
        Http::fake([
            'singaporelanded.com.sg/*' => Http::response([
                'data' => [
                    ['id' => 1, 'title' => 'Test Property 1', 'price' => 5000000, 'parking_number' => 2],
                    ['id' => 2, 'title' => 'Test Property 2', 'price' => 6000000, 'parking_number' => 2],
                ]
            ], 200),
        ]);

        // Storage::fake('local');

        // 1. Test with 1 data row (Total 2 rows including header)
        // Note: Controller uses Excel::toCollection on the file.
        // We need to create a CSV that Excel can read.
        
        $content = "Client Name,District,Price,Near to MRT,Tenure,House Type,Land Size,Condition,Bedrooms,Parking,Lift,Pool,Granny Room,Facing\n";
        $content .= "TestClient1,19,5mil,Yes,Freehold,Terrace,2000,Good,5,2,Yes,Yes,Yes,North\n";
        
        // Using UploadedFile::fake() creates a file in temp dir.
        // The controller moves it to 'uploads'. 
        // We need to make sure 'uploads' directory exists in our fake storage?
        // Actually, storage path in controller: storage_path('app/' . $path).
        // Since we are running in test environment, storage_path points to test storage?
        // Let's rely on standard Laravel file upload handling.

        $file = UploadedFile::fake()->createWithContent('test_1_row.csv', $content);

        $response = $this->post(route('propertyfinder.upload'), [
            'file' => $file,
            'platform' => 'hld',
        ]);

        $response->assertStatus(200);
        $response->assertViewHas('results');
        $results = $response->viewData('results');
        
        // Debug output if empty
        if (empty($results)) {
            echo "\nResults empty for 1 row input!\n";
        } else {
            echo "\nResults count for 1 row: " . count($results) . "\n";
        }

        $this->assertNotEmpty($results, "Should have results for 1 input row");
        $this->assertCount(1, $results, "Should have 1 client result for 1 input row");

        // 2. Test with 2 data rows
        $content = "Client Name,District,Price,Near to MRT,Tenure,House Type,Land Size,Condition,Bedrooms,Parking,Lift,Pool,Granny Room,Facing\n";
        $content .= "TestClient1,19,5mil,Yes,Freehold,Terrace,2000,Good,5,2,Yes,Yes,Yes,North\n";
        $content .= "TestClient2,20,6mil,No,999,Detached,3000,Good,5,2,Yes,Yes,Yes,South\n";

        $file = UploadedFile::fake()->createWithContent('test_2_rows.csv', $content);

        $response = $this->post(route('propertyfinder.upload'), [
            'file' => $file,
            'platform' => 'hld',
        ]);
        
        $response->assertStatus(200);
        $results = $response->viewData('results');
        
        if (empty($results)) {
            echo "\nResults empty for 2 rows input!\n";
        } else {
            echo "\nResults count for 2 rows: " . count($results) . "\n";
        }

        $this->assertCount(2, $results, "Should have 2 client results for 2 input rows");
    }
}
