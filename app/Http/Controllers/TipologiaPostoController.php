<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\TipologiaPosto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipologiaPostoController extends Controller
{
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

        $tipologia->update($data);

        return response()->json($tipologia);
    }

    /** DELETE /api/enti/{ente}/eventi/{evento}/tipologie/{tipologia} */
    public function destroy(Ente $ente, Evento $evento, TipologiaPosto $tipologia): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaTipologia($evento, $tipologia);

        $tipologia->delete();

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
