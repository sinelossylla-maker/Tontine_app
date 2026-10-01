<?php

namespace App\Notifications;

use App\Models\TontineRound;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PayoutExecutedNotification extends Notification
{
    use Queueable;

    public TontineRound $round;
    public string $tontineName;

    public function __construct(TontineRound $round, string $tontineName)
    {
        $this->round = $round;
        $this->tontineName = $tontineName;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        // Log SMS channel simulation
        Log::info("SMS Alert to {$notifiable->getKey()}: Remise de cagnotte de {$this->round->total_collected_amount} FCFA effectuee pour {$this->tontineName}.");

        return [
            'title' => 'Remise de la cagnotte effectuée',
            'message' => "Félicitations ! La cagnotte globale de {$this->round->total_collected_amount} FCFA pour la tontine '{$this->tontineName}' (Tour #{$this->round->round_number}) vous a été transférée.",
            'event_type' => 'payout_executed',
            'tontine_round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'amount' => (float) $this->round->total_collected_amount,
            'beneficiary_phone' => $this->round->beneficiary_phone,
            'tontine_name' => $this->tontineName,
        ];
    }
}
