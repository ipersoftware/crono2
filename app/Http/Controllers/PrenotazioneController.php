<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Prenotazione;
use App\Models\PrenotazionePosto;
use App\Models\PrenotazioneTemporanea;
use App\Models\RispostaForm;
use App\Models\Sessione;
use App\Models\SessioneTipologiaPosto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrenotazioneController extends Controller
{
    // ----------------------------------------
    // FLUSSO PUBBLICO
    // ----------------------------------------

    /**
     * POST /api/prenotazioni/lock
     * Acquisisci un lock temporale sui posti desiderati.
     */
    public function lock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sessione_id'          => 'required|integer|exists:sessioni,id',
            'posti'                => 'required|array|min:1',
            'posti.*.tipologia_id' => 'required|integer|exists:tipologie_posto,id',
            'posti.*.quantita'     => 'required|integer|min:1',
        ]);

        $sessione = Sessione::with('tipologiePosto')->findOrFail($data['sessione_id']);
        $evento   = $sessione->evento;

        abort_if($evento->stato !== 'PUBBLICATO', 422, 'Evento non disponibile alla prenotazione.');
        abort_if($sessione->stato !== 'APERTA', 422, 'Sessione non aperta alle prenotazioni.');

        return DB::transaction(function () use ($data, $sessione, $evento) {
            // Lock a livello di riga per evitare race conditions
            $sessione = Sessione::lockForUpdate()->find($sessione->id);

            $totaleRichiesto = collect($data['posti'])->sum('quantita');

            // Verifica disponibilità globale
            if ($sessione->controlla_posti_globale) {
                $disponibili = $sessione->posti_totali
                    - $sessione->posti_prenotati
                    - $sessione->posti_riservati;

                if (!$sessione->overbooking) {
                    abort_if($disponibili < $totaleRichiesto, 422, 'Posti insufficienti.');
                }
            }

            // Verifica disponibilità per tipologia
            foreach ($data['posti'] as $richiesta) {
                $st = SessioneTipologiaPosto::lockForUpdate()
                    ->where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $richiesta['tipologia_id'])
                    ->first();

                if ($st && !$sessione->controlla_posti_globale) {
                    $dispTip = $st->posti_disponibili - $st->posti_prenotati - $st->posti_riservati;
                    abort_if($dispTip < $richiesta['quantita'], 422,
                        "Posti insufficienti per la tipologia #{$richiesta['tipologia_id']}.");
                }
            }

            // Crea lock temporaneo
            $token = Str::uuid()->toString();
            $lock  = PrenotazioneTemporanea::create([
                'sessione_id' => $sessione->id,
                'token'       => $token,
                'posti_json'  => $data['posti'],
                'scadenza_at' => now()->addMinutes(config('booking.lock_minutes', 15)),
            ]);

            // Incrementa posti_riservati sulla sessione
            $sessione->increment('posti_riservati', $totaleRichiesto);

            // Incrementa posti_riservati per tipologia
            foreach ($data['posti'] as $richiesta) {
                SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $richiesta['tipologia_id'])
                    ->increment('posti_riservati', $richiesta['quantita']);
            }

            return response()->json([
                'token'       => $token,
                'scadenza_at' => $lock->scadenza_at,
            ], 201);
        });
    }

    /**
     * DELETE /api/prenotazioni/lock/{token}
     * Rilascia un lock temporale.
     */
    public function rilasciaLock(string $token): JsonResponse
    {
        $lock = PrenotazioneTemporanea::where('token', $token)->first();

        if (!$lock) {
            return response()->json(['message' => 'Lock non trovato o già scaduto.'], 404);
        }

        $this->rilasciaLockInterno($lock);

        return response()->json(['message' => 'Lock rilasciato.']);
    }

    /**
     * POST /api/prenotazioni
     * Conferma prenotazione (richiede lock valido).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'                      => 'required|string|exists:prenotazioni_temporanee,token',
            'nome'                       => 'required|string|max:255',
            'cognome'                    => 'required|string|max:255',
            'email'                      => 'required|email|max:255',
            'telefono'                   => 'nullable|string|max:50',
            'note'                       => 'nullable|string',
            'posti'                      => 'required|array|min:1',
            'posti.*.tipologia_id'       => 'required|integer|exists:tipologie_posto,id',
            'posti.*.quantita'           => 'required|integer|min:1',
            'posti.*.costo_unitario'     => 'nullable|numeric|min:0',
            'risposte'                   => 'nullable|array',
            'risposte.*.campo_form_id'   => 'required|integer|exists:campi_form,id',
            'risposte.*.risposta'        => 'required|string',
        ]);

        $lock = PrenotazioneTemporanea::where('token', $data['token'])
            ->where('scadenza_at', '>', now())
            ->first();

        abort_if(!$lock, 422, 'Il lock è scaduto o non valido. Ricominciare la prenotazione.');

        $sessione = Sessione::findOrFail($lock->sessione_id);
        $evento   = $sessione->evento;

        // Verifica duplicato
        $duplicato = Prenotazione::where('sessione_id', $sessione->id)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE', 'RISERVATA'])
            ->where(function ($q) use ($request, $data) {
                if ($request->user()) {
                    $q->where('user_id', $request->user()->id);
                } else {
                    $q->where('email', $data['email']);
                }
            })->exists();

        abort_if($duplicato, 422, 'Esiste già una prenotazione attiva per questa sessione.');

        return DB::transaction(function () use ($data, $lock, $sessione, $evento, $request) {
            $codice = $this->generaCodice();
            $totale = collect($data['posti'])->sum(fn($p) => ($p['costo_unitario'] ?? 0) * $p['quantita']);

            $statoIniziale = $evento->richiede_approvazione ? 'DA_CONFERMARE' : 'CONFERMATA';

            $prenotazione = Prenotazione::create([
                'ente_id'           => $evento->ente_id,
                'sessione_id'       => $sessione->id,
                'user_id'           => $request->user()?->id,
                'codice'            => $codice,
                'nome'              => $data['nome'],
                'cognome'           => $data['cognome'],
                'email'             => $data['email'],
                'telefono'          => $data['telefono'] ?? null,
                'note'              => $data['note'] ?? null,
                'stato'             => $statoIniziale,
                'importo_totale'    => $totale,
                'token_accesso'     => Str::random(32),
            ]);

            // Crea i posti prenotati
            foreach ($data['posti'] as $posto) {
                PrenotazionePosto::create([
                    'prenotazione_id'    => $prenotazione->id,
                    'sessione_id'        => $sessione->id,
                    'tipologia_posto_id' => $posto['tipologia_id'],
                    'quantita'           => $posto['quantita'],
                    'costo_unitario'     => $posto['costo_unitario'] ?? 0,
                    'costo_totale'       => ($posto['costo_unitario'] ?? 0) * $posto['quantita'],
                ]);
            }

            // Crea le risposte ai campi form
            foreach ($data['risposte'] ?? [] as $risposta) {
                RispostaForm::create([
                    'prenotazione_id' => $prenotazione->id,
                    'campo_form_id'   => $risposta['campo_form_id'],
                    'risposta'        => $risposta['risposta'],
                ]);
            }

            $totaleQuantita = collect($data['posti'])->sum('quantita');

            // Aggiorna posti: converti riservati → prenotati
            $sessione->decrement('posti_riservati', $totaleQuantita);
            $sessione->increment('posti_prenotati', $totaleQuantita);

            foreach ($data['posti'] as $posto) {
                SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $posto['tipologia_id'])
                    ->each(function ($st) use ($posto) {
                        $st->decrement('posti_riservati', $posto['quantita']);
                        $st->increment('posti_prenotati', $posto['quantita']);
                    });
            }

            // Elimina il lock
            $lock->delete();

            return response()->json($prenotazione->load(['prenotazionePosti', 'risposteForm']), 201);
        });
    }

    /**
     * GET /api/prenotazioni/mie
     * Prenotazioni dell'utente autenticato.
     */
    public function mie(Request $request): JsonResponse
    {
        abort_if(!$request->user(), 401, 'Non autenticato.');

        $prenotazioni = Prenotazione::where('user_id', $request->user()->id)
            ->with(['sessione.evento', 'prenotazionePosti.tipologiaPosto'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($prenotazioni);
    }

    /**
     * GET /api/prenotazioni/{codice}
     * Dettaglio prenotazione (utente o guest con token_accesso).
     */
    public function show(Request $request, string $codice): JsonResponse
    {
        $prenotazione = Prenotazione::where('codice', $codice)->firstOrFail();

        $user = $request->user();
        $tokenGuest = $request->query('token');

        $autorizzato = ($user && (int) $user->id === (int) $prenotazione->user_id)
            || $tokenGuest === $prenotazione->token_accesso;

        abort_if(!$autorizzato, 403, 'Accesso non autorizzato.');

        return response()->json(
            $prenotazione->load(['sessione.evento', 'prenotazionePosti.tipologiaPosto', 'risposteForm.campoForm'])
        );
    }

    /**
     * DELETE /api/prenotazioni/{codice}
     * Annulla prenotazione da parte dell'utente/guest.
     */
    public function annullaUtente(Request $request, string $codice): JsonResponse
    {
        $prenotazione = Prenotazione::where('codice', $codice)->firstOrFail();

        $user = $request->user();
        $tokenGuest = $request->query('token');

        $autorizzato = ($user && (int) $user->id === (int) $prenotazione->user_id)
            || $tokenGuest === $prenotazione->token_accesso;

        abort_if(!$autorizzato, 403, 'Accesso non autorizzato.');
        abort_if(!$prenotazione->isAnnullabile(), 422, 'Prenotazione non annullabile.');

        return $this->eseguiAnnullamento($prenotazione, 'ANNULLATA_UTENTE');
    }

    // ----------------------------------------
    // AREA OPERATORE
    // ----------------------------------------

    /**
     * GET /api/enti/{ente}/prenotazioni
     * Lista prenotazioni per operatore.
     */
    public function indexAdmin(Request $request, Ente $ente): JsonResponse
    {
        $q = Prenotazione::where('ente_id', $ente->id)
            ->with(['sessione.evento', 'prenotazionePosti.tipologiaPosto'])
            ->orderByDesc('created_at');

        if ($request->filled('sessione_id')) {
            $q->where('sessione_id', $request->sessione_id);
        }
        if ($request->filled('stato')) {
            $q->where('stato', $request->stato);
        }
        if ($request->filled('cerca')) {
            $cerca = $request->cerca;
            $q->where(function ($query) use ($cerca) {
                $query->where('codice', 'like', "%{$cerca}%")
                    ->orWhere('email', 'like', "%{$cerca}%")
                    ->orWhere('nome', 'like', "%{$cerca}%")
                    ->orWhere('cognome', 'like', "%{$cerca}%");
            });
        }

        return response()->json($q->paginate(50));
    }

    /**
     * PATCH /api/enti/{ente}/prenotazioni/{prenotazione}/approva
     * Approva una prenotazione in stato DA_CONFERMARE.
     */
    public function approva(Ente $ente, Prenotazione $prenotazione): JsonResponse
    {
        abort_if((int) $prenotazione->ente_id !== (int) $ente->id, 403, 'Non autorizzato.');
        abort_if($prenotazione->stato !== 'DA_CONFERMARE', 422, 'Prenotazione non in attesa di conferma.');

        $prenotazione->update([
            'stato'         => 'CONFERMATA',
            'confermata_at' => now(),
        ]);

        return response()->json($prenotazione);
    }

    /**
     * DELETE /api/enti/{ente}/prenotazioni/{prenotazione}
     * Annulla prenotazione da operatore.
     */
    public function annullaAdmin(Ente $ente, Prenotazione $prenotazione): JsonResponse
    {
        abort_if((int) $prenotazione->ente_id !== (int) $ente->id, 403, 'Non autorizzato.');
        abort_if(in_array($prenotazione->stato, ['ANNULLATA_UTENTE', 'ANNULLATA_ADMIN', 'SCADUTA']),
            422, 'Prenotazione già annullata.');

        return $this->eseguiAnnullamento($prenotazione, 'ANNULLATA_ADMIN');
    }

    // ----------------------------------------
    // HELPERS
    // ----------------------------------------

    private function eseguiAnnullamento(Prenotazione $prenotazione, string $nuovoStato): JsonResponse
    {
        return DB::transaction(function () use ($prenotazione, $nuovoStato) {
            $prenotazione->update([
                'stato'         => $nuovoStato,
                'annullata_at'  => now(),
            ]);

            // Libera i posti
            $sessione = Sessione::find($prenotazione->sessione_id);
            if ($sessione) {
                $totale = $prenotazione->prenotazionePosti()->sum('quantita');
                $sessione->decrement('posti_prenotati', $totale);

                foreach ($prenotazione->prenotazionePosti as $posto) {
                    SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                        ->where('tipologia_posto_id', $posto->tipologia_posto_id)
                        ->decrement('posti_prenotati', $posto->quantita);
                }
            }

            return response()->json(['message' => 'Prenotazione annullata.', 'codice' => $prenotazione->codice]);
        });
    }

    private function rilasciaLockInterno(PrenotazioneTemporanea $lock): void
    {
        DB::transaction(function () use ($lock) {
            $sessione = Sessione::find($lock->sessione_id);
            if ($sessione) {
                $posti = collect($lock->posti_json);
                $totale = $posti->sum('quantita');
                $sessione->decrement('posti_riservati', $totale);

                foreach ($posti as $posto) {
                    SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                        ->where('tipologia_posto_id', $posto['tipologia_id'])
                        ->decrement('posti_riservati', $posto['quantita']);
                }
            }
            $lock->delete();
        });
    }

    private function generaCodice(): string
    {
        $anno = now()->year;
        $ultimo = Prenotazione::whereYear('created_at', $anno)
            ->lockForUpdate()
            ->count();

        return sprintf('CRN-%d-%05d', $anno, $ultimo + 1);
    }
}
