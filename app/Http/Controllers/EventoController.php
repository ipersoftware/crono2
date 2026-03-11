<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Evento;
use App\Services\DocumentStorageService;
use App\Services\EventoLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventoController extends Controller
{
    public function __construct(
        protected EventoLogService $log,
        protected DocumentStorageService $documentStorage,
    ) {}
    /** GET /api/enti/{ente}/eventi */
    public function index(Request $request, Ente $ente): JsonResponse
    {
        $eventi = Evento::where('ente_id', $ente->id)
            ->when($request->stato, fn ($q, $s) => $q->where('stato', $s))
            ->when($request->serie_id, fn ($q, $id) => $q->where('serie_id', $id))
            ->when($request->q, fn ($q, $search) => $q->where('titolo', 'like', "%{$search}%"))
            ->when($request->filled('anno'), fn ($q) => $q->whereHas('sessioni', fn ($sq) =>
                $sq->whereYear('data_inizio', (int) $request->anno)
            ))
            ->with(['serie', 'tags', 'luoghi'])
            ->withCount('sessioni')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($eventi);
    }

    /** POST /api/enti/{ente}/eventi */
    public function store(Request $request, Ente $ente): JsonResponse
    {
        $data = $this->validaEvento($request);
        $data['ente_id'] = $ente->id;
        $data['slug'] = $this->generaSlug($data['titolo'], $ente->id);

        $evento = Evento::create($data);

        // Sync tags, luoghi e staff notifiche
        if ($request->has('tag_ids')) {
            $evento->tags()->sync($request->tag_ids);
        }

        if ($request->has('luogo_ids')) {
            $luoghiPivot = collect($request->luogo_ids)->mapWithKeys(
                fn ($id, $index) => [$id => ['principale' => $index === 0]]
            );
            $evento->luoghi()->sync($luoghiPivot);
        }

        if ($request->has('staff_ids')) {
            $evento->staffNotifiche()->sync(
                collect($request->staff_ids)->filter(fn ($id) => is_numeric($id))
            );
        }

        $this->log->log($evento->id, 'evento.creato', "Evento \u00abcreato: {$evento->titolo}\u00bb (stato: {$evento->stato})");

        return response()->json($evento->load(['serie', 'tags', 'luoghi', 'staffNotifiche:id,nome,cognome,email']), 201);
    }

    /** GET /api/enti/{ente}/eventi/{evento} */
    public function show(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);

        return response()->json(
            $evento->load([
                'serie',
                'tags',
                'luoghi',
                'sessioni',
                'tipologiePosto',
                'campiForm',
                'ente:id,shop_url,slug',
                'staffNotifiche:id,nome,cognome,email',
            ])
        );
    }

    /** PUT /api/enti/{ente}/eventi/{evento} */
    public function update(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);

        $data = $this->validaEvento($request, partial: true);

        $before = $evento->only(array_keys($data));

        // Cattura tag prima del sync (IDs ordinati per confronto stabile)
        $tagIdsPrima   = $evento->tags()->orderBy('id')->pluck('id')->toArray();
        $tagNomiPrima  = $evento->tags()->orderBy('id')->pluck('nome', 'id')->toArray();

        // Aggiorna slug con storico se il titolo cambia
        if (isset($data['titolo']) && $data['titolo'] !== $evento->titolo) {
            $nuovoSlug = $this->generaSlug($data['titolo'], $ente->id, $evento->id);
            if ($nuovoSlug !== $evento->slug) {
                $history = $evento->slug_history ?? [];
                $history[] = $evento->slug;
                $data['slug'] = $nuovoSlug;
                $data['slug_history'] = array_unique($history);
            }
        }

        $evento->update($data);

        if ($request->has('tag_ids')) {
            $evento->tags()->sync($request->tag_ids);
            $tagIdsDopo   = $evento->tags()->orderBy('id')->pluck('id')->toArray();
            if ($tagIdsPrima !== $tagIdsDopo) {
                $tagNomiDopo  = $evento->tags()->orderBy('id')->pluck('nome', 'id')->toArray();
                $idAggiunti   = array_diff($tagIdsDopo, $tagIdsPrima);
                $idRimossi    = array_diff($tagIdsPrima, $tagIdsDopo);
                $parti = [];
                if (!empty($idAggiunti)) $parti[] = 'aggiunti: ' . implode(', ', array_map(fn($id) => $tagNomiDopo[$id] ?? "#$id", $idAggiunti));
                if (!empty($idRimossi))  $parti[] = 'rimossi: '  . implode(', ', array_map(fn($id) => $tagNomiPrima[$id] ?? "#$id", $idRimossi));
                $this->log->log($evento->id, 'evento.modificato', 'Tag aggiornati: ' . implode('; ', $parti), [
                    'tag_prima' => array_values($tagNomiPrima),
                    'tag_dopo'  => array_values($tagNomiDopo),
                ]);
            }
        }

        if ($request->has('luogo_ids')) {
            $luoghiPivot = collect($request->luogo_ids)->mapWithKeys(
                fn ($id, $index) => [$id => ['principale' => $index === 0]]
            );
            $evento->luoghi()->sync($luoghiPivot);
        }

        if ($request->has('staff_ids')) {
            $evento->staffNotifiche()->sync(
                collect($request->staff_ids)->filter(fn ($id) => is_numeric($id))
            );
        }

        $etichette = [
            'titolo' => 'Titolo', 'stato' => 'Stato', 'pubblico' => 'Pubblico',
            'descrizione_breve' => 'Descrizione breve',
            'richiede_approvazione' => 'Richiede approvazione', 'cancellazione_consentita_ore' => 'Cancellazione consentita (ore)',
            'prenotabile_dal' => 'Prenotabile dal', 'prenotabile_al' => 'Prenotabile al',
        ];
        $diff = $this->log->diff($before, $evento->fresh()->only(array_keys($before)), ['slug', 'slug_history', 'attributi']);
        if (!empty($diff)) {
            $descrizione = 'Dati evento aggiornati: ' . $this->log->descriviDiff($diff, $etichette);
            $this->log->log($evento->id, 'evento.modificato', $descrizione, $diff);
        }

        return response()->json($evento->fresh(['serie', 'tags', 'luoghi', 'staffNotifiche:id,nome,cognome,email']));

    }

    /** DELETE /api/enti/{ente}/eventi/{evento} */
    public function destroy(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);

        $sessioniIds = $evento->sessioni()->pluck('id');
        $prenotazioniAttive = \App\Models\Prenotazione::whereIn('sessione_id', $sessioniIds)
            ->whereNotIn('stato', ['ANNULLATA_UTENTE', 'ANNULLATA_OPERATORE', 'ANNULLATA_ADMIN', 'SCADUTA'])
            ->count();

        abort_if(
            $prenotazioniAttive > 0,
            422,
            "Impossibile eliminare: ci sono {$prenotazioniAttive} prenotazioni attive su questo evento. Annullale prima di procedere."
        );

        $evento->delete();

        return response()->json(['message' => 'Evento eliminato.']);
    }

    /** PATCH /api/enti/{ente}/eventi/{evento}/pubblica */
    public function pubblica(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->update(['stato' => 'PUBBLICATO', 'pubblico' => true]);
        $this->log->log($evento->id, 'evento.pubblicato', 'Evento pubblicato.');

        return response()->json($evento);
    }

    /** PATCH /api/enti/{ente}/eventi/{evento}/sospendi */
    public function sospendi(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->update(['stato' => 'SOSPESO', 'pubblico' => false]);
        $this->log->log($evento->id, 'evento.sospeso', 'Evento sospeso.');

        return response()->json($evento);
    }

    /** PATCH /api/enti/{ente}/eventi/{evento}/annulla */
    public function annulla(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->update(['stato' => 'ANNULLATO', 'pubblico' => false]);
        $this->log->log($evento->id, 'evento.annullato', 'Evento annullato.');

        // TODO: inviare notifica EVENTO_ANNULLATO a tutti i prenotati

        return response()->json($evento);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function validaEvento(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'serie_id'                    => 'nullable|exists:serie,id',
            'titolo'                      => "{$req}|string|max:255",
            'descrizione_breve'           => 'nullable|string|max:512',
            'descrizione'                 => 'nullable|string',
            'immagine'                    => 'nullable|string|max:255',
            'stato'                       => 'nullable|in:BOZZA,PUBBLICATO,SOSPESO,ANNULLATO',
            'pubblico'                    => 'nullable|boolean',
            'in_evidenza'                 => 'nullable|boolean',
            'ordinamento'                 => 'nullable|integer',
            'visibile_dal'                => 'nullable|date',
            'visibile_al'                 => 'nullable|date',
            'prenotabile_dal'             => 'nullable|date',
            'prenotabile_al'              => 'nullable|date',
            'richiede_approvazione'       => 'nullable|boolean',
            'consenti_multi_sessione'          => 'nullable|boolean',
            'consenti_prenotazioni_multiple'   => 'nullable|boolean',
            'consenti_prenotazione_guest' => 'nullable|boolean',
            'cancellazione_consentita_ore' => 'nullable|integer|min:-1',
            'mostra_disponibilita'        => 'nullable|boolean',
            'attiva_note'                 => 'nullable|boolean',
            'nota_etichetta'              => 'nullable|string|max:255',
            'costo'                       => 'nullable|numeric|min:0',
            'attributi'                   => 'nullable|array',
            'colore_primario'             => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colore_secondario'           => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }

    private function generaSlug(string $titolo, int $enteId, ?int $excludeId = null): string
    {
        $base = Str::slug($titolo);
        $slug = $base;
        $i    = 1;

        while (
            Evento::where('ente_id', $enteId)
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function autorizza(Ente $ente, Evento $evento): void
    {
        if (request()->user()?->isAdmin()) {
            return;
        }
        abort_if((int) $evento->ente_id !== (int) $ente->id, 403, 'Evento non appartiene a questo Ente.');
    }

    /** POST /api/enti/{ente}/eventi/{evento}/immagine */
    public function uploadImmagine(Request $request, Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);

        $request->validate([
            'file' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:3072',
        ]);

        $document = $this->documentStorage->store(
            $request->file('file'),
            $ente->id,
            "Copertina evento: {$evento->titolo}"
        );

        $url = $this->documentStorage->url($document);
        $evento->update(['immagine' => $url]);

        return response()->json(['immagine' => $url, 'document_id' => $document->id]);
    }

    /** DELETE /api/enti/{ente}/eventi/{evento}/immagine */
    public function eliminaImmagine(Ente $ente, Evento $evento): JsonResponse
    {
        $this->autorizza($ente, $evento);
        $evento->update(['immagine' => null]);

        return response()->json(['immagine' => null]);
    }

    /** GET /api/enti/{ente}/eventi/export-xls */
    public function exportXls(Request $request, Ente $ente): BinaryFileResponse
    {
        $q = Evento::where('ente_id', $ente->id)
            ->with(['serie', 'tags', 'luoghi', 'sessioni'])
            ->withCount('sessioni')
            ->when($request->stato, fn ($q, $s) => $q->where('stato', $s))
            ->when($request->filled('anno'), fn ($q) => $q->whereHas('sessioni', fn ($sq) =>
                $sq->whereYear('data_inizio', (int) $request->anno)
            ))
            ->when($request->q, fn ($q, $search) => $q->where('titolo', 'like', "%{$search}%"))
            ->orderByDesc('created_at');

        $eventi = $q->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Eventi');

        $headers = [
            'ID', 'Titolo', 'Slug', 'Stato', 'Serie',
            'Luoghi', 'Tag', 'N° Sessioni',
            'Prima sessione', 'Ultima sessione',
            'In evidenza', 'Pubblico', 'Costo €',
            'Creato il',
        ];

        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValue([$col, 1], $h);
            $col++;
        }
        $lastCol = $col - 1;

        $lastColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);
        $sheet->getStyle("A1:{$lastColStr}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B4F8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $row = 2;
        foreach ($eventi as $e) {
            $sessioni = $e->sessioni->sortBy('data_inizio');
            $prima    = $sessioni->first()?->data_inizio;
            $ultima   = $sessioni->last()?->data_inizio;

            $col = 1;
            $sheet->setCellValue([$col++, $row], $e->id);
            $sheet->setCellValue([$col++, $row], $e->titolo);
            $sheet->setCellValue([$col++, $row], $e->slug);
            $sheet->setCellValue([$col++, $row], $e->stato);
            $sheet->setCellValue([$col++, $row], $e->serie?->titolo ?? '');
            $sheet->setCellValue([$col++, $row], $e->luoghi->pluck('nome')->join(', '));
            $sheet->setCellValue([$col++, $row], $e->tags->pluck('nome')->join(', '));
            $sheet->setCellValue([$col++, $row], $e->sessioni_count);
            $sheet->setCellValue([$col++, $row], $prima  ? Carbon::parse($prima)->format('d/m/Y H:i')  : '');
            $sheet->setCellValue([$col++, $row], $ultima ? Carbon::parse($ultima)->format('d/m/Y H:i') : '');
            $sheet->setCellValue([$col++, $row], $e->in_evidenza ? 'Sì' : 'No');
            $sheet->setCellValue([$col++, $row], $e->pubblico    ? 'Sì' : 'No');
            $sheet->setCellValue([$col++, $row], (float) ($e->costo ?? 0));
            $sheet->setCellValue([$col++, $row], $e->created_at ? Carbon::parse($e->created_at)->format('d/m/Y') : '');

            if ($row % 2 === 0) {
                $rowRange = 'A' . $row . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $row;
                $sheet->getStyle($rowRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0F4FF');
            }

            $row++;
        }

        for ($c = 1; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c)
            )->setAutoSize(true);
        }

        $tempDir  = storage_path('app/temp');
        if (! is_dir($tempDir)) mkdir($tempDir, 0755, true);

        $filename = 'eventi_' . now()->format('Ymd_His') . '.xlsx';
        $filepath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        (new Xlsx($spreadsheet))->save($filepath);

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
