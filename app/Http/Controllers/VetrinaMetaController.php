<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;

class VetrinaMetaController extends Controller
{
    /**
     * Pagina evento: inietta meta OG e JSON-LD server-side prima che la SPA si monti.
     * Questo fa sì che i social crawler (Facebook, WhatsApp, LinkedIn) vedano
     * titolo, descrizione e immagine anche senza eseguire JavaScript.
     */
    public function evento(string $shopUrl, string $slug)
    {
        $ente = Ente::where('shop_url', $shopUrl)->first();
        if (! $ente) {
            return view('app');
        }

        $evento = Evento::where('ente_id', $ente->id)
            ->where('slug', $slug)
            ->where('pubblico', true)
            ->with(['luoghi', 'sessioni' => fn($q) => $q->orderBy('data_inizio')])
            ->first();

        if (! $evento) {
            return view('app');
        }

        $url   = url("/vetrina/{$shopUrl}/eventi/{$slug}");
        $desc  = $evento->descrizione_breve ?: $evento->titolo;
        $image = $evento->immagine ? asset($evento->immagine) : null;

        // Schema.org Event JSON-LD
        $jsonLd = [
            '@context'            => 'https://schema.org',
            '@type'               => $evento->sessioni->count() > 1 ? 'EventSeries' : 'Event',
            'name'                => $evento->titolo,
            'description'         => strip_tags($desc),
            'url'                 => $url,
            'organizer'           => ['@type' => 'Organization', 'name' => $ente->nome],
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'offers'              => ['@type' => 'Offer', 'url' => $url, 'availability' => 'https://schema.org/InStock'],
        ];

        if ($image) {
            $jsonLd['image'] = $image;
        }

        if ($evento->luoghi->isNotEmpty()) {
            $luogo = $evento->luoghi->first();
            $jsonLd['location'] = [
                '@type'   => 'Place',
                'name'    => $luogo->nome,
                'address' => $luogo->indirizzo ?? $luogo->nome,
            ];
        }

        if ($evento->sessioni->isNotEmpty()) {
            $jsonLd['startDate'] = $evento->sessioni->first()->data_inizio;
            $jsonLd['endDate']   = $evento->sessioni->last()->data_fine
                                ?? $evento->sessioni->last()->data_inizio;
        }

        $meta = [
            'title'     => "{$evento->titolo} — {$ente->nome}",
            'description' => strip_tags($desc),
            'image'     => $image,
            'url'       => $url,
            'site_name' => $ente->nome,
            'type'      => 'event',
            'json_ld'   => json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return view('app', compact('meta'));
    }

    /**
     * Home vetrina ente: meta base per la condivisione della pagina eventi dell'ente.
     */
    public function home(string $shopUrl)
    {
        $ente = Ente::where('shop_url', $shopUrl)->first();
        if (! $ente) {
            return view('app');
        }

        $url = url("/vetrina/{$shopUrl}");

        $meta = [
            'title'       => "Eventi — {$ente->nome}",
            'description' => "Scopri gli eventi di {$ente->nome} e prenota il tuo posto.",
            'image'       => $ente->copertina ? asset($ente->copertina) : null,
            'url'         => $url,
            'site_name'   => $ente->nome,
            'type'        => 'website',
        ];

        return view('app', compact('meta'));
    }
}
