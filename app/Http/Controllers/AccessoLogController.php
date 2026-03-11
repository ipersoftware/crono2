<?php

namespace App\Http\Controllers;

use App\Models\AccessoLog;
use App\Models\Ente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessoLogController extends Controller
{
    /**
     * GET /api/enti/{ente}/accessi-log
     * Richiede ruolo minimo admin_ente.
     */
    public function index(Request $request, Ente $ente): JsonResponse
    {
        $q = AccessoLog::where('ente_id', $ente->id)
            ->with(['user:id,nome,cognome,email'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->user_id);
        }

        if ($request->filled('esito')) {
            $q->where('esito', $request->esito);
        }

        if ($request->filled('dal')) {
            $q->whereDate('created_at', '>=', $request->dal);
        }

        if ($request->filled('al')) {
            $q->whereDate('created_at', '<=', $request->al);
        }

        $log = $q->paginate($request->input('per_page', 50));

        return response()->json($log);
    }
}
