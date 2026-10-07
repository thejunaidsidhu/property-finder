<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DebugUploadTest extends TestCase
{
    public function test_route_access()
    {
        $response = $this->post(route('propertyfinder.upload'), [
            'platform' => 'hld',
        ]);
        $response->assertStatus(302);
    }

    public function test_upload_headerless_file()
    {
        Log::info("TEST: Starting headerless file upload test");
        Storage::fake('local');
        // CSV with NO header, just data
        $file = UploadedFile::fake()->createWithContent('no_header.csv', "MrUnknown,D15,5000000");
        
        $response = $this->post(route('propertyfinder.upload'), [
            'platform' => 'hld',
            'file' => $file,
        ]);
        Log::info("TEST: Headerless upload finished, status: " . $response->status());
        // We expect it to process, so it should return 200 or whatever success view
        // Ideally we check logs or response content, but status 200 is a good start vs 500
        $response->assertStatus(200);
    }
}
