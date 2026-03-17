<?php

namespace Database\Seeders;

use App\Models\MailTemplate;
use Illuminate\Database\Seeder;

class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [

            // ─── Prenotazione ────────────────────────────────────────────────

            [
                'tipo'    => 'PRENOTAZIONE_CONFERMATA',
                'oggetto' => 'Prenotazione confermata – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>La tua prenotazione è stata <strong>confermata</strong> con successo! 🎉</p>

<table style="border-collapse:collapse;width:100%;margin:16px 0">
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold;width:40%">Evento</td><td style="padding:6px 12px">{{titolo_evento}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Data</td><td style="padding:6px 12px">{{data_sessione}} ore {{ora_inizio}}–{{ora_fine}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Luogo</td><td style="padding:6px 12px">{{luogo_evento}} – {{indirizzo_luogo}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Posti</td><td style="padding:6px 12px">{{dettaglio_posti}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Totale</td><td style="padding:6px 12px">{{costo_totale}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Codice prenotazione</td><td style="padding:6px 12px"><strong>{{codice_prenotazione}}</strong></td></tr>
</table>

<p>Conserva questo codice: ti sarà richiesto all'ingresso.</p>

<p style="margin-top:24px">
  <a href="{{link_prenotazione}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Visualizza la tua prenotazione</a>
</p>

<p style="margin-top:24px;font-size:13px;color:#777">
  <strong>Cancellazione:</strong> {{info_cancellazione}}<br>
  <a href="{{link_annullamento}}">Annulla la prenotazione</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'PRENOTAZIONE_DA_CONFERMARE',
                'oggetto' => 'Prenotazione ricevuta – in attesa di approvazione – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Abbiamo ricevuto la tua richiesta di prenotazione per <strong>{{titolo_evento}}</strong>.<br>
La prenotazione è <strong>in attesa di approvazione</strong> da parte dell'organizzatore.</p>

<table style="border-collapse:collapse;width:100%;margin:16px 0">
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold;width:40%">Evento</td><td style="padding:6px 12px">{{titolo_evento}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Data</td><td style="padding:6px 12px">{{data_sessione}} ore {{ora_inizio}}–{{ora_fine}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Luogo</td><td style="padding:6px 12px">{{luogo_evento}} – {{indirizzo_luogo}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Posti</td><td style="padding:6px 12px">{{dettaglio_posti}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Totale</td><td style="padding:6px 12px">{{costo_totale}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Codice</td><td style="padding:6px 12px"><strong>{{codice_prenotazione}}</strong></td></tr>
</table>

<p>Riceverai una email di conferma non appena la richiesta sarà approvata.</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'PRENOTAZIONE_APPROVATA',
                'oggetto' => 'Prenotazione approvata – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Ottima notizia! La tua prenotazione per <strong>{{titolo_evento}}</strong> è stata <strong>approvata</strong>. ✅</p>

<table style="border-collapse:collapse;width:100%;margin:16px 0">
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold;width:40%">Evento</td><td style="padding:6px 12px">{{titolo_evento}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Data</td><td style="padding:6px 12px">{{data_sessione}} ore {{ora_inizio}}–{{ora_fine}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Luogo</td><td style="padding:6px 12px">{{luogo_evento}} – {{indirizzo_luogo}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Posti</td><td style="padding:6px 12px">{{dettaglio_posti}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Codice prenotazione</td><td style="padding:6px 12px"><strong>{{codice_prenotazione}}</strong></td></tr>
</table>

<p style="margin-top:24px">
  <a href="{{link_prenotazione}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Visualizza la tua prenotazione</a>
</p>

<p style="margin-top:24px;font-size:13px;color:#777">
  <strong>Cancellazione:</strong> {{info_cancellazione}}<br>
  <a href="{{link_annullamento}}">Annulla la prenotazione</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'PRENOTAZIONE_ANNULLATA_UTENTE',
                'oggetto' => 'Prenotazione annullata – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>La tua prenotazione per <strong>{{titolo_evento}}</strong> (codice <strong>{{codice_prenotazione}}</strong>) è stata <strong>annullata</strong> come da tua richiesta.</p>

<p>Se vuoi prenotare nuovamente puoi farlo dal sito:</p>

<p style="margin-top:16px">
  <a href="{{link_vetrina}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Torna alla vetrina</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'PRENOTAZIONE_ANNULLATA_OPERATORE',
                'oggetto' => 'Prenotazione annullata dall\'organizzatore – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Purtroppo la tua prenotazione per <strong>{{titolo_evento}}</strong> (codice <strong>{{codice_prenotazione}}</strong>) è stata <strong>annullata dall'organizzatore</strong>.</p>

{{motivo_annullamento}}

<p>Per informazioni contatta l'organizzatore:</p>
<ul>
  <li>Email: <a href="mailto:{{email_ente}}">{{email_ente}}</a></li>
  <li>Telefono: {{telefono_ente}}</li>
</ul>

<p>Ci scusiamo per il disagio.<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'PRENOTAZIONE_NOTIFICA_STAFF',
                'oggetto' => '[Staff] Nuova prenotazione – {{titolo_evento}} – {{codice_prenotazione}}',
                'corpo'   => <<<HTML
<p>È stata ricevuta una nuova prenotazione.</p>

<table style="border-collapse:collapse;width:100%;margin:16px 0">
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold;width:40%">Codice</td><td style="padding:6px 12px"><strong>{{codice_prenotazione}}</strong></td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Prenotante</td><td style="padding:6px 12px">{{nome_utente}} {{cognome_utente}} – {{email_utente}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Evento</td><td style="padding:6px 12px">{{titolo_evento}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Data</td><td style="padding:6px 12px">{{data_sessione}} ore {{ora_inizio}}–{{ora_fine}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Posti</td><td style="padding:6px 12px">{{dettaglio_posti}} (tot. {{posti_prenotati}})</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Totale</td><td style="padding:6px 12px">{{costo_totale}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Note</td><td style="padding:6px 12px">{{note_prenotazione}}</td></tr>
</table>

<p>
  <a href="{{link_prenotazione}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Gestisci nel pannello</a>
</p>
HTML,
            ],

            // ─── Evento ──────────────────────────────────────────────────────

            [
                'tipo'    => 'EVENTO_ANNULLATO',
                'oggetto' => 'Evento annullato – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Siamo spiacenti di informarti che l'evento <strong>{{titolo_evento}}</strong> previsto per il <strong>{{data_sessione}}</strong> è stato <strong>annullato</strong>.</p>

{{motivo_annullamento}}

<p>La tua prenotazione (codice <strong>{{codice_prenotazione}}</strong>) è stata automaticamente annullata.</p>

<p>Per maggiori informazioni contatta l'organizzatore:</p>
<ul>
  <li>Email: <a href="mailto:{{email_ente}}">{{email_ente}}</a></li>
  <li>Telefono: {{telefono_ente}}</li>
</ul>

<p>Ci scusiamo per il disagio.<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'REMINDER_EVENTO',
                'oggetto' => 'Promemoria – {{titolo_evento}} è domani!',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Ti ricordiamo che hai una prenotazione per <strong>{{titolo_evento}}</strong>! 📅</p>

<table style="border-collapse:collapse;width:100%;margin:16px 0">
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold;width:40%">Data</td><td style="padding:6px 12px">{{data_sessione}} ore {{ora_inizio}}–{{ora_fine}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Luogo</td><td style="padding:6px 12px">{{luogo_evento}} – {{indirizzo_luogo}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Posti</td><td style="padding:6px 12px">{{dettaglio_posti}}</td></tr>
  <tr><td style="padding:6px 12px;background:#f5f5f5;font-weight:bold">Codice</td><td style="padding:6px 12px"><strong>{{codice_prenotazione}}</strong></td></tr>
</table>

<p style="margin-top:24px">
  <a href="{{link_prenotazione}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Visualizza la tua prenotazione</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            // ─── Lista d'attesa ───────────────────────────────────────────────

            [
                'tipo'    => 'LISTA_ATTESA_ISCRIZIONE',
                'oggetto' => 'Iscrizione lista d\'attesa – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Sei stato aggiunto alla <strong>lista d'attesa</strong> per l'evento <strong>{{titolo_evento}}</strong> del <strong>{{data_sessione}}</strong>.</p>

<p>Riceverai una notifica non appena si libererà un posto. Ti verrà data una finestra di tempo per confermare la prenotazione.</p>

<p>Se nel frattempo non sei più interessato, puoi rimuoverti dalla lista dal tuo account.</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'LISTA_ATTESA_POSTO_DISPONIBILE',
                'oggetto' => 'Si è liberato un posto! – {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Buone notizie! Si è liberato un posto per <strong>{{titolo_evento}}</strong> del <strong>{{data_sessione}}</strong>. 🎟️</p>

<p><strong>Hai tempo limitato per confermare la prenotazione.</strong><br>
Accedi al tuo account e completa la prenotazione prima che il posto venga assegnato ad altri.</p>

<p style="margin-top:24px">
  <a href="{{link_vetrina}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Prenota ora</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'LISTA_ATTESA_SCADENZA',
                'oggetto' => 'Scadenza imminente – conferma il tuo posto per {{titolo_evento}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>⚠️ Il tempo per confermare il tuo posto per <strong>{{titolo_evento}}</strong> del <strong>{{data_sessione}}</strong> sta per scadere.</p>

<p>Se non completi la prenotazione entro breve, il posto verrà offerto al prossimo nella lista d'attesa.</p>

<p style="margin-top:24px">
  <a href="{{link_vetrina}}" style="background:#e3342f;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Completa la prenotazione ora</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            // ─── Account ─────────────────────────────────────────────────────

            [
                'tipo'    => 'REGISTRAZIONE_CONFERMATA',
                'oggetto' => 'Benvenuto su {{nome_ente}} – account creato',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Il tuo account è stato creato con successo su <strong>{{nome_ente}}</strong>. 🎉</p>

<p>Puoi ora accedere alla piattaforma e prenotare i tuoi eventi preferiti.</p>

<p style="margin-top:24px">
  <a href="{{link_vetrina}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Esplora gli eventi</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'BENVENUTO_OPERATORE',
                'oggetto' => 'Benvenuto su {{nome_ente}} – le tue credenziali di accesso',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}} {{cognome_utente}}</strong>,</p>

<p>Il tuo account operatore è stato creato con successo su <strong>{{nome_ente}}</strong>.</p>

<p>Ecco le tue credenziali di primo accesso:</p>

<table style="border-collapse:collapse;margin:16px 0">
  <tr>
    <td style="padding:6px 12px 6px 0;color:#555;font-weight:500">Email</td>
    <td style="padding:6px 0"><strong>{{email_utente}}</strong></td>
  </tr>
  <tr>
    <td style="padding:6px 12px 6px 0;color:#555;font-weight:500">Password temporanea</td>
    <td style="padding:6px 0"><strong>{{password_temporanea}}</strong></td>
  </tr>
</table>

<p style="color:#c0392b;font-size:13px">⚠️ Al primo accesso ti verrà chiesto di cambiare la password.</p>

<p style="margin-top:24px">
  <a href="{{link_login}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Accedi alla piattaforma</a>
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

            [
                'tipo'    => 'RESET_PASSWORD',
                'oggetto' => 'Reimposta la tua password – {{nome_ente}}',
                'corpo'   => <<<HTML
<p>Ciao <strong>{{nome_utente}}</strong>,</p>

<p>Abbiamo ricevuto una richiesta di reimpostazione della password per il tuo account (<strong>{{email_utente}}</strong>).</p>

<p>Se sei stato tu, clicca sul pulsante qui sotto per scegliere una nuova password:</p>

<p style="margin-top:24px">
  <a href="{{link_reset_password}}" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Reimposta password</a>
</p>

<p style="margin-top:16px;font-size:13px;color:#777">
  Se non hai richiesto il reset, ignora questa email. Il link scade entro 60 minuti.
</p>

<p>A presto,<br><strong>{{nome_ente}}</strong></p>
HTML,
            ],

        ];

        foreach ($templates as $t) {
            MailTemplate::updateOrCreate(
                ['ente_id' => null, 'tipo' => $t['tipo']],
                [
                    'oggetto' => $t['oggetto'],
                    'corpo'   => $t['corpo'],
                    'sistema' => true,
                    'attivo'  => true,
                ]
            );
        }

        $this->command->info('✅ ' . count($templates) . ' template email di sistema inseriti/aggiornati.');
    }
}
