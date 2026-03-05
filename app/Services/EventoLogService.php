<?php

namespace App\Services;

use App\Models\EventoLog;
use Illuminate\Http\Request;

class EventoLogService
{
    public function __construct(protected Request $request) {}

    public function log(int $eventoId, string $azione, string $descrizione, ?array $dettagli = null): void
    {
        EventoLog::create([
            'evento_id'   => $eventoId,
            'user_id'     => $this->request->user()?->id,
            'azione'      => $azione,
            'descrizione' => $descrizione,
            'dettagli'    => $dettagli,
        ]);
    }

    // ─── Helper per diff leggibili ────────────────────────────────────────────

    /**
     * Confronta due array e restituisce solo i campi che sono cambiati,
     * con i valori before/after. Esclude campi tecnici non significativi.
     */
    public function diff(array $before, array $after, array $escludi = []): array
    {
        $escludi = array_merge($escludi, ['updated_at', 'created_at']);
        $cambiamenti = [];
        foreach ($after as $chiave => $nuovoValore) {
            if (in_array($chiave, $escludi)) continue;
            $vecchioValore = $before[$chiave] ?? null;
            if ($vecchioValore != $nuovoValore) {
                $cambiamenti[$chiave] = ['da' => $vecchioValore, 'a' => $nuovoValore];
            }
        }
        return $cambiamenti;
    }

    /**
     * Genera una descrizione testuale leggibile da un array di cambiamenti diff().
     */
    public function descriviDiff(array $diff, array $etichette = []): string
    {
        if (empty($diff)) return 'Nessuna modifica rilevante.';
        $parti = [];
        foreach ($diff as $campo => $valori) {
            $etichetta = $etichette[$campo] ?? $campo;
            $da = $valori['da'] === null ? 'non impostato' : $valori['da'];
            $a  = $valori['a']  === null ? 'non impostato' : $valori['a'];
            $parti[] = "{$etichetta}: «{$da}» → «{$a}»";
        }
        return implode('; ', $parti);
    }
}
