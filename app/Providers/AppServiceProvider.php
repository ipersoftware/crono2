<?php

namespace App\Providers;

use App\Models\CampoForm;
use App\Models\Evento;
use App\Models\PersonalAccessToken;
use App\Models\Sessione;
use App\Models\TipologiaPosto;
use Illuminate\Support\Facades\Route;
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

        // Binding espliciti per bypassare lo scoped binding automatico di Laravel 11
        // che risolverebbe {evento} via $ente->eventi()->find($id) perdendo gli attributi
        Route::model('evento',   Evento::class);
        Route::model('sessione', Sessione::class);
        Route::model('tipologia_posto', TipologiaPosto::class);
        Route::model('campo',    CampoForm::class);
    }
}
