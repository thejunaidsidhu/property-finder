<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupScrapeCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-scrapes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete scraped results and associated JSON/HTML files older than 5 hours';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $expiryTime = now()->subHours(5);
        $staleResults = \DB::table('scraped_results')
            ->where('created_at', '<', $expiryTime)
            ->get();

        $count = 0;
        foreach ($staleResults as $result) {
            $jsonFile = $result->json_file;
            $platform = $result->platform;

            // Determine file path based on platform mapping
            if ($platform === 'hld') {
                $path = storage_path("app/scraped_json/{$jsonFile}");
            } else {
                // PropertyGuru files
                $path = storage_path("app/{$jsonFile}");
            }

            if (file_exists($path)) {
                unlink($path);
            }

            // Also cleanup potential intermediate HTML files if they exist (PG only)
            if ($platform !== 'hld') {
                // If it's a PG guru_results search, we might have pguru_page_*.html etc. 
                // However, the controller mostly uses pguru_uuid.json.
                // The UpdatePropertyGuruCache command generates pguru_page_*.html.
                // Those are usually named pguru_page_1.html etc. which are generic.
                // We mainly care about the individual client result files.
            }

            \DB::table('scraped_results')->where('id', $result->id)->delete();
            $count++;
        }

        $this->info("Successfully cleared {$count} stale cache records and files.");
        return Command::SUCCESS;
    }
}
