use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

$file = 'test_small.csv';
// Create a CSV with header + 1 row
$content = "Client Name,District,Price,Near to MRT,Tenure,House Type,Land Size,Condition,Bedrooms,Parking,Lift,Pool,Granny Room,Facing\n";
$content .= "TestClient1,19,5mil,Yes,Freehold,Terrace,2000,Good,5,2,Yes,Yes,Yes,North\n";

// Ensure directory exists
if (!is_dir(storage_path('app/uploads'))) {
    mkdir(storage_path('app/uploads'), 0755, true);
}

file_put_contents(storage_path('app/uploads/' . $file), $content);

$path = storage_path('app/uploads/' . $file);
echo "Testing with file: $path\n";

try {
    $rows = Excel::toCollection(null, $path)->first();
    echo "Rows count (including header): " . $rows->count() . "\n";
    
    echo "Iterating rows (skip 1):\n";
    $count = 0;
    foreach ($rows->skip(1) as $row) {
        $count++;
        echo "Row found: " . ($row[0] ?? 'empty') . "\n";
    }
    echo "Total data rows processed: $count\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
