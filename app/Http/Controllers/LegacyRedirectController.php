<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gestisce i redirect dai vecchi URL crono1 verso crono2.
 *
 * Pattern supportati:
 *   /search?tag={slug}                          → /vetrina/{shop_url}?tag_slug={slug}
 *   /search?metaclass=Evento&class=Evento       → /vetrina/{shop_url}
 *   /search (qualsiasi altra combinazione)      → /
 */
class LegacyRedirectController extends Controller
{
    public function search(Request $request): RedirectResponse
    {
        $tagSlug = $request->query('tag');

        if ($tagSlug) {
            // Cerca il tag per slug nel DB (scoped all'ente tramite la relazione)
            $tag = Tag::with('ente')->where('slug', $tagSlug)->first();

            if ($tag && $tag->ente?->shop_url) {
                $shopUrl = $tag->ente->shop_url;
                return redirect(
                    url("/vetrina/{$shopUrl}") . '?tag_slug=' . urlencode($tagSlug),
                    301
                );
            }

            // Tag non trovato per slug esatto: prova a cercare per nome normalizzato
            $tag = Tag::with('ente')
                ->whereRaw('LOWER(REPLACE(nome, " ", "-")) = ?', [strtolower($tagSlug)])
                ->first();

            if ($tag && $tag->ente?->shop_url) {
                $shopUrl = $tag->ente->shop_url;
                return redirect(
                    url("/vetrina/{$shopUrl}") . '?tag_slug=' . urlencode($tag->slug),
                    301
                );
            }
        }

        // Fallback: se c'è un solo ente manda alla sua vetrina, altrimenti alla landing
        $ente = Ente::whereNotNull('shop_url')->first();
        if ($ente) {
            return redirect(url("/vetrina/{$ente->shop_url}"), 301);
        }

        return redirect(url('/'), 301);
    }
}
