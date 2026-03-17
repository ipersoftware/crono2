<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VetrinaMetaController;
use Illuminate\Support\Facades\Route;

// GitHub webhook (must be before catch-all)
Route::post('/github/hook', [GitHubWebhookController::class, 'hook']);

// Keycloak authentication routes (must be before catch-all)
Route::get('/auth/keycloak', [AuthController::class, 'redirectToKeycloak']);
Route::get('/auth/keycloak/callback', [AuthController::class, 'handleKeycloakCallback']);

// Sitemap XML
Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// Redirect compatibilità crono1 — deve stare prima della catch-all SPA
Route::get('/search', [LegacyRedirectController::class, 'search']);

// Vetrina pubblica — meta OG server-side per social crawler (PRIMA della catch-all)
Route::get('/vetrina/{shopUrl}/eventi/{slug}', [VetrinaMetaController::class, 'evento']);
Route::get('/vetrina/{shopUrl}',               [VetrinaMetaController::class, 'home']);

// Catch-all route for Vue.js SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
