<?php

declare(strict_types=1);

use App\Http\Controllers\ProductBarcodeDownloadController;
use App\Http\Controllers\SubdomainLookupController;
use Illuminate\Support\Facades\Route;

Route::domain(config('app.central_domain'))->group(function () {
    Route::get('/', [SubdomainLookupController::class, 'show'])->name('subdomain.show');
    Route::post('/lookup', [SubdomainLookupController::class, 'lookup'])->name('subdomain.lookup');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('{tenant}/products/{product}/barcode.png', ProductBarcodeDownloadController::class)
        ->name('products.barcode.download');
});
