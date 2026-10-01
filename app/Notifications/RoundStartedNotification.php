<?php

namespace App\Notifications;

use App\Models\TontineRound;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class RoundStartedNotification extends Notification
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
        $dueDateFormatted = $this->round->due_date ? $this->round->due_date->format('d/m/Y') : '';

        // Log SMS channel simulation
        Log::info("SMS Alert to {$notifiable->getKey()}: Le Tour #{$this->round->round_number} pour {$this->tontineName} est ouvert. Date limite : {$dueDateFormatted}.");

        return [
            'title' => 'Nouveau tour démarré',
            'message' => "Le Tour #{$this->round->round_number} de la tontine '{$this->tontineName}' a commencé. Date limite : {$dueDateFormatted}. Bénéficiaire : {$this->round->beneficiary_phone}.",
            'event_type' => 'round_started',
            'tontine_round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'due_date' => $this->round->due_date ? $this->round->due_date->toIso8601String() : null,
            'beneficiary_phone' => $this->round->beneficiary_phone,
            'tontine_name' => $this->tontineName,
        ];
    }
}
