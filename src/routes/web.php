<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;

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
    return redirect()->route('imports.create');
});

Route::get('/imports/create', [ImportController::class, 'create'])->name('imports.create');
Route::get('/imports', function () {
    return redirect()->route('imports.create');
});
Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
Route::get('/imports/{import}/status', [ImportController::class, 'status'])->name('imports.status');
Route::get('/imports/{import}/errors', [ImportController::class, 'errors'])->name('imports.errors');
