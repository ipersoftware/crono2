<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Models\EventoLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventoLogController extends Controller
{
    /** GET /api/enti/{ente}/eventi/{evento}/log */
    public function index(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        if (!request()->user()?->isAdmin()) {
            abort_if((int) $evento->ente_id !== (int) $ente->id, 403, 'Non autorizzato.');
        }

        $log = EventoLog::where('evento_id', $evento->id)
            ->with('user:id,nome,cognome,email')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 50);

        return response()->json($log);
    }
}
