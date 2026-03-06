<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\RichiestaContatto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RichiestaContattoController extends Controller
{
    /**
     * GET /api/enti/{ente}/richieste-contatto
     * Lista richieste ricevute (admin).
     */
    public function index(Ente $ente): JsonResponse
    {
        $richieste = $ente->richiesteContatto()
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($richieste);
    }

    /**
     * PATCH /api/enti/{ente}/richieste-contatto/{richiesta}/letta
     * Segna come letta/non letta.
     */
    public function segnaLetta(Ente $ente, RichiestaContatto $richiesta): JsonResponse
    {
        abort_if($richiesta->ente_id !== $ente->id, 403);

        $richiesta->update(['letta' => !$richiesta->letta]);

        return response()->json($richiesta);
    }

    /**
     * DELETE /api/enti/{ente}/richieste-contatto/{richiesta}
     */
    public function destroy(Ente $ente, RichiestaContatto $richiesta): JsonResponse
    {
        abort_if($richiesta->ente_id !== $ente->id, 403);

        $richiesta->delete();

        return response()->json(['message' => 'Richiesta eliminata.']);
    }
}
