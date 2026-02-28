<?php

namespace App\Http\Controllers;

use App\Models\CampoForm;
use App\Models\Ente;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampoFormController extends Controller
{
    /** GET /api/enti/{ente}/eventi/{evento}/campi-form */
    public function index(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);

        return response()->json(
            $evento->campiForm()->where('attivo', true)->get()
        );
    }

    /** POST /api/enti/{ente}/eventi/{evento}/campi-form */
    public function store(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);

        $data = $request->validate([
            'ordine'          => 'nullable|integer|min:0',
            'tipo'            => 'required|in:TEXT,TEXTAREA,SELECT,CHECKBOX,RADIO,DATE,EMAIL,PHONE,NUMBER',
            'etichetta'       => 'required|string|max:255',
            'placeholder'     => 'nullable|string|max:255',
            'obbligatorio'    => 'nullable|boolean',
            'opzioni'         => 'nullable|array',
            'opzioni.*'       => 'string',
            'validazione'     => 'nullable|array',
            'visibile_pubblico' => 'nullable|boolean',
        ]);

        $data['evento_id'] = $evento->id;

        // Auto-ordine alla fine
        if (!isset($data['ordine'])) {
            $data['ordine'] = ($evento->campiForm()->max('ordine') ?? -1) + 1;
        }

        $campo = CampoForm::create($data);

        return response()->json($campo, 201);
    }

    /** PUT /api/enti/{ente}/eventi/{evento}/campi-form/{campo} */
    public function update(Request $request, Ente $ente, Evento $evento, CampoForm $campo): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaCampo($evento, $campo);

        $data = $request->validate([
            'ordine'          => 'nullable|integer|min:0',
            'tipo'            => 'nullable|in:TEXT,TEXTAREA,SELECT,CHECKBOX,RADIO,DATE,EMAIL,PHONE,NUMBER',
            'etichetta'       => 'nullable|string|max:255',
            'placeholder'     => 'nullable|string|max:255',
            'obbligatorio'    => 'nullable|boolean',
            'opzioni'         => 'nullable|array',
            'validazione'     => 'nullable|array',
            'visibile_pubblico' => 'nullable|boolean',
            'attivo'          => 'nullable|boolean',
        ]);

        $campo->update($data);

        return response()->json($campo);
    }

    /** DELETE /api/enti/{ente}/eventi/{evento}/campi-form/{campo} */
    public function destroy(Ente $ente, Evento $evento, CampoForm $campo): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);
        $this->autorizzaCampo($evento, $campo);

        $campo->delete();

        return response()->json(['message' => 'Campo eliminato.']);
    }

    /** POST /api/enti/{ente}/eventi/{evento}/campi-form/riordina */
    public function riordina(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizzaEvento($ente, $evento);

        $data = $request->validate([
            'ordine'    => 'required|array',
            'ordine.*'  => 'integer|exists:campi_form,id',
        ]);

        foreach ($data['ordine'] as $pos => $id) {
            CampoForm::where('id', $id)->where('evento_id', $evento->id)
                ->update(['ordine' => $pos]);
        }

        return response()->json(['message' => 'Ordine aggiornato.']);
    }

    private function autorizzaEvento(Ente $ente, Evento $evento): void
    {
        abort_if((int) $evento->ente_id !== (int) $ente->id, 403, 'Evento non appartiene a questo Ente.');
    }

    private function autorizzaCampo(Evento $evento, CampoForm $campo): void
    {
        abort_if((int) $campo->evento_id !== (int) $evento->id, 404, 'Campo non trovato.');
    }
}
