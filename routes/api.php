<?php

use App\Http\Controllers\Api\JobProgressController;
use App\Http\Controllers\Api\WilayahController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| Routes that don't require authentication
|
*/
Route::middleware(['throttle:wilayah-public', 'rate-limit-handler'])->group(function () {
    // Wilayah API endpoints
    Route::prefix('wilayah')->group(function () {
        Route::get('/provinces', [WilayahController::class, 'provinces']);
        Route::get('/regencies/{provinceId}', [WilayahController::class, 'regencies']);
        Route::get('/districts/{regencyId}', [WilayahController::class, 'districts']);
        Route::get('/villages/{districtId}', [WilayahController::class, 'villages']);
        Route::get('/hierarchy/{provinceId}', [WilayahController::class, 'hierarchy']);
        Route::get('/search/provinces', [WilayahController::class, 'searchProvinces']);
        Route::get('/popular/provinces', [WilayahController::class, 'popularProvinces']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
|
| Routes that require authentication
|
*/
// Web-based API routes (for authenticated web users)
Route::middleware(['api-web', 'auth:web'])->group(function () {

    // Job Progress API Routes
    Route::prefix('jobs')->name('api.jobs.')->group(function () {
        Route::get('/active', [JobProgressController::class, 'getActiveJobs'])->name('active');
        Route::get('/recent', [JobProgressController::class, 'getRecentJobs'])->name('recent');
        Route::get('/stats', [JobProgressController::class, 'getJobStats'])->name('stats');
        Route::get('/{jobId}', [JobProgressController::class, 'getJobProgress'])->name('show');
        Route::post('/{jobId}/cancel', [JobProgressController::class, 'cancelJob'])->name('cancel');
        Route::delete('/{jobId}', [JobProgressController::class, 'deleteJob'])->name('delete');
    });

});
