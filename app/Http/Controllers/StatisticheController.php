<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticheController extends Controller
{
    /**
     * KPI overview: 5 card sintetiche sul periodo selezionato.
     * GET /api/enti/{ente}/statistiche/kpi
     */
    public function kpi(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al, $eventoId] = $this->filtri($request);

        $base = DB::table('prenotazioni')
            ->where('ente_id', $ente->id)
            ->whereBetween('data_prenotazione', [$dal, $al])
            ->when($eventoId, fn($q) => $q->whereIn('sessione_id',
                DB::table('sessioni')->where('evento_id', $eventoId)->pluck('id')
            ));

        $totale      = (clone $base)->count();
        $confermate  = (clone $base)->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])->count();
        $annullate   = (clone $base)->where('stato', 'like', 'ANNULLATA%')->count();
        $attesa      = (clone $base)->whereIn('stato', ['IN_LISTA_ATTESA', 'NOTIFICATO'])->count();

        $postiPrenotati = (clone $base)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->sum('posti_prenotati');

        $ricavi = (clone $base)
            ->whereIn('stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->where('costo_totale', '>', 0)
            ->sum('costo_totale');

        $tassoAnnullamento = $totale > 0
            ? round($annullate / $totale * 100, 1)
            : 0;

        return response()->json([
            'confermate'         => $confermate,
            'posti_prenotati'    => (int) $postiPrenotati,
            'tasso_annullamento' => $tassoAnnullamento,
            'ricavi'             => round((float) $ricavi, 2),
            'lista_attesa'       => $attesa,
        ]);
    }

    /**
     * Andamento mensile prenotazioni (line chart).
     * GET /api/enti/{ente}/statistiche/andamento
     */
    public function andamento(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al, $eventoId] = $this->filtri($request);

        $rows = DB::table('prenotazioni')
            ->selectRaw("DATE_FORMAT(data_prenotazione, '%Y-%m') AS mese")
            ->selectRaw("SUM(CASE WHEN stato IN ('CONFERMATA','DA_CONFERMARE') THEN 1 ELSE 0 END) AS confermate")
            ->selectRaw("SUM(CASE WHEN stato LIKE 'ANNULLATA%' THEN 1 ELSE 0 END) AS annullate")
            ->where('ente_id', $ente->id)
            ->whereBetween('data_prenotazione', [$dal, $al])
            ->when($eventoId, fn($q) => $q->whereIn('sessione_id',
                DB::table('sessioni')->where('evento_id', $eventoId)->pluck('id')
            ))
            ->groupBy('mese')
            ->orderBy('mese')
            ->get();

        return response()->json($rows);
    }

    /**
     * Distribuzione per stato (donut chart).
     * GET /api/enti/{ente}/statistiche/stati
     */
    public function stati(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al, $eventoId] = $this->filtri($request);

        $rows = DB::table('prenotazioni')
            ->selectRaw('stato, COUNT(*) AS n')
            ->where('ente_id', $ente->id)
            ->whereBetween('data_prenotazione', [$dal, $al])
            ->when($eventoId, fn($q) => $q->whereIn('sessione_id',
                DB::table('sessioni')->where('evento_id', $eventoId)->pluck('id')
            ))
            ->groupBy('stato')
            ->orderByDesc('n')
            ->get();

        return response()->json($rows);
    }

    /**
     * Top 10 eventi per prenotazioni (bar chart orizzontale).
     * GET /api/enti/{ente}/statistiche/top-eventi
     */
    public function topEventi(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al] = $this->filtri($request);

        $rows = DB::table('prenotazioni as p')
            ->join('sessioni as s', 'p.sessione_id', '=', 's.id')
            ->join('eventi as e', 's.evento_id', '=', 'e.id')
            ->selectRaw('e.id, e.titolo')
            ->selectRaw('COUNT(p.id) AS n_prenotazioni')
            ->selectRaw('SUM(p.posti_prenotati) AS posti_prenotati')
            ->selectRaw('ROUND(SUM(p.posti_prenotati) / NULLIF(SUM(s.posti_totali), 0) * 100, 1) AS tasso_occupazione')
            ->where('p.ente_id', $ente->id)
            ->whereIn('p.stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->whereBetween('p.data_prenotazione', [$dal, $al])
            ->groupBy('e.id', 'e.titolo')
            ->orderByDesc('n_prenotazioni')
            ->limit(10)
            ->get();

        return response()->json($rows);
    }

    /**
     * Tasso di occupazione sessioni per evento (bar chart).
     * GET /api/enti/{ente}/statistiche/occupazione
     */
    public function occupazione(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al, $eventoId] = $this->filtri($request);

        $rows = DB::table('prenotazioni as p')
            ->join('sessioni as s', 'p.sessione_id', '=', 's.id')
            ->join('eventi as e', 's.evento_id', '=', 'e.id')
            ->selectRaw('e.id, e.titolo')
            ->selectRaw('SUM(p.posti_prenotati) AS posti_prenotati')
            ->selectRaw('SUM(s.posti_totali) AS posti_totali')
            ->selectRaw('ROUND(SUM(p.posti_prenotati) / NULLIF(SUM(s.posti_totali), 0) * 100, 1) AS tasso')
            ->where('p.ente_id', $ente->id)
            ->whereIn('p.stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->whereBetween('p.data_prenotazione', [$dal, $al])
            ->when($eventoId, fn($q) => $q->where('e.id', $eventoId))
            ->groupBy('e.id', 'e.titolo')
            ->orderByDesc('tasso')
            ->get();

        return response()->json($rows);
    }

    /**
     * Prenotazioni per giorno della settimana (bar chart).
     * GET /api/enti/{ente}/statistiche/giorni-settimana
     */
    public function giorniSettimana(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al, $eventoId] = $this->filtri($request);

        $rows = DB::table('prenotazioni')
            ->selectRaw('DAYOFWEEK(data_prenotazione) AS giorno, COUNT(*) AS n')
            ->where('ente_id', $ente->id)
            ->whereBetween('data_prenotazione', [$dal, $al])
            ->when($eventoId, fn($q) => $q->whereIn('sessione_id',
                DB::table('sessioni')->where('evento_id', $eventoId)->pluck('id')
            ))
            ->groupBy('giorno')
            ->orderBy('giorno')
            ->get()
            ->keyBy('giorno');

        // MySQL DAYOFWEEK: 1=Dom, 2=Lun, ..., 7=Sab — rimappiamo in Lun=0..Dom=6
        $nomi  = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
        $mysqlOrder = [2, 3, 4, 5, 6, 7, 1]; // Lun→Dom
        $result = [];
        foreach ($mysqlOrder as $i => $mysqlDay) {
            $result[] = [
                'giorno' => $nomi[$i],
                'n'      => $rows[$mysqlDay]->n ?? 0,
            ];
        }

        return response()->json($result);
    }

    /**
     * Prenotazioni per fascia oraria (bar chart).
     * GET /api/enti/{ente}/statistiche/fasce-orarie
     */
    public function fasceOrarie(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al, $eventoId] = $this->filtri($request);

        $rows = DB::table('prenotazioni')
            ->selectRaw('HOUR(data_prenotazione) AS ora, COUNT(*) AS n')
            ->where('ente_id', $ente->id)
            ->whereBetween('data_prenotazione', [$dal, $al])
            ->when($eventoId, fn($q) => $q->whereIn('sessione_id',
                DB::table('sessioni')->where('evento_id', $eventoId)->pluck('id')
            ))
            ->groupBy('ora')
            ->orderBy('ora')
            ->get()
            ->keyBy('ora');

        // Riempie le ore mancanti con 0
        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $result[] = ['ora' => sprintf('%02d:00', $h), 'n' => $rows[$h]->n ?? 0];
        }

        return response()->json($result);
    }

    /**
     * Lista d'attesa — dettaglio per evento (tabella §4.8).
     * GET /api/enti/{ente}/statistiche/lista-attesa
     */
    public function listaAttesa(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al] = $this->filtri($request);

        $inAttesa = DB::table('prenotazioni as p')
            ->join('sessioni as s', 'p.sessione_id', '=', 's.id')
            ->join('eventi as e', 's.evento_id', '=', 'e.id')
            ->selectRaw('e.id, e.titolo')
            ->selectRaw("SUM(CASE WHEN p.stato IN ('IN_LISTA_ATTESA','NOTIFICATO') THEN 1 ELSE 0 END) AS in_attesa")
            ->selectRaw("SUM(CASE WHEN p.stato = 'NOTIFICATO' THEN 1 ELSE 0 END) AS notificati")
            ->selectRaw("SUM(CASE WHEN p.stato = 'CONFERMATA' THEN 1 ELSE 0 END) AS convertiti")
            ->where('p.ente_id', $ente->id)
            ->whereBetween('p.data_prenotazione', [$dal, $al])
            ->groupBy('e.id', 'e.titolo')
            ->havingRaw("in_attesa > 0")
            ->orderByDesc('in_attesa')
            ->get()
            ->map(function ($row) {
                $totale = $row->in_attesa + $row->convertiti;
                $row->tasso_conversione = $totale > 0
                    ? round($row->convertiti / $totale * 100, 1)
                    : 0;
                return $row;
            });

        return response()->json($inAttesa);
    }

    /**
     * Distribuzione tipologie posto (pie chart §4.9).
     * GET /api/enti/{ente}/statistiche/tipologie-posto
     */
    public function tipologiePosto(Request $request, Ente $ente): JsonResponse
    {
        [$dal, $al, $eventoId] = $this->filtri($request);

        $rows = DB::table('prenotazione_posti as pp')
            ->join('prenotazioni as p',   'pp.prenotazione_id', '=', 'p.id')
            ->join('tipologie_posto as t', 'pp.tipologia_posto_id', '=', 't.id')
            ->leftJoin('sessioni as s', 'p.sessione_id', '=', 's.id')
            ->selectRaw('t.id, t.nome')
            ->selectRaw('SUM(pp.quantita) AS quantita')
            ->selectRaw('ROUND(SUM(pp.quantita * t.costo), 2) AS ricavo')
            ->where('p.ente_id', $ente->id)
            ->whereIn('p.stato', ['CONFERMATA', 'DA_CONFERMARE'])
            ->whereBetween('p.data_prenotazione', [$dal, $al])
            ->when($eventoId, fn($q) => $q->where('s.evento_id', $eventoId))
            ->groupBy('t.id', 't.nome')
            ->orderByDesc('quantita')
            ->get();

        return response()->json($rows);
    }

    // ─── Helper ─────────────────────────────────────────────────────────────

    private function filtri(Request $request): array
    {
        $dal      = $request->query('dal', now()->startOfYear()->toDateString());
        $al       = $request->query('al',  now()->toDateString());
        $eventoId = $request->query('evento_id');

        // Sicurezza: al deve essere >= dal
        if ($al < $dal) $al = $dal;

        // Il range "al" deve includere tutta la giornata
        $alFine = $al . ' 23:59:59';

        return [$dal, $alFine, $eventoId ?: null];
    }
}
