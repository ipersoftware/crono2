<?php

namespace App\Services;

use App\Mail\TemplatePrenotazioneMail;
use App\Models\MailTemplate;
use App\Models\NotificaLog;
use App\Models\Prenotazione;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificaService
{
    /**
     * Invia la notifica email per una prenotazione.
     *
     * @param  Prenotazione  $prenotazione  Con relazioni sessione.evento.ente, posti.tipologiaPosto già caricate (o caricate internamente)
     * @param  string        $tipo          Es. 'PRENOTAZIONE_CONFERMATA'
     * @param  array         $extraPlaceholders  Placeholder aggiuntivi (es. motivo_annullamento)
     */
    public function invia(Prenotazione $prenotazione, string $tipo, array $extraPlaceholders = []): void
    {
        // Carica relazioni necessarie se non già caricate
        if (!$prenotazione->relationLoaded('sessione')) {
            $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi', 'posti.tipologiaPosto']);
        } elseif (!$prenotazione->sessione->relationLoaded('evento')) {
            $prenotazione->sessione->load(['evento.ente', 'luoghi']);
        }

        $sessione = $prenotazione->sessione;
        $evento   = $sessione?->evento;
        $ente     = $evento?->ente;

        if (!$ente) {
            Log::warning("NotificaService: ente non trovato per prenotazione #{$prenotazione->id}, tipo {$tipo}");
            return;
        }

        // 1. Risolvi template
        $template = MailTemplate::risolvi($ente->id, $tipo);

        if (!$template) {
            Log::info("NotificaService: nessun template attivo per tipo {$tipo}, ente #{$ente->id}");
            return;
        }

        // 2. Costruisci placeholder
        $placeholders = array_merge($this->buildPlaceholders($prenotazione), $extraPlaceholders);

        // 3. Rendering
        ['oggetto' => $oggetto, 'corpo' => $corpo] = $template->renderizza($placeholders);

        // 4. Crea log (stato IN_CODA)
        $log = NotificaLog::create([
            'ente_id'           => $ente->id,
            'prenotazione_id'   => $prenotazione->id,
            'tipo'              => $tipo,
            'destinatario_email' => $prenotazione->email,
            'oggetto'           => $oggetto,
            'stato'             => 'IN_CODA',
        ]);

        // 5. Invia (sincrono; wrappare in un Job per la coda se si vuole asincrono)
        try {
            Mail::to($prenotazione->email)
                ->send(new TemplatePrenotazioneMail($oggetto, $corpo));

            $log->update(['stato' => 'INVIATA', 'inviata_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['stato' => 'ERRORE', 'errore' => $e->getMessage()]);
            Log::error("NotificaService: errore invio {$tipo} a {$prenotazione->email}: {$e->getMessage()}");
        }
    }

    /**
     * Notifica staff ente (tutti gli operatori/admin) per una nuova prenotazione.
     */
    public function inviaNotificaStaff(Prenotazione $prenotazione): void
    {
        if (!$prenotazione->relationLoaded('sessione')) {
            $prenotazione->load(['sessione.evento.ente', 'sessione.evento.staffNotifiche', 'posti.tipologiaPosto']);
        }

        $ente  = $prenotazione->sessione?->evento?->ente;
        $evento = $prenotazione->sessione?->evento;
        if (!$ente) {
            return;
        }

        $template = MailTemplate::risolvi($ente->id, 'PRENOTAZIONE_NOTIFICA_STAFF');
        if (!$template) {
            return;
        }

        $placeholders = $this->buildPlaceholders($prenotazione);
        ['oggetto' => $oggetto, 'corpo' => $corpo] = $template->renderizza($placeholders);

        // Staff specifici dell'evento; fallback su config ente / email ente
        $staffEvento = $evento?->staffNotifiche ?? collect();
        if ($staffEvento->isNotEmpty()) {
            $emailsStaff = $staffEvento->pluck('email')->filter()->values()->toArray();
        } else {
            $emailsStaff = $this->getEmailsStaff($ente);
        }

        foreach ($emailsStaff as $emailStaff) {
            $log = NotificaLog::create([
                'ente_id'           => $ente->id,
                'prenotazione_id'   => $prenotazione->id,
                'tipo'              => 'PRENOTAZIONE_NOTIFICA_STAFF',
                'destinatario_email' => $emailStaff,
                'oggetto'           => $oggetto,
                'stato'             => 'IN_CODA',
            ]);

            try {
                Mail::to($emailStaff)->send(new TemplatePrenotazioneMail($oggetto, $corpo));
                $log->update(['stato' => 'INVIATA', 'inviata_at' => now()]);
            } catch (\Throwable $e) {
                $log->update(['stato' => 'ERRORE', 'errore' => $e->getMessage()]);
                Log::error("NotificaService: errore staff notify a {$emailStaff}: {$e->getMessage()}");
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Invia notifica email per una prenotazione in stato lista d'attesa.
     *
     * @param  Prenotazione $prenotazione  Stato IN_LISTA_ATTESA, NOTIFICATO o simile
     * @param  string       $tipo          Es. 'LISTA_ATTESA_ISCRIZIONE'
     * @param  array        $extraPlaceholders
     */
    public function inviaAListaAttesa(Prenotazione $prenotazione, string $tipo, array $extraPlaceholders = []): void
    {
        if (!$prenotazione->relationLoaded('sessione')) {
            $prenotazione->load(['sessione.evento.ente', 'sessione.luoghi']);
        }

        $sessione = $prenotazione->sessione;
        $evento   = $sessione?->evento;
        $ente     = $evento?->ente;

        if (!$ente) {
            Log::warning("NotificaService: ente non trovato per prenotazione #{$prenotazione->id} (lista attesa), tipo {$tipo}");
            return;
        }

        $template = MailTemplate::risolvi($ente->id, $tipo);
        if (!$template) {
            Log::info("NotificaService: nessun template attivo per tipo {$tipo}, ente #{$ente->id}");
            return;
        }

        $luogo        = $sessione?->luoghi?->first();
        $frontendUrl  = rtrim(config('app.frontend_url', ''), '/');
        $placeholders = array_merge([
            '{{nome_utente}}'            => $prenotazione->nome ?? '',
            '{{cognome_utente}}'         => $prenotazione->cognome ?? '',
            '{{email_utente}}'           => $prenotazione->email ?? '',
            '{{titolo_evento}}'          => $evento?->titolo ?? '',
            '{{data_sessione}}'          => $sessione?->data_inizio?->translatedFormat('d F Y') ?? '',
            '{{ora_inizio}}'             => $sessione?->data_inizio?->format('H:i') ?? '',
            '{{ora_fine}}'               => $sessione?->data_fine?->format('H:i') ?? '',
            '{{luogo_evento}}'           => $luogo?->nome ?? '',
            '{{indirizzo_luogo}}'        => $luogo ? trim(($luogo->indirizzo ?? '') . ', ' . ($luogo->citta ?? ''), ', ') : '',
            '{{posti_richiesti}}'        => (string) ($prenotazione->posti_prenotati ?? 0),
            '{{posizione_lista_attesa}}' => (string) ($prenotazione->posizione_lista_attesa ?? '–'),
            '{{link_conferma_attesa}}'   => $frontendUrl . '/lista-attesa/' . ($prenotazione->token_accesso ?? '') . '/conferma',
            '{{scadenza_conferma}}'      => $prenotazione->scadenza_riserva
                ? $prenotazione->scadenza_riserva->translatedFormat('d F Y \a\l\l\e H:i')
                : '',
            '{{codice_prenotazione}}'    => $prenotazione->codice ?? '',
            '{{nome_ente}}'              => $ente?->nome ?? '',
            '{{email_ente}}'             => $ente?->email ?? '',
            '{{telefono_ente}}'          => $ente?->telefono ?? '',
            '{{link_vetrina}}'           => $ente ? $frontendUrl . '/vetrina/' . ($ente->shop_url ?? $ente->slug ?? '') : '',
        ], $extraPlaceholders);

        ['oggetto' => $oggetto, 'corpo' => $corpo] = $template->renderizza($placeholders);

        $log = NotificaLog::create([
            'ente_id'            => $ente->id,
            'prenotazione_id'    => $prenotazione->id,
            'tipo'               => $tipo,
            'destinatario_email' => $prenotazione->email,
            'oggetto'            => $oggetto,
            'stato'              => 'IN_CODA',
        ]);

        try {
            Mail::to($prenotazione->email)->send(new TemplatePrenotazioneMail($oggetto, $corpo));
            $log->update(['stato' => 'INVIATA', 'inviata_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['stato' => 'ERRORE', 'errore' => $e->getMessage()]);
            Log::error("NotificaService: errore invio {$tipo} a {$prenotazione->email}: {$e->getMessage()}");
        }
    }

    private function buildPlaceholders(Prenotazione $prenotazione): array
    {
        $sessione = $prenotazione->sessione;
        $evento   = $sessione?->evento;
        $ente     = $evento?->ente;

        $luogo = $sessione?->luoghi?->first();

        // Dettaglio posti: "2x Intero (15,00 €), 1x Ridotto (8,00 €)"
        $dettaglioPosti = '';
        if ($prenotazione->relationLoaded('posti')) {
            $dettaglioPosti = $prenotazione->posti->map(function ($p) {
                $nome   = $p->tipologiaPosto?->nome ?? 'Posto';
                $prezzo = number_format($p->costo_unitario ?? 0, 2, ',', '.') . ' €';
                return "{$p->quantita}x {$nome} ({$prezzo})";
            })->implode(', ');
        }

        $frontendUrl = rtrim(config('app.frontend_url', ''), '/');

        return [
            // Utente
            '{{nome_utente}}'           => $prenotazione->nome ?? '',
            '{{cognome_utente}}'        => $prenotazione->cognome ?? '',
            '{{email_utente}}'          => $prenotazione->email ?? '',

            // Evento / Sessione
            '{{titolo_evento}}'         => $evento?->titolo ?? '',
            '{{data_sessione}}'         => $sessione?->data_inizio?->translatedFormat('d F Y') ?? '',
            '{{ora_inizio}}'            => $sessione?->data_inizio?->format('H:i') ?? '',
            '{{ora_fine}}'              => $sessione?->data_fine?->format('H:i') ?? '',
            '{{luogo_evento}}'          => $luogo?->nome ?? '',
            '{{indirizzo_luogo}}'       => $luogo ? trim(($luogo->indirizzo ?? '') . ', ' . ($luogo->citta ?? ''), ', ') : '',
            '{{descrizione_sessione}}' => $sessione?->descrizione ?? '',

            // Prenotazione
            '{{codice_prenotazione}}'   => $prenotazione->codice ?? '',
            '{{posti_prenotati}}'       => (string) ($prenotazione->posti_prenotati ?? 0),
            '{{dettaglio_posti}}'       => $dettaglioPosti,
            '{{costo_totale}}'          => number_format($prenotazione->costo_totale ?? 0, 2, ',', '.') . ' €',
            '{{note_prenotazione}}'     => $prenotazione->note ?? '',
            '{{link_prenotazione}}'     => $frontendUrl . '/prenotazioni/' . ($prenotazione->codice ?? '') . '?token=' . ($prenotazione->token_accesso ?? ''),
            '{{link_annullamento}}'     => $frontendUrl . '/prenotazioni/' . ($prenotazione->codice ?? '') . '/annulla?token=' . ($prenotazione->token_accesso ?? ''),
            '{{motivo_annullamento}}'   => $prenotazione->motivo_annullamento ?? '',

            // Ente
            '{{nome_ente}}'             => $ente?->nome ?? '',
            '{{email_ente}}'            => $ente?->email ?? '',
            '{{telefono_ente}}'         => $ente?->telefono ?? '',
            '{{link_vetrina}}'          => $ente ? $frontendUrl . '/vetrina/' . ($ente->shop_url ?? $ente->slug ?? '') : '',
        ];
    }

    /**
     * Restituisce la lista di email staff da notificare.
     * Legge da ente.config['notifica_staff_emails'] o usa l'email dell'ente come fallback.
     */
    private function getEmailsStaff(\App\Models\Ente $ente): array
    {
        $config = $ente->config ?? [];

        // Se la notifica staff è disabilitata esplicitamente, non inviare
        if (isset($config['notifica_staff_abilitata']) && !$config['notifica_staff_abilitata']) {
            return [];
        }

        // Email configurate manualmente
        if (!empty($config['notifica_staff_emails']) && is_array($config['notifica_staff_emails'])) {
            return $config['notifica_staff_emails'];
        }

        // Fallback: email dell'ente
        return $ente->email ? [$ente->email] : [];
    }

    /**
     * Invia email di benvenuto a un utente appena creato dall'admin.
     * Usa il template BENVENUTO_OPERATORE per staff, REGISTRAZIONE_CONFERMATA per utenti normali.
     */
    public function inviaBenvenutoUtente(User $user, string $plainPassword): void
    {
        $tipo = in_array($user->role, ['operatore_ente', 'admin_ente', 'admin'], true)
            ? 'BENVENUTO_OPERATORE'
            : 'REGISTRAZIONE_CONFERMATA';

        $enteId = $user->ente_id ?? 0;
        $template = MailTemplate::risolvi($enteId, $tipo);

        if (!$template) {
            Log::info("NotificaService: nessun template per {$tipo}, skip benvenuto utente #{$user->id}");
            return;
        }

        if (!$user->relationLoaded('ente') && $user->ente_id) {
            $user->load('ente');
        }

        $ente        = $user->ente;
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url', '')), '/');

        $placeholders = [
            '{{nome_utente}}'        => $user->nome ?? '',
            '{{cognome_utente}}'     => $user->cognome ?? '',
            '{{email_utente}}'       => $user->email ?? '',
            '{{password_temporanea}}'=> htmlspecialchars($plainPassword, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            '{{nome_ente}}'          => $ente?->nome ?? config('app.name', ''),
            '{{email_ente}}'         => $ente?->email ?? '',
            '{{telefono_ente}}'      => $ente?->telefono ?? '',
            '{{link_vetrina}}'       => $ente
                ? $frontendUrl . '/vetrina/' . ($ente->shop_url ?? $ente->slug ?? '')
                : $frontendUrl,
            '{{link_login}}'         => $frontendUrl . '/login',
        ];

        ['oggetto' => $oggetto, 'corpo' => $corpo] = $template->renderizza($placeholders);

        $log = null;
        if ($user->ente_id !== null) {
            $log = NotificaLog::create([
                'ente_id'            => $user->ente_id,
                'prenotazione_id'    => null,
                'tipo'               => $tipo,
                'destinatario_email' => $user->email,
                'oggetto'            => $oggetto,
                'stato'              => 'IN_CODA',
            ]);
        }

        try {
            Mail::to($user->email)->send(new TemplatePrenotazioneMail($oggetto, $corpo));
            $log?->update(['stato' => 'INVIATA', 'inviata_at' => now()]);
        } catch (\Throwable $e) {
            $log?->update(['stato' => 'ERRORE', 'errore' => $e->getMessage()]);
            Log::error("NotificaService: errore invio benvenuto a {$user->email}: {$e->getMessage()}");
        }
    }
}
