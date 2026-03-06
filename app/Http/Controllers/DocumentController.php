<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function __construct(protected DocumentStorageService $storage) {}

    /**
     * GET /api/documents/{document}/serve  — pubblico, no auth
     * Streamma il file con Content-Type corretto e cache lunga.
     */
    public function serve(Document $document): Response
    {
        abort_unless($this->storage->exists($document), 404);

        return response($this->storage->get($document))
            ->header('Content-Type', $document->mime_type)
            ->header('Cache-Control', 'public, max-age=31536000');
    }

    /**
     * DELETE /api/documents/{document}  — richiede auth
     */
    public function destroy(Document $document): JsonResponse
    {
        $this->storage->delete($document);

        return response()->json(['message' => 'Documento eliminato.']);
    }
}
