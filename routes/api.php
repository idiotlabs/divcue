<?php

use Illuminate\Support\Facades\Route;

Route::post('/etl/dividend', [\App\Http\Controllers\EtlWebhookController::class, 'store']);
