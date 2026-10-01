<?php

namespace App\Notifications;

use App\Models\Contribution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public Contribution $contribution;
    public string $tontineName;
    public int $roundNumber;

    public function __construct(Contribution $contribution, string $tontineName, int $roundNumber)
    {
        $this->contribution = $contribution;
        $this->tontineName = $tontineName;
        $this->roundNumber = $roundNumber;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $statusText = $this->contribution->punctuality_status === 'on_time' ? 'dans les temps (On-Time)' : 'en retard (Late)';

        // Log SMS channel simulation
        Log::info("SMS Alert to {$notifiable->getKey()}: Versement de {$this->contribution->amount} FCFA pour {$this->tontineName} enregistre {$statusText}.");

        return [
            'title' => 'Versement reçu',
            'message' => "Votre versement de {$this->contribution->amount} FCFA via {$this->contribution->payment_provider} pour la tontine '{$this->tontineName}' (Tour #{$this->roundNumber}) a été enregistré {$statusText}.",
            'event_type' => 'payment_received',
            'contribution_id' => $this->contribution->id,
            'amount' => (float) $this->contribution->amount,
            'payment_provider' => $this->contribution->payment_provider,
            'transaction_reference' => $this->contribution->transaction_reference,
            'punctuality_status' => $this->contribution->punctuality_status,
            'tontine_name' => $this->tontineName,
            'round_number' => $this->roundNumber,
        ];
    }
}
