<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PropertyFinderController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', [PropertyFinderController::class, 'index'])->name('propertyfinder.index');
Route::post('/upload', [PropertyFinderController::class, 'upload'])->name('propertyfinder.upload');

Route::get('/guru/filter', [PropertyFinderController::class, 'filter']);
Route::post('/listing/label', [PropertyFinderController::class, 'labelListing'])->name('listing.label');
Route::get('/upload', [PropertyController::class, 'index']);

