<?php

namespace App\Imports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientsImport implements ToModel, SkipsEmptyRows, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Skip rows that don't have a client name (first column)
        // Check if client name exists and is not empty
        $clientName = trim($row[0] ?? '');

        if (empty($clientName)) {
            return null; // Skip this row
        }

        return new Client([
            'name' => $clientName,
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
        ]);
    }
}
