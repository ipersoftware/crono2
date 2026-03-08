<?php

namespace App\Console\Commands;

use App\Models\Prenotazione;
use App\Services\ListaAttesaService;
use App\Services\NotificaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessaListaAttesaScaduta extends Command
{
    protected $signature   = 'lista-attesa:processa-scadute';
    protected $description = 'Scade le notifiche di lista attesa non confermate entro la finestra e tenta il prossimo in coda.';

    public function __construct(
        private readonly NotificaService    $notifiche,
        private readonly ListaAttesaService $listaAttesa,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $scadute = Prenotazione::where('stato', 'NOTIFICATO')
            ->where('scadenza_riserva', '<=', now())
            ->with(['sessione.evento'])
            ->get();

        if ($scadute->isEmpty()) {
            $this->line('Nessuna notifica lista attesa scaduta.');
            return self::SUCCESS;
        }

        $this->info("Trovate {$scadute->count()} notifiche scadute.");

        foreach ($scadute as $prenotazione) {
            try {
                // Marca come scaduta
                $prenotazione->update([
                    'stato'                  => 'SCADUTA',
                    'posizione_lista_attesa' => null,
                ]);

                // Notifica l'utente che la finestra è scaduta
                $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi']);
                $this->notifiche->inviaAListaAttesa($prenotazione, 'LISTA_ATTESA_SCADENZA');

                $this->line("  Scaduta: {$prenotazione->cognome} {$prenotazione->nome} (cod. {$prenotazione->codice})");

                // Prova a notificare il prossimo in lista
                if ($prenotazione->sessione) {
                    $this->listaAttesa->processaPromozione($prenotazione->sessione);
                }
            } catch (\Throwable $e) {
                $this->error("  Errore per prenotazione #{$prenotazione->id}: {$e->getMessage()}");
                Log::error("ProcessaListaAttesaScaduta: errore prenotazione #{$prenotazione->id}: {$e->getMessage()}");
            }
        }

        $this->info('Elaborazione completata.');
        return self::SUCCESS;
    }
}
