<?php

use Illuminate\Support\Facades\Route;
use Vaharadev\LaravelClient\Http\Controllers\VaharaController;

Route::get('_vahara_publish', [VaharaController::class, 'publish']);
