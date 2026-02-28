<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GitHubWebhookController;
use Illuminate\Support\Facades\Route;

// GitHub webhook (must be before catch-all)
Route::post('/github/hook', [GitHubWebhookController::class, 'hook']);

// Keycloak authentication routes (must be before catch-all)
Route::get('/auth/keycloak', [AuthController::class, 'redirectToKeycloak']);
Route::get('/auth/keycloak/callback', [AuthController::class, 'handleKeycloakCallback']);

// Catch-all route for Vue.js SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
