<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Tontine;
use App\Models\TontineRound;
use App\Models\UserNotifiable;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PayoutExecutedNotification;
use App\Notifications\RoundStartedNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Service TontinePaymentService
 *
 * Core financial engine managing tontine lifecycle transitions, rotation rounds generation,
 * Mobile Money contribution recording, punctuality evaluation, and automated payout transfers.
 *
 * @package App\Services
 */
class TontinePaymentService
{
    /**
     * Start a tontine group and generate its rotation rounds schedule.
     *
     * Algorithmic Flow:
     * 1. Validates that the tontine is currently in `pending` status and has active members.
     * 2. Calculates expected round amount: `contribution_amount * max_members`.
     * 3. Executes an atomic DB transaction:
     *    - Transitions tontine status to `active`.
     *    - Maps each member's `turn_order` to a corresponding `round_number`.
     *    - Calculates `due_date` based on frequency (`weekly` => +N weeks, `monthly` => +N months).
     *    - Sets Round 1 to `active` and all subsequent rounds to `pending`.
     * 4. Dispatches `RoundStartedNotification` to all group members.
     *
     * @param  Tontine  $tontine  The tontine model instance to start.
     * @return Tontine The updated tontine instance loaded with generated rounds.
     *
     * @throws Exception If tontine is not in pending status or has no members.
     */
    public function startTontine(Tontine $tontine): Tontine
    {
        if ($tontine->status !== 'pending') {
            throw new Exception("Seule une tontine en statut 'pending' peut être démarrée.");
        }

        $members = $tontine->members()->orderBy('turn_order', 'asc')->get();

        if ($members->isEmpty()) {
            throw new Exception("Impossible de démarrer une tontine sans membres.");
        }

        $expectedAmountPerRound = (float) $tontine->contribution_amount * $tontine->max_members;

        $firstRound = DB::transaction(function () use ($tontine, $members, $expectedAmountPerRound) {
            $tontine->update(['status' => 'active']);

            $startDate = Carbon::parse($tontine->start_date);
            $firstRoundCreated = null;

            foreach ($members as $index => $member) {
                $roundNumber = $member->turn_order;

                if ($tontine->frequency === 'weekly') {
                    $dueDate = $startDate->copy()->addWeeks($index)->endOfDay();
                } else {
                    $dueDate = $startDate->copy()->addMonths($index)->endOfDay();
                }

                $round = TontineRound::create([
                    'tontine_id' => $tontine->id,
                    'round_number' => $roundNumber,
                    'beneficiary_phone' => $member->member_phone,
                    'due_date' => $dueDate,
                    'total_expected_amount' => $expectedAmountPerRound,
                    'total_collected_amount' => 0,
                    'status' => $roundNumber === 1 ? 'active' : 'pending',
                    'payout_status' => 'pending',
                ]);

                if ($roundNumber === 1) {
                    $firstRoundCreated = $round;
                }
            }

            return $firstRoundCreated;
        });

        // Notify members about round 1 activation
        if ($firstRound) {
            $this->notifyMembersRoundStarted($tontine, $firstRound);
        }

        return $tontine->load('rounds');
    }

