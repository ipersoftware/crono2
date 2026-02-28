<?php

namespace App\Http\Middleware;

use App\Models\Ente;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica che l'utente autenticato abbia accesso all'Ente richiesto.
 *
 * L'Ente viene risolto dal parametro di route {ente} (id o shop_url).
 * L'Admin di sistema ha accesso a tutti gli Enti.
 */
class EnsureEnteAccess
{
    public function handle(Request $request, Closure $next, string $minRole = 'operatore_ente'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non autenticato.'], 401);
        }

        // Admin di sistema: accesso globale senza restrizioni
        if ($user->isAdmin()) {
            return $next($request);
        }

        $ente = $request->route('ente');

        if (!$ente instanceof Ente) {
            $ente = Ente::find($ente) ?? Ente::where('shop_url', $ente)->first();
        }

        if (!$ente) {
            return response()->json(['message' => 'Ente non trovato.'], 404);
        }

        // Verifica che l'utente appartenga all'ente
        if ((int) $user->ente_id !== (int) $ente->id) {
            return response()->json(['message' => 'Accesso non autorizzato a questo Ente.'], 403);
        }

        // Verifica il ruolo minimo richiesto
        $gerarchia = ['utente' => 0, 'operatore_ente' => 1, 'admin_ente' => 2, 'admin' => 3];
        $livelloUtente = $gerarchia[$user->role] ?? 0;
        $livelloMinimo = $gerarchia[$minRole] ?? 1;

        if ($livelloUtente < $livelloMinimo) {
            return response()->json(['message' => 'Permessi insufficienti.'], 403);
        }

        // Inietta l'Ente nella request per riuso nei controller
        $request->merge(['_ente' => $ente]);

        return $next($request);
    }
}
