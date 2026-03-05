<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\Sessione;
use App\Models\TipologiaPosto;
use App\Models\SessioneTipologiaPosto;
use App\Models\Prenotazione;
use App\Models\PrenotazionePosto;
use App\Services\EventoLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessioneController extends Controller
{
    public function __construct(protected EventoLogService $log) {}
    /** GET /api/enti/{ente}/eventi/{evento}/sessioni */
    public function index(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);

        $sessioni = $evento->sessioni()
            ->with(['luoghi', 'tipologiePosto.tipologiaPosto'])
            ->orderBy('data_inizio')
            ->get();

        return response()->json($sessioni);
    }

    /** POST /api/enti/{ente}/eventi/{evento}/sessioni */
    public function store(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);

        $data = $this->valida($request);
        $data['evento_id'] = $evento->id;

        // 0 = illimitato; null non ammesso dalla colonna
        $data['posti_totali'] = $data['posti_totali'] ?? 0;
        // DEFAULT 15; null non ammesso dalla colonna
        $data['durata_lock_minuti'] = $data['durata_lock_minuti'] ?? 15;

        // Inizializza posti_disponibili = posti_totali
        if (isset($data['posti_totali'])) {
            $data['posti_disponibili'] = $data['posti_totali'];
        }

        $sessione = Sessione::create($data);

        // Associa luoghi
        if ($request->has('luogo_ids')) {
            $sessione->luoghi()->sync($request->luogo_ids);
        }

        // Crea disponibilità per ogni tipologia dell'evento
        if (!$evento->tipologiePosto->isEmpty()) {
            $tipologiePosto = $request->tipologie_posto ?? [];

            foreach ($evento->tipologiePosto as $tipologia) {
                $config = collect($tipologiePosto)->firstWhere('tipologia_posto_id', $tipologia->id) ?? [];
                $postiTotali = $config['posti_totali'] ?? 0;

                SessioneTipologiaPosto::create([
                    'sessione_id'       => $sessione->id,
                    'tipologia_posto_id' => $tipologia->id,
                    'posti_totali'      => $postiTotali,
                    'posti_disponibili' => $postiTotali,
                    'posti_riservati'   => 0,
                    'attiva'            => $config['attiva'] ?? true,
                ]);
            }
        }

        $sessioneLabel = $sessione->data_inizio ?? 'nuova';
        $this->log->log($evento->id, 'sessione.creata', "Sessione aggiunta: {$sessioneLabel} — {$sessione->posti_totali} posti totali.");

        return response()->json($sessione->load(['luoghi', 'tipologiePosto']), 201);
    }

    /** GET /api/enti/{ente}/eventi/{evento}/sessioni/{sessione} */
    public function show(Ente $ente, Evento $evento, Sessione $sessione): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaSessione($evento, $sessione);

        return response()->json(
            $sessione->load(['luoghi', 'tipologiePosto.tipologiaPosto'])
        );
    }

    /** PUT /api/enti/{ente}/eventi/{evento}/sessioni/{sessione} */
    public function update(Request $request, Ente $ente, Evento $evento, Sessione $sessione): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaSessione($evento, $sessione);

        $beforeSessione = $sessione->only(['data_inizio', 'data_fine', 'posti_totali', 'posti_disponibili', 'prenotabile', 'forza_non_disponibile']);

        $data = $this->valida($request, partial: true);
        if (array_key_exists('posti_totali', $data)) {
            $data['posti_totali'] = $data['posti_totali'] ?? 0;
            // Conta le prenotazioni attive reali (fonte di verità)
            $postiConfermati = Prenotazione::where('sessione_id', $sessione->id)
                ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE', 'RISERVATA'])
                ->sum('posti_prenotati');
            // Blocca se il nuovo totale è inferiore alle prenotazioni già esistenti
            abort_if(
                $data['posti_totali'] > 0 && $data['posti_totali'] < $postiConfermati,
                422,
                "Impossibile impostare {$data['posti_totali']} posti totali: ci sono già {$postiConfermati} posti prenotati. Annullare prima le prenotazioni in eccesso."
            );
            $data['posti_disponibili'] = max(0, $data['posti_totali'] - $postiConfermati - $sessione->posti_riservati);
        }
        if (array_key_exists('durata_lock_minuti', $data)) {
            $data['durata_lock_minuti'] = $data['durata_lock_minuti'] ?? 15;
        }
        $sessione->update($data);

        if ($request->has('luogo_ids')) {
            $sessione->luoghi()->sync($request->luogo_ids);
        }

        // Aggiorna posti per tipologia
        if ($request->has('tipologie_posto')) {
            foreach ($request->tipologie_posto as $config) {
                $postiTotali  = $config['posti_totali'] ?? 0;
                $tipologiaId  = $config['tipologia_posto_id'];
                $riservati    = SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $tipologiaId)
                    ->value('posti_riservati') ?? 0;
                // Conta i posti realmente prenotati per questa tipologia
                $confermati = PrenotazionePosto::where('tipologia_posto_id', $tipologiaId)
                    ->whereHas('prenotazione', fn($q) => $q
                        ->where('sessione_id', $sessione->id)
                        ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE', 'RISERVATA'])
                    )
                    ->sum('quantita');
                // Blocca se il nuovo totale tipologia è inferiore ai già prenotati
                abort_if(
                    $postiTotali > 0 && $postiTotali < $confermati,
                    422,
                    "Impossibile impostare {$postiTotali} posti per la tipologia #{$tipologiaId}: ci sono già {$confermati} posti prenotati."
                );

                SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $tipologiaId)
                    ->update([
                        'posti_totali'      => $postiTotali,
                        'posti_disponibili' => max(0, $postiTotali - $confermati - $riservati),
                        'attiva'            => $config['attiva'] ?? true,
                    ]);
            }
        }

        $etichette = [
            'data_inizio' => 'Inizio', 'data_fine' => 'Fine',
            'posti_totali' => 'Posti totali', 'posti_disponibili' => 'Posti disponibili',
            'prenotabile' => 'Prenotabile', 'forza_non_disponibile' => 'Forzata non disponibile',
        ];
        $diff = $this->log->diff($beforeSessione, $sessione->fresh()->only(array_keys($beforeSessione)));
        if (!empty($diff)) {
            $this->log->log(
                $evento->id,
                'sessione.modificata',
                'Sessione del ' . $sessione->data_inizio . ' modificata: ' . $this->log->descriviDiff($diff, $etichette),
                $diff
            );
        }

        return response()->json($sessione->fresh(['luoghi', 'tipologiePosto.tipologiaPosto']));
    }

    /** DELETE /api/enti/{ente}/eventi/{evento}/sessioni/{sessione} */
    public function destroy(Ente $ente, Evento $evento, Sessione $sessione): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaSessione($evento, $sessione);

        abort_if(
            $sessione->prenotazioni()->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])->exists(),
            422,
            'Impossibile eliminare una sessione con prenotazioni attive.'
        );

        $sessioneLabel = $sessione->data_inizio;
        $sessione->delete();
        $this->log->log($evento->id, 'sessione.eliminata', "Sessione del {$sessioneLabel} eliminata.");

        return response()->json(['message' => 'Sessione eliminata.']);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function valida(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'titolo'                           => 'nullable|string|max:255',
            'descrizione'                      => 'nullable|string',
            'data_inizio'                      => "{$req}|date",
            'data_fine'                        => "{$req}|date|after:data_inizio",
            'posti_totali'                     => 'nullable|integer|min:0',
            'controlla_posti_globale'          => 'nullable|boolean',
            'prenotabile'                      => 'nullable|boolean',
            'forza_non_disponibile'            => 'nullable|boolean',
            'soglia_chiusura_automatica'       => 'nullable|integer|min:0',
            'soglia_overbooking_percentuale'   => 'nullable|integer|min:0',
            'soglia_overbooking_assoluta'      => 'nullable|integer|min:0',
            'attiva_lista_attesa'              => 'nullable|boolean',
            'lista_attesa_finestra_conferma_ore' => 'nullable|integer|min:1',
            'durata_lock_minuti'               => 'nullable|integer|min:1',
            'note_pubbliche'                   => 'nullable|string',
        ]);
    }

    private function autorizzaEvento(Ente $ente, Evento $evento): void
    {
        // Admin di sistema può accedere a qualsiasi evento
        if (request()->user()?->isAdmin()) {
            return;
        }
        abort_if((int) $evento->ente_id !== (int) $ente->id, 403, 'Evento non appartiene a questo Ente.');
    }

    private function autorizzaSessione(Evento $evento, Sessione $sessione): void
    {
        abort_if((int) $sessione->evento_id !== (int) $evento->id, 404, 'Sessione non trovata.');
    }
}
