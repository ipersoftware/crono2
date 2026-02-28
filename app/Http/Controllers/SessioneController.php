<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\Sessione;
use App\Models\TipologiaPosto;
use App\Models\SessioneTipologiaPosto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessioneController extends Controller
{
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

        $data = $this->valida($request, partial: true);
        $sessione->update($data);

        if ($request->has('luogo_ids')) {
            $sessione->luoghi()->sync($request->luogo_ids);
        }

        return response()->json($sessione->fresh(['luoghi', 'tipologiePosto']));
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

        $sessione->delete();

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
        abort_if((int) $evento->ente_id !== (int) $ente->id, 403, 'Evento non appartiene a questo Ente.');
    }

    private function autorizzaSessione(Evento $evento, Sessione $sessione): void
    {
        abort_if((int) $sessione->evento_id !== (int) $evento->id, 404, 'Sessione non trovata.');
    }
}
