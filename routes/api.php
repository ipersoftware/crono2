<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampoFormController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EnteController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\ListaAttesaController;
use App\Http\Controllers\EventoLogController;
use App\Http\Controllers\LuogoController;
use App\Http\Controllers\MailTemplateController;
use App\Http\Controllers\MonitoraggioController;
use App\Http\Controllers\PrenotazioneController;
use App\Http\Controllers\RichiestaContattoController;
use App\Http\Controllers\SerieController;
use App\Http\Controllers\SessioneController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TipologiaPostoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\VetrinaController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------
// Landing page (no auth)
// -------------------------------------------------------
Route::post('/contatto-piattaforma', [LandingController::class, 'contatto']);

// -------------------------------------------------------
// Auth
// -------------------------------------------------------
Route::get('/auth/provider', [AuthController::class, 'provider']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// -------------------------------------------------------
// Vetrina pubblica (no auth)
// -------------------------------------------------------
Route::prefix('vetrina/{shopUrl}')->group(function () {
    Route::get('/',        [VetrinaController::class, 'index']);
    Route::get('/eventi',  [VetrinaController::class, 'eventi']);
    Route::get('/eventi/{slug}', [VetrinaController::class, 'evento']);
    Route::get('/serie',   [VetrinaController::class, 'serie']);
    Route::get('/tags',    [VetrinaController::class, 'tags']);
    Route::post('/contatto', [VetrinaController::class, 'contatto']);
});

// Serve documenti — pubblico, no auth (URL opaco con ID)
Route::get('/documents/{document}/serve', [DocumentController::class, 'serve']);

// -------------------------------------------------------
// Prenotazioni pubbliche (auth opzionale)
// -------------------------------------------------------
Route::post('/prenotazioni/lock',              [PrenotazioneController::class, 'lock']);
Route::delete('/prenotazioni/lock/{token}',    [PrenotazioneController::class, 'rilasciaLock']);
Route::post('/prenotazioni',                   [PrenotazioneController::class, 'store']);
Route::get('/prenotazioni/{codice}',           [PrenotazioneController::class, 'show']);
Route::delete('/prenotazioni/{codice}',        [PrenotazioneController::class, 'annullaUtente']);
Route::post('/prenotazioni/lista-attesa',       [ListaAttesaController::class, 'store']);
Route::post('/lista-attesa/{token}/conferma',   [ListaAttesaController::class, 'conferma']);

// -------------------------------------------------------
// Rotte protette
// -------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Impersonificazione ente (solo admin)
    Route::post('/enti/{ente}/impersonate',  [AuthController::class, 'impersonateEnte']);
    Route::delete('/auth/impersonate',       [AuthController::class, 'stopImpersonateEnte']);

    // Import da Governance (solo admin)
    Route::get('/enti/governance/disponibili', [EnteController::class, 'governanceDisponibili']);
    Route::post('/enti/governance/importa',    [EnteController::class, 'importaDaGovernance']);
    Route::post('/enti/{ente}/sincronizza-template',       [EnteController::class, 'sincronizzaTemplate']);
    Route::post('/enti/{ente}/aggiorna-da-governance',      [EnteController::class, 'aggiornaAnagraficaGovernance']);
    Route::patch('/enti/{ente}/vetrina',                    [EnteController::class, 'aggiornaVetrina']);
    Route::post('/enti/{ente}/vetrina/copertina',           [EnteController::class, 'uploadCopertina']);
    Route::delete('/enti/{ente}/vetrina/copertina',         [EnteController::class, 'eliminaCopertina']);

    // Prenotazioni utente autenticato
    Route::get('/prenotazioni/mie', [PrenotazioneController::class, 'mie']);

    // Users & Enti base
    Route::apiResource('users', UserController::class);
    Route::apiResource('enti',  EnteController::class)->parameters(['enti' => 'ente']);

    // -------------------------------------------------------
    // Rotte admin per ente — protette da EnsureEnteAccess
    // -------------------------------------------------------
    Route::prefix('enti/{ente}')
        ->middleware('ente.access')
        ->group(function () {

            // Tags
            Route::apiResource('tags', TagController::class)->except(['show']);

            // Luoghi
            Route::apiResource('luoghi', LuogoController::class);

            // Serie
            Route::apiResource('serie', SerieController::class);

            // Eventi
            Route::apiResource('eventi', EventoController::class)
                ->parameters(['eventi' => 'evento']);
            Route::post('eventi/{evento}/pubblica',   [EventoController::class, 'pubblica']);
            Route::post('eventi/{evento}/sospendi',   [EventoController::class, 'sospendi']);
            Route::post('eventi/{evento}/annulla',    [EventoController::class, 'annulla']);
            Route::get('eventi/{evento}/log',         [EventoLogController::class, 'index']);
            Route::get('eventi/{evento}/monitoraggio', [MonitoraggioController::class, 'evento']);
            Route::post('eventi/{evento}/immagine',   [EventoController::class, 'uploadImmagine']);
            Route::delete('eventi/{evento}/immagine', [EventoController::class, 'eliminaImmagine']);

            // Documenti (elimina)
            Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

            // Sessioni (nested sotto evento)
            Route::apiResource('eventi/{evento}/sessioni', SessioneController::class)
                ->parameters(['sessioni' => 'sessione']);

            // Tipologie posto (nested sotto evento)
            Route::apiResource('eventi/{evento}/tipologie-posto', TipologiaPostoController::class)
                ->parameters(['tipologie-posto' => 'tipologia_posto']);

            // Campi form (nested sotto evento)
            Route::get('eventi/{evento}/campi-form',                   [CampoFormController::class, 'index']);
            Route::post('eventi/{evento}/campi-form',                  [CampoFormController::class, 'store']);
            Route::put('eventi/{evento}/campi-form/{campo}',           [CampoFormController::class, 'update']);
            Route::delete('eventi/{evento}/campi-form/{campo}',        [CampoFormController::class, 'destroy']);
            Route::post('eventi/{evento}/campi-form/riordina',         [CampoFormController::class, 'riordina']);

            // Prenotazioni (gestione admin)
            Route::get('prenotazioni',                                  [PrenotazioneController::class, 'indexAdmin']);
            Route::get('prenotazioni/export-xls',                       [PrenotazioneController::class, 'exportXls']);
            Route::patch('prenotazioni/{prenotazione}/approva',         [PrenotazioneController::class, 'approva']);
            Route::delete('prenotazioni/{prenotazione}',                [PrenotazioneController::class, 'annullaAdmin']);

            // Mail templates
            Route::get('mail-templates',                                [MailTemplateController::class, 'index']);
            Route::get('mail-templates/{tipo}',                         [MailTemplateController::class, 'show']);
            Route::post('mail-templates',                               [MailTemplateController::class, 'store']);
            Route::put('mail-templates/{mailTemplate}',                 [MailTemplateController::class, 'update']);
            Route::delete('mail-templates/{mailTemplate}',              [MailTemplateController::class, 'destroy']);
            Route::get('mail-templates/{mailTemplate}/anteprima',       [MailTemplateController::class, 'anteprima']);

            // Richieste contatto
            Route::get('richieste-contatto',                                          [RichiestaContattoController::class, 'index']);
            Route::patch('richieste-contatto/{richiesta}/letta',                      [RichiestaContattoController::class, 'segnaLetta']);
            Route::delete('richieste-contatto/{richiesta}',                           [RichiestaContattoController::class, 'destroy']);
        });
});

// -------------------------------------------------------
// Health check
// -------------------------------------------------------
Route::get('/health', function () {
    return response()->json([
        'status'    => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});
