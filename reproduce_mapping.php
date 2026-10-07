<?php

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

function testMapping($districtInput, $houseTypeInput, $districtMap, $houseTypeMap) {
    echo "Testing: District='$districtInput', HouseType='$houseTypeInput'\n";

    // Logic from Controller
    $districtParts = explode('/', $districtInput);
    $districtIds = [];
    foreach ($districtParts as $part) {
        $part = strtolower(trim($part));
        if (isset($districtMap[$part])) $districtIds[] = $districtMap[$part];
    }
    
    $houseTypeId = $houseTypeMap[strtolower($houseTypeInput)] ?? null;

    if (!$houseTypeId || empty($districtIds)) {
        echo " -> FAILED: Invalid district or house type mapping\n";
        echo "    Mapped District IDs: " . json_encode($districtIds) . "\n";
        echo "    Mapped House Type ID: " . ($houseTypeId ?? 'NULL') . "\n";
    } else {
        echo " -> SUCCESS\n";
    }
    echo "--------------------------------------------------\n";
}

// Test cases from user
testMapping("D15", "detached", $districtMap, $houseTypeMap); // Expected to fail due to lowercase 'detached'
testMapping("D20", "terrace", $districtMap, $houseTypeMap);  // Expected to fail due to lowercase 'terrace'

// Valid cases for comparison
testMapping("D15", "Detached", $districtMap, $houseTypeMap);
testMapping("D20", "Terrace", $districtMap, $houseTypeMap);
