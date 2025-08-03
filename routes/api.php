<?php

use Illuminate\Support\Facades\Route;

Route::post('/etl/dividend', [EtlWebhookController::class, 'store']);
