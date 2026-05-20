<?php

use App\Http\Controllers\Admin\DownloadOrderInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('orders/{order}/invoice', DownloadOrderInvoiceController::class)
        ->name('admin.orders.invoice');
});
