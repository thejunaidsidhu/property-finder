<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapeCache extends Model
{
    use HasFactory;

    protected $table = 'scrape_cache';
    protected $fillable = ['platform', 'criteria_hash', 'json_file'];
}
