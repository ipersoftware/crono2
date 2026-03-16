<?php

namespace App\Events;

use App\Models\Sessione;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostiTornatiDisponibili implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int    $sessione_id;
    public int    $evento_id;
    public int    $posti_liberi;

    public function __construct(Sessione $sessione)
    {
        $this->sessione_id  = $sessione->id;
        $this->evento_id    = $sessione->evento_id;
        $this->posti_liberi = max(0, $sessione->posti_disponibili - $sessione->posti_riservati);
    }

    /**
     * Canale pubblico per sessione — tutti gli utenti che guardano l'evento lo ricevono.
     */
    public function broadcastOn(): Channel
    {
        return new Channel("sessione.{$this->sessione_id}");
    }

    public function broadcastAs(): string
    {
        return 'posti.disponibili';
    }
}
