<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GovernancePrivacyService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.governance.url', ''), '/');
        $this->token   = config('services.governance.token', '');
    }

    /**
     * Restituisce il template informativa attivo per tipo e ente (con cache 1h).
     * Ritorna null se il servizio non è configurato, il template non esiste o la chiamata fallisce.
     *
     * @return array{trovato:bool,versione:string,contenuto:string}|null
     */
    public function getInformativa(string $tipo, int $enteId): ?array
    {
        if (!$this->baseUrl || !$this->token) {
            return null;
        }

        $cacheKey = "privacy.{$tipo}.ente.{$enteId}";

        return Cache::remember($cacheKey, 3600, function () use ($tipo, $enteId) {
            try {
                $response = Http::withToken($this->token)
                    ->acceptJson()
                    ->timeout(5)
                    ->get("{$this->baseUrl}/api/privacy/render/{$tipo}/ente/{$enteId}");

                if ($response->notFound()) {
                    return null;
                }

                $data = $response->throw()->json();

                if (!empty($data['variabili_mancanti'])) {
                    Log::warning('GovernancePrivacy: variabili mancanti nel template', [
                        'tipo'     => $tipo,
                        'ente_id'  => $enteId,
                        'mancanti' => $data['variabili_mancanti'],
                    ]);
                }

                return $data;
            } catch (\Throwable $e) {
                Log::error('GovernancePrivacyService: errore chiamata API', [
                    'tipo'    => $tipo,
                    'ente_id' => $enteId,
                    'error'   => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    public function invalidaCache(string $tipo, int $enteId): void
    {
        Cache::forget("privacy.{$tipo}.ente.{$enteId}");
    }
}
