<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sessione extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sessioni';

    protected $fillable = [
        'evento_id',
        'titolo',
        'descrizione',
        'data_inizio',
        'data_fine',
        'posti_totali',
        'posti_disponibili',
        'posti_in_attesa',
        'posti_riservati',
        'controlla_posti_globale',
        'prenotabile',
        'forza_non_disponibile',
        'soglia_chiusura_automatica',
        'soglia_overbooking_percentuale',
        'soglia_overbooking_assoluta',
        'attiva_lista_attesa',
        'lista_attesa_finestra_conferma_ore',
        'durata_lock_minuti',
        'note_pubbliche',
        'attributi',
    ];

    protected $casts = [
        'data_inizio'               => 'datetime',
        'data_fine'                 => 'datetime',
        'controlla_posti_globale'   => 'boolean',
        'prenotabile'               => 'boolean',
        'forza_non_disponibile'     => 'boolean',
        'attiva_lista_attesa'       => 'boolean',
        'attributi'                 => 'array',
    ];

    // ─── Relazioni ────────────────────────────────────────────────────────────

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function luoghi(): BelongsToMany
    {
        return $this->belongsToMany(Luogo::class, 'sessione_luogo');
    }

    public function tipologiePosto(): HasMany
    {
        return $this->hasMany(SessioneTipologiaPosto::class);
    }

    public function prenotazioni(): HasMany
    {
        return $this->hasMany(Prenotazione::class);
    }

    public function prenotazioniTemporanee(): HasMany
    {
        return $this->hasMany(PrenotazioneTemporanea::class);
    }

    public function listaAttesa(): HasMany
    {
        return $this->hasMany(ListaAttesa::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Verifica se ci sono posti disponibili (tiene conto dell'overbooking).
     */
    public function haPostiDisponibili(int $quantita = 1): bool
    {
        if ($this->forza_non_disponibile) {
            return false;
        }

        $postiMassimi = $this->calcolaPostiMassimi();

        if ($postiMassimi === null) {
            // illimitato
            return true;
        }

        return ($postiMassimi - $this->posti_disponibili_occupati()) >= $quantita;
    }

    /**
     * Posti totali + overbooking (null = illimitato).
     */
    public function calcolaPostiMassimi(): ?int
    {
        if ($this->posti_totali === 0) {
            return null;
        }

        $overbooking = 0;

        if ($this->soglia_overbooking_percentuale !== null) {
            $overbooking = (int) round($this->posti_totali * $this->soglia_overbooking_percentuale / 100);
        }

        if ($this->soglia_overbooking_assoluta !== null) {
            $overbooking = $this->soglia_overbooking_percentuale !== null
                ? min($overbooking, $this->soglia_overbooking_assoluta)
                : $this->soglia_overbooking_assoluta;
        }

        return $this->posti_totali + $overbooking;
    }

    private function posti_disponibili_occupati(): int
    {
        return ($this->posti_totali - $this->posti_disponibili) + $this->posti_riservati;
    }
}