    /**
     * Record a Mobile Money contribution for a specific active tontine round.
     *
     * Algorithmic Flow:
     * 1. Validates that the round is currently `active`.
     * 2. Verifies member belonging and checks for duplicate transaction reference or duplicate round payment.
     * 3. Compares `payment_timestamp` against `round->due_date`:
     *    - `payment_timestamp <= due_date` => `punctuality_status = 'on_time'`
     *    - `payment_timestamp > due_date` => `punctuality_status = 'late'`
     * 4. Executes an atomic DB transaction:
     *    - Creates `Contribution` record.
     *    - Atomically increments `total_collected_amount`.
     *    - Checks if total expected amount is reached, triggering `processPayout()` automatically.
     * 5. Dispatches `PaymentReceivedNotification` to the contributing member.
     *
     * @param  TontineRound  $round  Target rotation round.
     * @param  string  $memberPhone  Contributing member phone identifier.
     * @param  float  $amount  Payment amount in FCFA.
     * @param  string  $provider  Mobile Money provider ('wave', 'orange_money', 'mtn_momo').
     * @param  string  $transactionReference  Unique provider transaction reference string.
     * @param  Carbon|null  $paymentTimestamp  Precise operation timestamp (defaults to current time).
     * @return Contribution The recorded contribution instance.
     *
     * @throws Exception If round is inactive, member does not belong, or reference is duplicate.
     */
    public function recordContribution(
        TontineRound $round,
        string $memberPhone,
        float $amount,
        string $provider,
        string $transactionReference,
        ?Carbon $paymentTimestamp = null
    ): Contribution {
        if ($round->status !== 'active') {
            throw new Exception("Les cotisations ne sont ouvertes que pour le tour actif.");
        }

        $tontine = $round->tontine;

        // Verify member belongs to tontine
        if (!$tontine->members()->where('member_phone', $memberPhone)->exists()) {
            throw new Exception("Ce membre n'appartient pas à ce groupe de tontine.");
        }

        // Verify transaction reference uniqueness
        if (Contribution::where('transaction_reference', $transactionReference)->exists()) {
            throw new Exception("Cette référence de transaction a déjà été enregistrée.");
        }

        // Check if member already contributed for this round
        if ($round->contributions()->where('member_phone', $memberPhone)->exists()) {
            throw new Exception("Ce membre a déjà cotisé pour ce tour.");
        }

        $timestamp = $paymentTimestamp ?? now();

        // Determine punctuality status strictly comparing timestamps
        $punctualityStatus = $timestamp->lte($round->due_date) ? 'on_time' : 'late';

        $contribution = DB::transaction(function () use ($round, $memberPhone, $amount, $provider, $transactionReference, $timestamp, $punctualityStatus) {
            $contribution = Contribution::create([
                'tontine_round_id' => $round->id,
                'member_phone' => $memberPhone,
                'amount' => $amount,
                'payment_provider' => $provider,
                'transaction_reference' => $transactionReference,
                'payment_timestamp' => $timestamp,
                'punctuality_status' => $punctualityStatus,
                'payment_status' => 'successful',
            ]);

            // Increment collected amount atomically
            $round->increment('total_collected_amount', $amount);
            $round->refresh();

            // Check if round expected total amount is reached
            if ($round->isFullyCollected() || $round->contributions()->count() >= $round->tontine->max_members) {
                $this->processPayout($round);
            }

            return $contribution;
        });

        // Send payment received notification
        try {
            Notification::send(new UserNotifiable($memberPhone), new PaymentReceivedNotification($contribution, $tontine->name, $round->round_number));
        } catch (Exception $e) {
            Log::error("Failed sending PaymentReceivedNotification: " . $e->getMessage());
        }

        return $contribution;
    }

    /**
     * Process Mobile Money Payout (Remise de la cagnotte) to the round beneficiary.
     *
     * Algorithmic Flow:
     * 1. Simulates provider API transfer to `round->beneficiary_phone`.
     * 2. Updates round status: `payout_status = 'paid'`, `payout_at = now()`, `status = 'completed'`.
     * 3. Dispatches `PayoutExecutedNotification` to beneficiary.
     * 4. Activates next round (`round_number + 1`) or marks overall tontine `status = 'completed'` if final round.
     *
     * @param  TontineRound  $round  The fully collected round to pay out.
     * @return void
     */
    public function processPayout(TontineRound $round): void
    {
        $tontine = $round->tontine;

        // Simulate external Mobile Money provider API call (Wave / Orange Money / MTN)
        Log::info("Transferring payout of {$round->total_collected_amount} to beneficiary {$round->beneficiary_phone} for round #{$round->round_number} of Tontine #{$round->tontine_id}");

        $round->update([
            'payout_status' => 'paid',
            'payout_at' => now(),
            'status' => 'completed',
        ]);

        // Send payout notification to beneficiary
        try {
            Notification::send(new UserNotifiable($round->beneficiary_phone), new PayoutExecutedNotification($round, $tontine->name));
        } catch (Exception $e) {
            Log::error("Failed sending PayoutExecutedNotification: " . $e->getMessage());
        }

        // Activate next round if available
        $nextRound = TontineRound::where('tontine_id', $round->tontine_id)
            ->where('round_number', $round->round_number + 1)
            ->first();

        if ($nextRound) {
            $nextRound->update(['status' => 'active']);
            $this->notifyMembersRoundStarted($tontine, $nextRound);
        } else {
            // All rounds complete -> mark tontine complete
            $tontine->update(['status' => 'completed']);
        }
    }

    /**
     * Helper to notify all tontine members when a round starts.
     *
     * @param  Tontine  $tontine  Parent tontine group.
     * @param  TontineRound  $round  Newly activated round.
     * @return void
     */
    protected function notifyMembersRoundStarted(Tontine $tontine, TontineRound $round): void
    {
        try {
            foreach ($tontine->members as $member) {
                Notification::send(new UserNotifiable($member->member_phone), new RoundStartedNotification($round, $tontine->name));
            }
        } catch (Exception $e) {
            Log::error("Failed sending RoundStartedNotification: " . $e->getMessage());
        }
    }
}
