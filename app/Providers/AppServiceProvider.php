<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Usa il nostro PersonalAccessToken esteso (con impersonated_ente_id)
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
