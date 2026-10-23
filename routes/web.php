<?php

use App\Http\Controllers\AliasController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BrowseController::class, 'index'])->name('repositories');
Route::get('/index.php', [BrowseController::class, 'index']);
Route::get('/listing.php', [BrowseController::class, 'listing'])->name('listing');
Route::get('/log.php', [BrowseController::class, 'log'])->name('log');
Route::get('/revision.php', [BrowseController::class, 'revision'])->name('revision');
Route::get('/search.php', [BrowseController::class, 'search'])->name('search');
Route::get('/filedetails.php', [CodeController::class, 'source'])->name('source');
Route::get('/diff.php', [CodeController::class, 'diff'])->name('diff');
Route::get('/blame.php', [CodeController::class, 'blame'])->name('blame');

Route::get('/comp.php', CompareController::class)->name('compare');
Route::get('/rss.php', FeedController::class)->name('rss');
Route::get('/dl.php', DownloadController::class)->name('download');
foreach (['browse', 'browse.php', 'multiviews.php'] as $alias) {
    Route::get('/'.$alias.'/{location?}', AliasController::class)->where('location', '.*');
}
