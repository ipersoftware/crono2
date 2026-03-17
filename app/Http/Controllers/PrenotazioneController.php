<?php

namespace App\Http\Controllers;

use App\Events\PostiEsauriti;
use App\Events\PostiTornatiDisponibili;
use App\Models\CampoForm;
use App\Models\Ente;
use App\Models\Prenotazione;
use App\Models\PrenotazionePosto;
use App\Models\PrenotazioneTemporanea;
use App\Models\RispostaForm;
use App\Models\Sessione;
use App\Models\SessioneTipologiaPosto;
use App\Services\NotificaService;
use App\Services\ListaAttesaService;
use App\Services\EventoLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrenotazioneController extends Controller
{
    public function __construct(
        private readonly NotificaService     $notifiche,
        private readonly EventoLogService    $log,
        private readonly ListaAttesaService  $listaAttesa,
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

            // Snapshot posti liberi prima di incrementare i riservati
            $liberiPrima = $sessione->posti_totali > 0
                ? max(0, $sessione->posti_disponibili - $sessione->posti_riservati)
                : PHP_INT_MAX; // sessione senza limite: non può mai esaurirsi

            // Incrementa posti_riservati sulla sessione
            $sessione->increment('posti_riservati', $totaleRichiesto);

            // Incrementa posti_riservati per tipologia
            foreach ($data['posti'] as $richiesta) {
                SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $richiesta['tipologia_id'])
                    ->increment('posti_riservati', $richiesta['quantita']);
            }

            // Broadcast se la sessione è appena diventata esaurita (transizione >0 → <=0)
            $liberiDopo = $liberiPrima - $totaleRichiesto;
            if ($liberiPrima > 0 && $liberiDopo <= 0) {
                $sessione->refresh();
                broadcast(new PostiEsauriti($sessione));
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
            'privacy_versione'           => 'nullable|string|max:20',
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
            if (!$evento->consenti_prenotazioni_multiple) {
                // Permette sessioni diverse, ma non la stessa sessione due volte
                $queryDuplicato->where('sessione_id', $sessione->id);
                abort_if($queryDuplicato->exists(), 422, 'Esiste già una prenotazione attiva per questa sessione.');
            }
            // consenti_prenotazioni_multiple = true → nessun controllo duplicato
        } else {
            // Non permette più prenotazioni sullo stesso evento
            $queryDuplicato->whereHas('sessione', fn($q) => $q->where('evento_id', $evento->id));
            abort_if($queryDuplicato->exists(), 422, 'Esiste già una prenotazione attiva per questo evento.');
        }

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
                'privacy_versione'  => $data['privacy_versione'] ?? null,
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

            // Chiusura automatica per soglia prenotazioni (solo se confermata subito)
            if ($statoIniziale === 'CONFERMATA') {
                $this->verificaSogliaChiusuraPrenotazioni($sessione);
            }

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
        if ($request->filled('data_dal')) {
            $q->whereDate('data_prenotazione', '>=', $request->data_dal);
        }
        if ($request->filled('data_al')) {
            $q->whereDate('data_prenotazione', '<=', $request->data_al);
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

        $this->verificaSogliaChiusuraPrenotazioni($prenotazione->sessione);

        $this->notifiche->invia($prenotazione, 'PRENOTAZIONE_APPROVATA');
        $this->log->log(
            $prenotazione->sessione->evento_id,
            'prenotazione.approvata',
            "Prenotazione {$prenotazione->codice} approvata ({$prenotazione->cognome} {$prenotazione->nome})."
        );

        return response()->json($prenotazione);
    }

    /**
     * GET /api/enti/{ente}/prenotazioni/export-xls
     * Esporta le prenotazioni filtrate in formato XLSX.
     * Se viene passato evento_id, include anche le colonne dei campi personalizzati.
     */
    public function exportXls(Request $request, Ente $ente): BinaryFileResponse
    {
        $q = Prenotazione::where('ente_id', $ente->id)
            ->with(['sessione.evento', 'posti.tipologiaPosto', 'risposteForm.campoForm'])
            ->orderByDesc('data_prenotazione');

        $eventoId = $request->filled('evento_id') ? (int) $request->evento_id : null;

        if ($eventoId) {
            $sessioniIds = Sessione::where('evento_id', $eventoId)->pluck('id');
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
        if ($request->filled('data_dal')) {
            $q->whereDate('data_prenotazione', '>=', $request->data_dal);
        }
        if ($request->filled('data_al')) {
            $q->whereDate('data_prenotazione', '<=', $request->data_al);
        }

        $prenotazioni = $q->get();

        // Campi personalizzati: solo se è selezionato un evento
        $campiForm = collect();
        if ($eventoId) {
            $campiForm = CampoForm::where('evento_id', $eventoId)
                ->orderBy('ordine')
                ->get();
        }

        // --- Costruzione foglio XLS ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Prenotazioni');

        $headers = [
            'Codice', 'Cognome', 'Nome', 'Email', 'Telefono',
            'Evento', 'Data sessione', 'Prenotato il',
            'Stato', 'N° posti', 'Tipologie posti', 'Importo €', 'Note',
        ];
        foreach ($campiForm as $campo) {
            $headers[] = $campo->etichetta;
        }

        // Riga intestazione — API PhpSpreadsheet 5.x: setCellValue([$col,$row], $val)
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValue([$col, 1], $h);
            $col++;
        }
        $lastCol = $col - 1;

        // Stile intestazione: sfondo blu, testo bianco, grassetto
        $lastColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);
        $sheet->getStyle("A1:{$lastColStr}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Righe dati
        $row = 2;
        foreach ($prenotazioni as $p) {
            $nPosti = $p->posti->sum('quantita');
            $col = 1;

            $sheet->setCellValue([$col++, $row], $p->codice);
            $sheet->setCellValue([$col++, $row], $p->cognome);
            $sheet->setCellValue([$col++, $row], $p->nome);
            $sheet->setCellValue([$col++, $row], $p->email);
            $sheet->setCellValue([$col++, $row], $p->telefono ?? '');
            $sheet->setCellValue([$col++, $row], $p->sessione?->evento?->titolo ?? '');
            $sheet->setCellValue([$col++, $row],
                $p->sessione?->data_inizio ? Carbon::parse($p->sessione->data_inizio)->format('d/m/Y H:i') : ''
            );
            $sheet->setCellValue([$col++, $row],
                $p->data_prenotazione ? Carbon::parse($p->data_prenotazione)->format('d/m/Y H:i') : ''
            );
            $sheet->setCellValue([$col++, $row], $p->stato);
            $sheet->setCellValue([$col++, $row], $nPosti);
            $tipologieStr = $p->posti
                ->filter(fn($pp) => $pp->quantita > 0)
                ->map(fn($pp) => ($pp->tipologiaPosto?->nome ?? '?') . '×' . $pp->quantita)
                ->join(', ');
            $sheet->setCellValue([$col++, $row], $tipologieStr);
            $sheet->setCellValue([$col++, $row], (float) ($p->costo_totale ?? 0));
            $sheet->setCellValue([$col++, $row], $p->note ?? '');

            foreach ($campiForm as $campo) {
                $risposta = $p->risposteForm->first(fn($r) => (int) $r->campo_form_id === (int) $campo->id);
                $sheet->setCellValue([$col++, $row], $risposta?->valore ?? '');
            }

            // Righe alternate
            if ($row % 2 === 0) {
                $rowRange = 'A' . $row . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $row;
                $sheet->getStyle($rowRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0F4FF');
            }

            $row++;
        }

        // Auto-larghezza colonne
        for ($c = 1; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c)
            )->setAutoSize(true);
        }

        // Salva in cartella temp
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $filename = 'prenotazioni_' . now()->format('Ymd_His') . '.xlsx';
        $filepath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        (new Xlsx($spreadsheet))->save($filepath);

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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

    /**
     * Chiude automaticamente la sessione (forza_non_disponibile = true) quando
     * il numero di prenotazioni attive raggiunge la soglia configurata.
     */
    private function verificaSogliaChiusuraPrenotazioni(Sessione $sessione): void
    {
        $soglia = $sessione->soglia_chiusura_prenotazioni;
        if ($soglia === null || $soglia <= 0) {
            return;
        }

        $attive = $sessione->prenotazioni()
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->count();

        if ($attive >= $soglia) {
            $sessione->update(['forza_non_disponibile' => true]);
        }
    }

    private function eseguiAnnullamento(Prenotazione $prenotazione, string $nuovoStato, ?string $motivo = null): JsonResponse
    {
        return DB::transaction(function () use ($prenotazione, $nuovoStato, $motivo) {
            $eraListaAttesa = in_array($prenotazione->stato, ['IN_LISTA_ATTESA', 'NOTIFICATO']);

            $prenotazione->update([
                'stato'                  => $nuovoStato,
                'data_annullamento'      => now(),
                'motivo_annullamento'    => $motivo,
                'posizione_lista_attesa' => null,
            ]);

            $sessione = Sessione::find($prenotazione->sessione_id);
            if ($sessione) {
                $totale = $prenotazione->posti()->sum('quantita');

                // Snapshot prima (per notifica)
                $liberiPrima = $sessione->posti_totali > 0
                    ? max(0, $sessione->posti_disponibili - $sessione->posti_riservati)
                    : null;

                if ($eraListaAttesa) {
                    // Le prenotazioni in lista attesa non occupano posti_disponibili:
                    // basta decrementare posti_in_attesa
                    $sessione->decrement('posti_in_attesa', min($totale, $sessione->posti_in_attesa ?? 0));
                } else {
                    // Prenotazione reale: restituisce i posti
                    $sessione->increment('posti_disponibili', $totale);

                    foreach ($prenotazione->posti as $posto) {
                        SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                            ->where('tipologia_posto_id', $posto->tipologia_posto_id)
                            ->increment('posti_disponibili', $posto->quantita);
                    }

                    // Notifica se si passa da 0 a > 0 posti liberi
                    if ($liberiPrima !== null && $liberiPrima <= 0) {
                        $sessione->refresh();
                        $liberiDopo = max(0, $sessione->posti_disponibili - $sessione->posti_riservati);
                        if ($liberiDopo > 0) {
                            broadcast(new PostiTornatiDisponibili($sessione));
                        }
                    }
                }
            }

        // Notifica utente annullamento
        $tipoNotifica = $nuovoStato === 'ANNULLATA_UTENTE' ? 'PRENOTAZIONE_ANNULLATA_UTENTE' : 'PRENOTAZIONE_ANNULLATA_OPERATORE';
        $this->notifiche->invia($prenotazione, $tipoNotifica);

        // Triggera promozione lista d'attesa solo se si è liberato un posto reale
        if ($sessione && !$eraListaAttesa) {
            $this->listaAttesa->processaPromozione($sessione);
        }

        $attore    = $nuovoStato === 'ANNULLATA_UTENTE' ? 'dall\'utente' : 'dall\'operatore';
        $motivoLog = $motivo ? " Motivo: {$motivo}." : '';
        $prefisso  = $eraListaAttesa ? 'Lista attesa rimossa' : 'Prenotazione annullata';
        $this->log->log(
            $prenotazione->sessione->evento_id,
            'prenotazione.annullata',
            "{$prefisso} {$prenotazione->codice} {$attore} ({$prenotazione->cognome} {$prenotazione->nome}).{$motivoLog}",
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

                // Snapshot disponibilità prima del rilascio (per decidere se notificare)
                $liberiPrima = $sessione->posti_totali > 0
                    ? max(0, $sessione->posti_disponibili - $sessione->posti_riservati)
                    : null; // illimitato → non notificare

                $sessione->decrement('posti_riservati', $totale);

                foreach ($posti as $posto) {
                    SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                        ->where('tipologia_posto_id', $posto['tipologia_id'])
                        ->decrement('posti_riservati', $posto['quantita']);
                }

                // Notifica solo se si passa da 0 posti liberi a > 0
                if ($liberiPrima !== null && $liberiPrima <= 0) {
                    $sessione->refresh();
                    $liberiDopo = max(0, $sessione->posti_disponibili - $sessione->posti_riservati);
                    if ($liberiDopo > 0) {
                        broadcast(new PostiTornatiDisponibili($sessione));
                    }
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
