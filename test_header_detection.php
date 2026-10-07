<?php

function testHeaderDetection($row) {
    echo "Testing row: " . json_encode($row) . "\n";
    $firstCell = trim($row[0] ?? '');
    $isHeader = false;

    // Logic from Controller
    // Check first column for "Client Name" or "client_name"
    if (stripos($firstCell, 'Client Name') !== false || stripos($firstCell, 'client_name') !== false) {
        $isHeader = true;
    }
    // Check if second column is "district" (strong signal)
    if (!$isHeader && isset($row[1]) && strtolower(trim($row[1])) === 'district') {
        $isHeader = true;
    }

    if ($isHeader) {
        echo " -> HEADER DETECTED (Skipped)\n";
    } else {
        echo " -> DATA ROW (Processed)\n";
    }
    echo "--------------------------------------------------\n";
}

// Test Cases
testHeaderDetection(['Client Name', 'District', 'Price']); // Standard Header
testHeaderDetection(['client_name', 'district', 'price']); // Snake Case Header (User's case)
testHeaderDetection(['client name', 'District', 'Price']); // Variation
testHeaderDetection(['Name', 'District', 'Price']);        // "Name" might fail first check, but "District" should catch it
testHeaderDetection(['John Doe', 'D15', '2000000']);       // Data Row
testHeaderDetection(['Alice', 'District 10', '5mil']);     // Data Row (but 'District 10' != 'district')

