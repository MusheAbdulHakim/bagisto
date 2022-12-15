<?php

use Mushe\Rave\Http\Controllers\FlutterwaveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'theme', 'locale', 'currency'])->group(function () {
    Route::get('rave-checkout', [FlutterwaveController::class, 'index'])->name('rave.redirect');
    Route::get('rave-verify', [FlutterwaveController::class, 'verify'])->name('rave.verify');
});