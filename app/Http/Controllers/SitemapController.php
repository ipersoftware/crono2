<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $eventi = Evento::where('pubblico', true)
            ->whereNull('deleted_at')
            ->with('ente:id,nome,shop_url')
            ->orderByDesc('updated_at')
            ->get();

        $enti = Ente::where('attivo', true)
            ->whereNull('deleted_at')
            ->get(['id', 'shop_url', 'updated_at']);

        $xml = view('sitemap', compact('eventi', 'enti'))->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
