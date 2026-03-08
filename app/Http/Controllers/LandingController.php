<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LandingController extends Controller
{
    /**
     * POST /api/contatto-piattaforma
     * Riceve il form contatti della landing page e invia una notifica email.
     */
    public function contatto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome'      => 'required|string|max:150',
            'email'     => 'required|email|max:255',
            'telefono'  => 'nullable|string|max:50',
            'messaggio' => 'required|string|max:3000',
        ]);

        $destinatario = config('mail.contact_address', config('mail.from.address'));

        if ($destinatario) {
            $body = "Nuovo messaggio dalla landing page di Crono\n\n"
                . "Nome: {$data['nome']}\n"
                . "Email: {$data['email']}\n"
                . "Telefono: " . ($data['telefono'] ?? '—') . "\n\n"
                . "Messaggio:\n{$data['messaggio']}";

            Mail::raw($body, function ($message) use ($data, $destinatario) {
                $message->to($destinatario)
                        ->replyTo($data['email'], $data['nome'])
                        ->subject("[Crono] Nuovo contatto da {$data['nome']}");
            });
        }

        return response()->json(['message' => 'Messaggio inviato con successo.'], 201);
    }
}
