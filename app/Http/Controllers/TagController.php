<?php

namespace App\Http\Controllers;

use App\Models\Ente;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    /** GET /api/enti/{ente}/tags */
    public function index(Request $request, Ente $ente): JsonResponse
    {
        $tags = Tag::where('ente_id', $ente->id)
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get();

        return response()->json($tags);
    }

    /** POST /api/enti/{ente}/tags */
    public function store(Request $request, Ente $ente): JsonResponse
    {
        $data = $request->validate([
            'nome'   => 'required|string|max:100',
            'colore' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $data['ente_id'] = $ente->id;
        $data['slug']    = Str::slug($data['nome']);

        // Slug univoco per ente
        $base = $data['slug'];
        $i    = 1;
        while (Tag::where('ente_id', $ente->id)->where('slug', $data['slug'])->exists()) {
            $data['slug'] = "{$base}-{$i}";
            $i++;
        }

        $tag = Tag::create($data);

        return response()->json($tag, 201);
    }

    /** GET /api/enti/{ente}/tags/{tag} */
    public function show(Ente $ente, Tag $tag): JsonResponse
    {
        $this->autorizzaTag($ente, $tag);

        return response()->json($tag);
    }

    /** PUT /api/enti/{ente}/tags/{tag} */
    public function update(Request $request, Ente $ente, Tag $tag): JsonResponse
    {
        $this->autorizzaTag($ente, $tag);

        $data = $request->validate([
            'nome'   => 'sometimes|string|max:100',
            'colore' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if (isset($data['nome'])) {
            $data['slug'] = Str::slug($data['nome']);
        }

        $tag->update($data);

        return response()->json($tag);
    }

    /** DELETE /api/enti/{ente}/tags/{tag} */
    public function destroy(Ente $ente, Tag $tag): JsonResponse
    {
        $this->autorizzaTag($ente, $tag);
        $tag->delete();

        return response()->json(['message' => 'Tag eliminato.']);
    }

    private function autorizzaTag(Ente $ente, Tag $tag): void
    {
        abort_if((int) $tag->ente_id !== (int) $ente->id, 403, 'Tag non appartiene a questo Ente.');
    }
}
