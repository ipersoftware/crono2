<?php

namespace App\Http\Controllers;

use App\Models\NotificaLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificaLogController extends Controller
{
    public function index(Request $request, $ente): JsonResponse
    {
        $q = NotificaLog::with(['prenotazione:id,codice'])
            ->where('ente_id', $ente)
            ->orderByDesc('created_at');

        if ($request->filled('dal')) {
            $q->where('created_at', '>=', $request->dal . ' 00:00:00');
        }
        if ($request->filled('al')) {
            $q->where('created_at', '<=', $request->al . ' 23:59:59');
        }
        if ($request->filled('tipo')) {
            $q->where('tipo', $request->tipo);
        }
        if ($request->filled('stato')) {
            $q->where('stato', $request->stato);
        }
        if ($request->filled('prenotazione_id')) {
            $q->where('prenotazione_id', $request->prenotazione_id);
        }

        $result = $q->paginate($request->integer('per_page', 50));

        return response()->json($result);
    }
}
