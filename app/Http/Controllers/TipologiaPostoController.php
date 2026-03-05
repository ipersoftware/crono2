<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\TipologiaPosto;
use App\Services\EventoLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipologiaPostoController extends Controller
{
    public function __construct(protected EventoLogService $log) {}
    /** GET /api/enti/{ente}/eventi/{evento}/tipologie */
    public function index(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);

        return response()->json(
            $evento->tipologiePosto()->orderBy('ordinamento')->get()
        );
    }

    /** POST /api/enti/{ente}/eventi/{evento}/tipologie */
    public function store(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);

        $data = $request->validate([
            'nome'            => 'required|string|max:255',
            'descrizione'     => 'nullable|string',
            'gratuita'        => 'required|boolean',
            'costo'           => 'nullable|numeric|min:0',
            'min_prenotabili' => 'nullable|integer|min:1',
            'max_prenotabili' => 'nullable|integer|min:1',
            'ordinamento'     => 'nullable|integer|min:0',
            'attiva'          => 'nullable|boolean',
        ]);

        $data['evento_id'] = $evento->id;
        $data['ente_id']   = $ente->id;

        if ($data['gratuita']) {
            $data['costo'] = null;
        }

        $tipologia = TipologiaPosto::create($data);

        $costo = $tipologia->gratuita ? 'gratuita' : '\u20ac ' . number_format((float) $tipologia->costo, 2);
        $this->log->log($evento->id, 'tipologia.creata', "Tipologia posto aggiunta: \u00ab{$tipologia->nome}\u00bb ({$costo})");

        return response()->json($tipologia, 201);
    }

    /** PUT /api/enti/{ente}/eventi/{evento}/tipologie/{tipologia} */
    public function update(Request $request, Ente $ente, Evento $evento, TipologiaPosto $tipologia): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaTipologia($evento, $tipologia);

        $data = $request->validate([
            'nome'            => 'sometimes|string|max:255',
            'descrizione'     => 'nullable|string',
            'gratuita'        => 'nullable|boolean',
            'costo'           => 'nullable|numeric|min:0',
            'min_prenotabili' => 'nullable|integer|min:1',
            'max_prenotabili' => 'nullable|integer|min:1',
            'ordinamento'     => 'nullable|integer|min:0',
            'attiva'          => 'nullable|boolean',
        ]);

        if (isset($data['gratuita']) && $data['gratuita']) {
            $data['costo'] = null;
        }

        $etichette = [
            'nome' => 'Nome', 'costo' => 'Costo', 'gratuita' => 'Gratuita',
            'min_prenotabili' => 'Min prenotabili', 'max_prenotabili' => 'Max prenotabili',
            'attiva' => 'Attiva', 'descrizione' => 'Descrizione',
        ];
        $before = $tipologia->only(array_keys($etichette));
        $tipologia->update($data);
        $diff = $this->log->diff($before, $tipologia->fresh()->only(array_keys($etichette)));
        if (!empty($diff)) {
            $this->log->log($evento->id, 'tipologia.modificata',
                "Tipologia posto \u00ab{$tipologia->nome}\u00bb modificata: " . $this->log->descriviDiff($diff, $etichette),
                $diff
            );
        }

        return response()->json($tipologia);
    }

    /** DELETE /api/enti/{ente}/eventi/{evento}/tipologie/{tipologia} */
    public function destroy(Ente $ente, Evento $evento, TipologiaPosto $tipologia): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaTipologia($evento, $tipologia);

        $nome = $tipologia->nome;
        $tipologia->delete();

        $this->log->log($evento->id, 'tipologia.eliminata', "Tipologia posto rimossa: \u00ab{$nome}\u00bb");

        return response()->json(['message' => 'Tipologia eliminata.']);
    }

    private function autorizzaEvento(Ente $ente, Evento $evento): void
    {
        abort_if((int) $evento->ente_id !== (int) $ente->id, 403, 'Evento non appartiene a questo Ente.');
    }

    private function autorizzaTipologia(Evento $evento, TipologiaPosto $tipologia): void
    {
        abort_if((int) $tipologia->evento_id !== (int) $evento->id, 404, 'Tipologia non trovata.');
    }
}
