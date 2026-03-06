<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\MailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EnteController extends Controller
{
    /**
     * Lista enti
     */
    public function index(Request $request)
    {
        $query = Ente::query();

        if ($request->has('attivi')) {
            $query->where('attivo', true);
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('codice_fiscale', 'like', "%{$search}%");
            });
        }

        $enti = $query->orderBy('nome')->paginate(20);

        return response()->json($enti);
    }

    /**
     * Dettaglio ente
     */
    public function show(Ente $ente)
    {
        return response()->json($ente->load(['users']));
    }

    /**
     * Crea nuovo ente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'codice_fiscale' => ['required', 'string', 'max:16', 'unique:enti'],
            'partita_iva' => ['nullable', 'string', 'max:11'],
            'email' => ['required', 'email', 'unique:enti'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'indirizzo' => ['nullable', 'string'],
            'citta' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'size:2'],
            'cap' => ['nullable', 'string', 'size:5'],
            'descrizione' => ['nullable', 'string'],
            'attivo' => ['boolean'],
        ]);

        $ente = Ente::create($validated);

        $this->duplicaTemplateDefault($ente);

        return response()->json([
            'message' => 'Ente creato con successo',
            'ente' => $ente,
        ], 201);
    }

    /**
     * Aggiorna ente
     */
    public function update(Request $request, Ente $ente)
    {
        $validated = $request->validate([
            'nome' => ['sometimes', 'string', 'max:255'],
            'codice_fiscale' => ['sometimes', 'string', 'max:16', Rule::unique('enti')->ignore($ente->id)],
            'partita_iva' => ['nullable', 'string', 'max:11'],
            'email' => ['sometimes', 'email', Rule::unique('enti')->ignore($ente->id)],
            'telefono' => ['nullable', 'string', 'max:20'],
            'indirizzo' => ['nullable', 'string'],
            'citta' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'size:2'],
            'cap' => ['nullable', 'string', 'size:5'],
            'descrizione' => ['nullable', 'string'],
            'privacy_url' => ['nullable', 'url', 'max:500'],
            'attivo' => ['boolean'],
        ]);

        $ente->update($validated);

        return response()->json([
            'message' => 'Ente aggiornato con successo',
            'ente' => $ente,
        ]);
    }

    /**
     * Elimina ente (soft delete)
     */
    public function destroy(Ente $ente)
    {
        $ente->delete();

        return response()->json([
            'message' => 'Ente eliminato con successo',
        ]);
    }

    /**
     * Ritorna gli enti presenti nel DB Governance abilitati a Crono2
     * ma non ancora importati localmente (governance_id non presente in enti locale).
     * Solo per admin.
     */
    public function governanceDisponibili(Request $request)
    {
        // IDs già importati
        $importati = Ente::whereNotNull('governance_id')->pluck('governance_id')->all();

        $query = DB::connection('governance')
            ->table('enti as e')
            ->where('e.attivo', 1)
            ->select(
                'e.id as governance_id',
                'e.nome',
                'e.codice_fiscale',
                'e.email',
                'e.citta',
                'e.provincia'
            );

        if (count($importati) > 0) {
            $query->whereNotIn('e.id', $importati);
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('e.nome', 'like', "%{$s}%")
                  ->orWhere('e.codice_fiscale', 'like', "%{$s}%");
            });
        }

        $enti = $query->orderBy('e.nome')->get();

        return response()->json($enti);
    }

    /**
     * Importa uno o più enti dal DB Governance nel DB locale.
     * Solo per admin.
     */
    public function importaDaGovernance(Request $request)
    {
        $validated = $request->validate([
            'governance_ids'   => ['required', 'array', 'min:1'],
            'governance_ids.*' => ['required', 'integer'],
        ]);

        $importati = [];
        $errori    = [];

        foreach ($validated['governance_ids'] as $govId) {
            try {
                $govEnte = DB::connection('governance')
                    ->table('enti')
                    ->where('id', $govId)
                    ->first();

                if (!$govEnte) {
                    throw new \RuntimeException('Ente non trovato nel DB Governance.');
                }

                // Crea o aggiorna l'ente locale
                $ente = Ente::updateOrCreate(
                    ['governance_id' => $govId],
                    [
                        'nome'            => $govEnte->nome,
                        'codice_fiscale'  => $govEnte->codice_fiscale ?? null,
                        'email'           => $govEnte->email ?? null,
                        'telefono'        => $govEnte->telefono ?? null,
                        'indirizzo'       => $govEnte->indirizzo ?? null,
                        'citta'           => $govEnte->citta ?? null,
                        'provincia'       => $govEnte->provincia ?? null,
                        'cap'             => $govEnte->cap ?? null,
                        'attivo'          => true,
                    ]
                );

                if ($ente->wasRecentlyCreated) {
                    $this->duplicaTemplateDefault($ente);
                }

                $importati[] = [
                    'governance_id' => $govId,
                    'ente_id'       => $ente->id,
                    'nome'          => $ente->nome,
                ];
            } catch (\Throwable $e) {
                $errori[] = [
                    'governance_id' => $govId,
                    'errore'        => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'importati' => $importati,
            'errori'    => $errori,
            'message'   => count($importati) . ' ente/i importato/i con successo' .
                           (count($errori) ? ', ' . count($errori) . ' errore/i.' : '.'),
        ], count($importati) > 0 ? 200 : 422);
    }

    /**
     * Copia o aggiorna i template di sistema per un ente.
     * Aggiorna i template esistenti se già presenti (eccetto quelli marcati come personalizzati).
     * Solo per admin.
     */
    public function sincronizzaTemplate(Ente $ente)
    {
        $defaultTemplates = MailTemplate::whereNull('ente_id')->get();

        if ($defaultTemplates->isEmpty()) {
            return response()->json(['message' => 'Nessun template di sistema trovato.'], 422);
        }

        $creati   = 0;
        $aggiornati = 0;

        foreach ($defaultTemplates as $tmpl) {
            $esistente = MailTemplate::where('ente_id', $ente->id)
                ->where('tipo', $tmpl->tipo)
                ->first();

            if ($esistente) {
                $esistente->update([
                    'oggetto' => $tmpl->oggetto,
                    'corpo'   => $tmpl->corpo,
                    'attivo'  => $tmpl->attivo,
                ]);
                $aggiornati++;
            } else {
                MailTemplate::create([
                    'ente_id' => $ente->id,
                    'tipo'    => $tmpl->tipo,
                    'oggetto' => $tmpl->oggetto,
                    'corpo'   => $tmpl->corpo,
                    'sistema' => false,
                    'attivo'  => $tmpl->attivo,
                ]);
                $creati++;
            }
        }

        return response()->json([
            'message'    => "Template sincronizzati: {$creati} creati, {$aggiornati} aggiornati.",
            'creati'     => $creati,
            'aggiornati' => $aggiornati,
        ]);
    }

    /**
     * Duplica i template di sistema (ente_id = NULL) per il nuovo ente.
     * Salta i tipi per cui l'ente ha già un template.
     */
    private function duplicaTemplateDefault(Ente $ente): void
    {
        $defaultTemplates = MailTemplate::whereNull('ente_id')->get();

        if ($defaultTemplates->isEmpty()) {
            return;
        }

        $tipiEsistenti = MailTemplate::where('ente_id', $ente->id)
            ->pluck('tipo')
            ->all();

        foreach ($defaultTemplates as $tmpl) {
            if (in_array($tmpl->tipo, $tipiEsistenti, true)) {
                continue;
            }

            MailTemplate::create([
                'ente_id' => $ente->id,
                'tipo'    => $tmpl->tipo,
                'oggetto' => $tmpl->oggetto,
                'corpo'   => $tmpl->corpo,
                'sistema' => false,
                'attivo'  => $tmpl->attivo,
            ]);
        }
    }
}
