<?php

use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::post('generations/estimate', [GenerationController::class, 'estimate'])
        ->middleware('throttle:120,1')
        ->name('generations.estimate');
});

require __DIR__.'/settings.php';
