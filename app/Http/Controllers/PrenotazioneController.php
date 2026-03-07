<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Prenotazione;
use App\Models\PrenotazionePosto;
use App\Models\PrenotazioneTemporanea;
use App\Models\RispostaForm;
use App\Models\Sessione;
use App\Models\SessioneTipologiaPosto;
use App\Services\NotificaService;
use App\Services\EventoLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrenotazioneController extends Controller
{
    public function __construct(
        private readonly NotificaService $notifiche,
        private readonly EventoLogService $log,
    ) {}

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
        abort_if(!$sessione->prenotabile, 422, 'Sessione non aperta alle prenotazioni.');
        abort_if($sessione->forza_non_disponibile, 422, 'Sessione non disponibile.');

        return DB::transaction(function () use ($data, $sessione, $evento) {
            // Lock a livello di riga per evitare race conditions
            $sessione = Sessione::lockForUpdate()->find($sessione->id);

            // Purga lock scaduti e ripristina i posti_riservati
            $lockScaduti = PrenotazioneTemporanea::where('sessione_id', $sessione->id)
                ->where('scadenza_at', '<=', now())
                ->get();
            foreach ($lockScaduti as $lockScaduto) {
                $this->rilasciaLockInterno($lockScaduto);
            }
            // Rilegge la sessione aggiornata dopo la purga
            $sessione = Sessione::lockForUpdate()->find($sessione->id);

            $totaleRichiesto = collect($data['posti'])->sum('quantita');

            // Verifica disponibilità globale (se la sessione ha un limite totale)
            if ($sessione->posti_totali > 0) {
                $disponibili = $sessione->posti_disponibili - $sessione->posti_riservati;
                abort_if($disponibili < $totaleRichiesto, 422, 'Posti insufficienti. Disponibili: ' . $disponibili . '.');
            }

            // Verifica disponibilità per tipologia e vincoli min/max prenotabili
            foreach ($data['posti'] as $richiesta) {
                $st = SessioneTipologiaPosto::lockForUpdate()
                    ->where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $richiesta['tipologia_id'])
                    ->first();

                // Verifica per tipologia (se la tipologia ha un limite proprio)
                if ($st && $st->posti_totali > 0) {
                    $dispTip = $st->posti_disponibili - $st->posti_riservati;
                    abort_if($dispTip < $richiesta['quantita'], 422,
                        "Posti insufficienti per la tipologia #{$richiesta['tipologia_id']}.");
                }

                // Verifica min/max configurati sulla tipologia
                $tipologia = $st ? $st->tipologiaPosto : null;
                if ($tipologia) {
                    if ($tipologia->min_prenotabili && $richiesta['quantita'] < $tipologia->min_prenotabili) {
                        abort(422, "Devi prenotare almeno {$tipologia->min_prenotabili} posti per \"{$tipologia->nome}\".");
                    }
                    if ($tipologia->max_prenotabili && $richiesta['quantita'] > $tipologia->max_prenotabili) {
                        abort(422, "Puoi prenotare al massimo {$tipologia->max_prenotabili} posti per \"{$tipologia->nome}\".");
                    }
                }
            }

            // Crea lock temporaneo
            $token = Str::uuid()->toString();
            $lock  = PrenotazioneTemporanea::create([
                'sessione_id'        => $sessione->id,
                'token'              => $token,
                'posti_totali'       => $totaleRichiesto,
                'dettaglio_tipologie'=> $data['posti'],
                'scadenza_at'        => now()->addMinutes($sessione->durata_lock_minuti ?? config('booking.lock_minutes', 15)),
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
            'privacy_ok'                 => 'required|boolean|accepted',
        ]);

        $lock = PrenotazioneTemporanea::where('token', $data['token'])
            ->where('scadenza_at', '>', now())
            ->first();

        abort_if(!$lock, 422, 'Il lock è scaduto o non valido. Ricominciare la prenotazione.');

        $sessione = Sessione::findOrFail($lock->sessione_id);
        $evento   = $sessione->evento;

        // Verifica duplicato:
        // - sempre: stessa sessione (non ha senso prenotare due volte la stessa data)
        // - se consenti_multi_sessione = false: anche qualsiasi altra sessione dello stesso evento
        $queryDuplicato = Prenotazione::whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE', 'RISERVATA'])
            ->where(function ($q) use ($request, $data) {
                if ($request->user()) {
                    $q->where('user_id', $request->user()->id);
                } else {
                    $q->where('email', $data['email']);
                }
            });

        if ($evento->consenti_multi_sessione) {
            // Permette prenotazioni su sessioni diverse, ma non sulla stessa sessione due volte
            $queryDuplicato->where('sessione_id', $sessione->id);
            $messaggioDuplicato = 'Esiste già una prenotazione attiva per questa sessione.';
        } else {
            // Non permette più prenotazioni sullo stesso evento
            $queryDuplicato->whereHas('sessione', fn($q) => $q->where('evento_id', $evento->id));
            $messaggioDuplicato = 'Esiste già una prenotazione attiva per questo evento.';
        }

        abort_if($queryDuplicato->exists(), 422, $messaggioDuplicato);

        return DB::transaction(function () use ($data, $lock, $sessione, $evento, $request) {
            $codice = $this->generaCodice();
            $totale = collect($data['posti'])->sum(fn($p) => ($p['costo_unitario'] ?? 0) * $p['quantita']);

            $statoIniziale = $evento->richiede_approvazione ? 'DA_CONFERMARE' : 'CONFERMATA';

            $prenotazione = Prenotazione::create([
                'ente_id'           => $evento->ente_id,
                'sessione_id'       => $sessione->id,
                'user_id'           => $request->user()?->id,
                'codice'            => $codice,
                'token_accesso'     => Str::random(48),
                'nome'              => $data['nome'],
                'cognome'           => $data['cognome'],
                'email'             => $data['email'],
                'telefono'          => $data['telefono'] ?? null,
                'note'              => $data['note'] ?? null,
                'privacy_ok'        => true,
                'stato'             => $statoIniziale,
                'posti_prenotati'   => collect($data['posti'])->sum('quantita'),
                'costo_totale'      => $totale,
                'data_prenotazione' => now(),
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
                    'valore'          => $risposta['risposta'],
                ]);
            }

            $totaleQuantita = collect($data['posti'])->sum('quantita');

            // Aggiorna posti: converti riservati → confermati
            $sessione->decrement('posti_riservati', $totaleQuantita);
            $sessione->decrement('posti_disponibili', $totaleQuantita);

            foreach ($data['posti'] as $posto) {
                SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $posto['tipologia_id'])
                    ->each(function ($st) use ($posto) {
                        $st->decrement('posti_riservati', $posto['quantita']);
                        $st->decrement('posti_disponibili', $posto['quantita']);
                    });
            }

            // Elimina il lock
            $lock->delete();

            // Carica relazioni per le notifiche
            $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi', 'posti.tipologiaPosto']);

            // Notifica utente
            $tipoNotifica = $statoIniziale === 'CONFERMATA' ? 'PRENOTAZIONE_CONFERMATA' : 'PRENOTAZIONE_DA_CONFERMARE';
            $this->notifiche->invia($prenotazione, $tipoNotifica);
            $this->notifiche->inviaNotificaStaff($prenotazione);

            return response()->json($prenotazione->load(['posti', 'risposteForm']), 201);
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
            ->with(['sessione.evento', 'posti.tipologiaPosto'])
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
            || ($tokenGuest && hash_equals((string) $prenotazione->token_accesso, (string) $tokenGuest));

        abort_if(!$autorizzato, 403, 'Accesso non autorizzato.');

        return response()->json(
            $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi', 'posti.tipologiaPosto', 'risposteForm.campoForm'])
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
            || ($tokenGuest && hash_equals((string) $prenotazione->token_accesso, (string) $tokenGuest));

        abort_if(!$autorizzato, 403, 'Accesso non autorizzato.');
        abort_if(!$prenotazione->isAnnullabile(), 422, 'Prenotazione non annullabile.');

        $motivo = $request->input('motivo_annullamento');

        return $this->eseguiAnnullamento($prenotazione, 'ANNULLATA_UTENTE', $motivo);
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
            ->with(['sessione.evento', 'posti.tipologiaPosto', 'risposteForm.campoForm'])
            ->orderByDesc('created_at');

        if ($request->filled('evento_id')) {
            $eventoId = (int) $request->evento_id;
            $sessioniIds = \App\Models\Sessione::where('evento_id', $eventoId)->pluck('id');
            $q->whereIn('sessione_id', $sessioniIds);
        }
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
            'stato' => 'CONFERMATA',
        ]);

        $this->notifiche->invia($prenotazione, 'PRENOTAZIONE_APPROVATA');
        $this->log->log(
            $prenotazione->sessione->evento_id,
            'prenotazione.approvata',
            "Prenotazione {$prenotazione->codice} approvata ({$prenotazione->cognome} {$prenotazione->nome})."
        );

        return response()->json($prenotazione);
    }

    /**
     * DELETE /api/enti/{ente}/prenotazioni/{prenotazione}
     * Annulla prenotazione da operatore.
     */
    public function annullaAdmin(Request $request, Ente $ente, Prenotazione $prenotazione): JsonResponse
    {
        abort_if((int) $prenotazione->ente_id !== (int) $ente->id, 403, 'Non autorizzato.');
        abort_if(in_array($prenotazione->stato, ['ANNULLATA_UTENTE', 'ANNULLATA_ADMIN', 'SCADUTA']),
            422, 'Prenotazione già annullata.');

        $motivo = $request->input('motivo_annullamento');

        return $this->eseguiAnnullamento($prenotazione, 'ANNULLATA_ADMIN', $motivo);
    }

    // ----------------------------------------
    // HELPERS
    // ----------------------------------------

    private function eseguiAnnullamento(Prenotazione $prenotazione, string $nuovoStato, ?string $motivo = null): JsonResponse
    {
        return DB::transaction(function () use ($prenotazione, $nuovoStato, $motivo) {
            $prenotazione->update([
                'stato'               => $nuovoStato,
                'data_annullamento'   => now(),
                'motivo_annullamento' => $motivo,
            ]);

            // Libera i posti
            $sessione = Sessione::find($prenotazione->sessione_id);
            if ($sessione) {
                $totale = $prenotazione->posti()->sum('quantita');
                $sessione->increment('posti_disponibili', $totale);

                foreach ($prenotazione->posti as $posto) {
                    SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                        ->where('tipologia_posto_id', $posto->tipologia_posto_id)
                        ->increment('posti_disponibili', $posto->quantita);
                }
            }

        // Notifica utente annullamento
        $tipoNotifica = $nuovoStato === 'ANNULLATA_UTENTE' ? 'PRENOTAZIONE_ANNULLATA_UTENTE' : 'PRENOTAZIONE_ANNULLATA_OPERATORE';
        $this->notifiche->invia($prenotazione, $tipoNotifica);

        $attore = $nuovoStato === 'ANNULLATA_UTENTE' ? 'dall\'utente' : 'dall\'operatore';
        $motivoLog = $motivo ? " Motivo: {$motivo}." : '';
        $this->log->log(
            $prenotazione->sessione->evento_id,
            'prenotazione.annullata',
            "Prenotazione {$prenotazione->codice} annullata {$attore} ({$prenotazione->cognome} {$prenotazione->nome}).{$motivoLog}",
            $motivo ? ['motivo' => $motivo] : null
        );

        return response()->json(['message' => 'Prenotazione annullata.', 'codice' => $prenotazione->codice]);
        });
    }

    private function rilasciaLockInterno(PrenotazioneTemporanea $lock): void
    {
        $esegui = function () use ($lock) {
            $sessione = Sessione::find($lock->sessione_id);
            if ($sessione) {
                $posti = collect($lock->dettaglio_tipologie);
                $totale = $posti->sum('quantita');
                $sessione->decrement('posti_riservati', $totale);

                foreach ($posti as $posto) {
                    SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                        ->where('tipologia_posto_id', $posto['tipologia_id'])
                        ->decrement('posti_riservati', $posto['quantita']);
                }
            }
            $lock->delete();
        };

        // Se siamo già dentro una transazione (es. purga durante lock), esegui direttamente
        if (DB::transactionLevel() > 0) {
            $esegui();
        } else {
            DB::transaction($esegui);
        }
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
