<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentStorageService
{
    protected $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('documents');
    }

    /**
     * Salva il file e crea il record in DB.
     * Struttura: {ente_id}/{anno}/{mm}/{sha256}.{ext}
     */
    public function store(UploadedFile $file, int $enteId, ?string $description = null): Document
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $now  = now();

        $relativePath = sprintf('%d/%d/%02d', $enteId, $now->year, $now->month);
        $fileName     = $hash . '.' . strtolower($file->getClientOriginalExtension());

        // Deduplicazione: stesso hash + ente → ritorna record esistente
        $existing = Document::where('name', $fileName)
            ->where('ente_id', $enteId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $this->disk->putFileAs($relativePath, $file, $fileName);

        return Document::create([
            'ente_id'       => $enteId,
            'user_id'       => Auth::id(),
            'file_name'     => $file->getClientOriginalName(),
            'name'          => $fileName,
            'relative_path' => $relativePath,
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'description'   => $description,
        ]);
    }

    /**
     * Restituisce l'URL pubblico per servire il documento.
     */
    public function url(Document $document): ?string
    {
        if (! $this->exists($document)) {
            return null;
        }

        return url('/api/documents/' . $document->id . '/serve');
    }

    public function exists(Document $document): bool
    {
        return $this->disk->exists($document->relative_path . '/' . $document->name);
    }

    public function get(Document $document): string
    {
        return $this->disk->get($document->relative_path . '/' . $document->name);
    }

    public function delete(Document $document): void
    {
        $this->disk->delete($document->relative_path . '/' . $document->name);
        $document->delete();
    }
}
