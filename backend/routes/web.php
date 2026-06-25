<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::prefix('dashboard')
    ->name('dashboard.')
    ->group(base_path('routes/dashboard.php'));
