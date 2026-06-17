<?php

namespace App\Events;

use App\Models\Sessione;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostiEsauriti implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sessione_id;
    public int $evento_id;

    public function __construct(Sessione $sessione)
    {
        $this->sessione_id = $sessione->id;
        $this->evento_id   = $sessione->evento_id;
    }

    /**
     * Canale per-sessione (Booking.vue) + canale per-evento (EventoDettaglio).
     *
     * @return Channel[]
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("sessione.{$this->sessione_id}"),
            new Channel("evento.{$this->evento_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'posti.esauriti';
    }
}
