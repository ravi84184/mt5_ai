<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::redirect('/dashboard', '/admin');
Route::redirect('/dashboard/login', '/admin/login');

Route::prefix('admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));
