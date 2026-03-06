<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\MailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailTemplateController extends Controller
{
    /** GET /api/enti/{ente}/mail-templates */
    public function index(Ente $ente): JsonResponse
    {
        return response()->json(
            MailTemplate::where('ente_id', $ente->id)
                ->orderBy('tipo')
                ->get()
        );
    }

    /**
     * GET /api/enti/{ente}/mail-templates/{template}
     * Restituisce il template personalizzato oppure quello di sistema come fallback.
     */
    public function show(Ente $ente, string $tipo): JsonResponse
    {
        // Prima cerca il template personalizzato per l'ente
        $template = MailTemplate::where('ente_id', $ente->id)
            ->where('tipo', $tipo)
            ->first();

        // Se non esiste, prende quello di sistema (ente_id NULL)
        if (!$template) {
            $template = MailTemplate::whereNull('ente_id')
                ->where('tipo', $tipo)
                ->first();
        }

        abort_if(!$template, 404, "Template '{$tipo}' non trovato.");

        return response()->json($template);
    }

    /**
     * POST /api/enti/{ente}/mail-templates
     * Crea (o sovrascrive) il template personalizzato per un tipo.
     */
    public function store(Request $request, Ente $ente): JsonResponse
    {
        $data = $request->validate([
            'tipo'    => 'required|in:PRENOTAZIONE_CONFERMATA,PRENOTAZIONE_DA_CONFERMARE,PRENOTAZIONE_APPROVATA,PRENOTAZIONE_ANNULLATA_UTENTE,PRENOTAZIONE_ANNULLATA_OPERATORE,PRENOTAZIONE_NOTIFICA_STAFF,EVENTO_ANNULLATO,LISTA_ATTESA_ISCRIZIONE,LISTA_ATTESA_POSTO_DISPONIBILE,LISTA_ATTESA_SCADENZA,REMINDER_EVENTO,REGISTRAZIONE_CONFERMATA,RESET_PASSWORD',
            'oggetto' => 'required|string|max:255',
            'corpo'   => 'required|string',
            'attivo'  => 'nullable|boolean',
        ]);

        $template = MailTemplate::updateOrCreate(
            ['ente_id' => $ente->id, 'tipo' => $data['tipo']],
            [
                'oggetto' => $data['oggetto'],
                'corpo'   => $data['corpo'],
                'attivo'  => $data['attivo'] ?? true,
            ]
        );

        return response()->json($template, $template->wasRecentlyCreated ? 201 : 200);
    }

    /** PUT /api/enti/{ente}/mail-templates/{template} */
    public function update(Request $request, Ente $ente, MailTemplate $mailTemplate): JsonResponse
    {
        $this->autorizza($ente, $mailTemplate);

        $data = $request->validate([
            'oggetto' => 'nullable|string|max:255',
            'corpo'   => 'nullable|string',
            'attivo'  => 'nullable|boolean',
        ]);

        $mailTemplate->update($data);

        return response()->json($mailTemplate);
    }

    /** DELETE /api/enti/{ente}/mail-templates/{template} */
    public function destroy(Ente $ente, MailTemplate $mailTemplate): JsonResponse
    {
        $this->autorizza($ente, $mailTemplate);

        $mailTemplate->delete();

        return response()->json(['message' => 'Template eliminato. Verrà usato il template di sistema.']);
    }

    /**
     * GET /api/enti/{ente}/mail-templates/{template}/anteprima
     * Renderizza il template con dati di esempio.
     */
    public function anteprima(Request $request, Ente $ente, MailTemplate $mailTemplate): JsonResponse
    {
        $this->autorizza($ente, $mailTemplate);

        $datiEsempio = $request->input('dati', [
            '{{nome}}'          => 'Mario',
            '{{cognome}}'       => 'Rossi',
            '{{email}}'         => 'mario.rossi@example.com',
            '{{codice}}'        => 'CRN-2026-00001',
            '{{evento}}'        => 'Evento di Esempio',
            '{{sessione_data}}' => '01/03/2026 10:00',
            '{{ente}}'          => $ente->nome,
            '{{link}}'          => url('/'),
        ]);

        return response()->json([
            'oggetto' => $mailTemplate->renderizza($datiEsempio)['oggetto'],
            'corpo'   => $mailTemplate->renderizza($datiEsempio)['corpo'],
        ]);
    }

    private function autorizza(Ente $ente, MailTemplate $template): void
    {
        abort_if((int) $template->ente_id !== (int) $ente->id, 403, 'Template non appartiene a questo Ente.');
    }
}
