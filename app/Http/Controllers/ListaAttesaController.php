<?php

namespace App\Http\Controllers;

use App\Models\Prenotazione;
use App\Models\PrenotazionePosto;
use App\Models\Sessione;
use App\Models\SessioneTipologiaPosto;
use App\Services\EventoLogService;
use App\Services\ListaAttesaService;
use App\Services\NotificaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListaAttesaController extends Controller
{
    public function __construct(
        private readonly NotificaService   $notifiche,
        private readonly ListaAttesaService $listaAttesaService,
        private readonly EventoLogService   $log,
    ) {}

    /**
     * POST /api/prenotazioni/lista-attesa
     * Iscrive un utente alla lista d'attesa di una sessione esaurita.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sessione_id'          => 'required|integer|exists:sessioni,id',
            'nome'                 => 'required|string|max:255',
            'cognome'              => 'required|string|max:255',
            'email'                => 'required|email|max:255',
            'telefono'             => 'nullable|string|max:50',
            'posti'                => 'required|array|min:1',
            'posti.*.tipologia_id' => 'required|integer|exists:tipologie_posto,id',
            'posti.*.quantita'     => 'required|integer|min:1',
            'privacy_ok'           => 'required|boolean|accepted',
        ]);

        $sessione = Sessione::with(['evento', 'tipologiePosto'])->findOrFail($data['sessione_id']);
        $evento   = $sessione->evento;

        abort_if($evento->stato !== 'PUBBLICATO', 422, 'Evento non disponibile.');
        abort_if(!$sessione->prenotabile, 422, 'Sessione non aperta alle prenotazioni.');
        abort_if($sessione->forza_non_disponibile, 422, 'Sessione non disponibile.');
        abort_if(!$sessione->attiva_lista_attesa, 422, 'Lista d\'attesa non attiva per questa sessione.');

        // La sessione deve essere effettivamente esaurita
        if ($sessione->posti_totali > 0) {
            $postiLiberi = max(0, $sessione->posti_disponibili - $sessione->posti_riservati);
            abort_if($postiLiberi > 0, 422, 'La sessione ha ancora posti disponibili — procedi con la prenotazione normale.');
        }

        // Verifica duplicato iscrizione attiva
        $duplicato = Prenotazione::where('sessione_id', $sessione->id)
            ->where('email', $data['email'])
            ->whereIn('stato', ['IN_LISTA_ATTESA', 'NOTIFICATO'])
            ->exists();
        abort_if($duplicato, 422, 'Risulti già iscritto alla lista d\'attesa di questa sessione.');

        $totaleQuantita = collect($data['posti'])->sum('quantita');

        // Posizione = successiva disponibile
        $posizione = (Prenotazione::where('sessione_id', $sessione->id)
            ->whereIn('stato', ['IN_LISTA_ATTESA', 'NOTIFICATO'])
            ->max('posizione_lista_attesa') ?? 0) + 1;

        $evento = $sessione->evento;
        $codice = $this->generaCodice();

        $prenotazione = DB::transaction(function () use ($data, $sessione, $evento, $codice, $posizione, $totaleQuantita, $request) {
            $costoTotale = collect($data['posti'])->sum(function ($p) use ($sessione) {
                $stp = SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $p['tipologia_id'])
                    ->first();
                $costo = $stp?->tipologiaPosto?->costo ?? 0;
                return $costo * $p['quantita'];
            });

            $prenotazione = Prenotazione::create([
                'ente_id'                => $evento->ente_id,
                'sessione_id'            => $sessione->id,
                'user_id'                => $request->user()?->id,
                'codice'                 => $codice,
                'token_accesso'          => Str::random(48),
                'nome'                   => $data['nome'],
                'cognome'                => $data['cognome'],
                'email'                  => $data['email'],
                'telefono'               => $data['telefono'] ?? null,
                'privacy_ok'             => true,
                'stato'                  => 'IN_LISTA_ATTESA',
                'posti_prenotati'        => $totaleQuantita,
                'posizione_lista_attesa' => $posizione,
                'costo_totale'           => $costoTotale,
                'data_prenotazione'      => now(),
            ]);

            foreach ($data['posti'] as $p) {
                $stp = SessioneTipologiaPosto::where('sessione_id', $sessione->id)
                    ->where('tipologia_posto_id', $p['tipologia_id'])
                    ->first();
                $costoUnitario = $stp?->tipologiaPosto?->costo ?? 0;

                PrenotazionePosto::create([
                    'prenotazione_id'    => $prenotazione->id,
                    'sessione_id'        => $sessione->id,
                    'tipologia_posto_id' => $p['tipologia_id'],
                    'quantita'           => $p['quantita'],
                    'costo_unitario'     => $costoUnitario,
                    'costo_totale'       => $costoUnitario * $p['quantita'],
                ]);
            }

            return $prenotazione;
        });

        // Aggiorna contatore posti_in_attesa sulla sessione
        $sessione->increment('posti_in_attesa', $totaleQuantita);

        // Notifica iscrizione
        $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi']);
        $this->notifiche->inviaAListaAttesa($prenotazione, 'LISTA_ATTESA_ISCRIZIONE');

        $this->log->log(
            $evento->id,
            'lista_attesa.iscrizione',
            "Lista attesa: {$prenotazione->cognome} {$prenotazione->nome} ({$prenotazione->email}) iscritto in posizione {$posizione} (cod. {$prenotazione->codice})."
        );

        return response()->json([
            'message'   => 'Iscrizione alla lista d\'attesa registrata.',
            'posizione' => $posizione,
            'codice'    => $prenotazione->codice,
            'email'     => $prenotazione->email,
        ], 201);
    }

    /**
     * POST /api/lista-attesa/{token}/conferma
     * Conferma la disponibilità del posto da parte dell'utente notificato.
     * Usato quando tipo_conferma = PRENOTAZIONE_DA_CONFERMARE.
     */
    public function conferma(string $token): JsonResponse
    {
        $prenotazione = Prenotazione::where('token_accesso', $token)
            ->where('stato', 'NOTIFICATO')
            ->firstOrFail();

        abort_if(
            $prenotazione->scadenza_riserva && $prenotazione->scadenza_riserva->isPast(),
            422,
            'Il link di conferma è scaduto.'
        );

        $sessione = Sessione::with('evento')->find($prenotazione->sessione_id);

        // Verifica disponibilità effettiva dei posti
        if ($sessione->posti_totali > 0) {
            $postiLiberi = max(0, $sessione->posti_disponibili - ($sessione->posti_riservati ?? 0));
            abort_if(
                $postiLiberi < $prenotazione->posti_prenotati,
                422,
                'I posti non sono più disponibili. La tua posizione in lista viene mantenuta.'
            );
        }

        return DB::transaction(function () use ($prenotazione, $sessione) {
            $sess = Sessione::lockForUpdate()->find($sessione->id);

            // Double-check dentro la transazione
            if ($sess->posti_totali > 0) {
                $postiLiberi = max(0, $sess->posti_disponibili - ($sess->posti_riservati ?? 0));
                abort_if($postiLiberi < $prenotazione->posti_prenotati, 422, 'I posti non sono più disponibili.');
            }

            $totale = $prenotazione->posti_prenotati;

            // Promuovi la prenotazione a CONFERMATA
            $prenotazione->update([
                'stato'                  => 'CONFERMATA',
                'posizione_lista_attesa' => null,
                'data_prenotazione'      => now(),
            ]);

            // Aggiorna contatori sessione
            $sess->decrement('posti_disponibili', $totale);
            $sess->decrement('posti_in_attesa', min($totale, $sess->posti_in_attesa ?? 0));

            // Aggiorna contatori per tipologia
            $prenotazione->load('posti');
            foreach ($prenotazione->posti as $pp) {
                SessioneTipologiaPosto::where('sessione_id', $sess->id)
                    ->where('tipologia_posto_id', $pp->tipologia_posto_id)
                    ->decrement('posti_disponibili', $pp->quantita);
            }

            // Notifica conferma
            $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi', 'posti.tipologiaPosto']);
            $this->notifiche->invia($prenotazione, 'PRENOTAZIONE_APPROVATA');

            $this->log->log(
                $sess->evento_id,
                'lista_attesa.confermata',
                "Lista attesa: {$prenotazione->cognome} {$prenotazione->nome} ha confermato il posto (cod. {$prenotazione->codice})."
            );

            return response()->json([
                'message'             => 'Prenotazione confermata con successo.',
                'codice_prenotazione' => $prenotazione->codice,
                'token_accesso'       => $prenotazione->token_accesso,
            ]);
        });
    }

    private function generaCodice(): string
    {
        $anno   = now()->year;
        $ultimo = Prenotazione::whereYear('created_at', now()->year)->lockForUpdate()->count();
        return sprintf('CRN-%d-%05d', $anno, $ultimo + 1);
    }
}
