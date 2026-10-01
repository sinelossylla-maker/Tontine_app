<?php

namespace Database\Seeders;

use App\Models\Contribution;
use App\Models\NotificationPreference;
use App\Models\Tontine;
use App\Models\TontineMember;
use App\Models\TontineRound;
use App\Models\UserNotifiable;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PayoutExecutedNotification;
use App\Notifications\RoundStartedNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class TestTontineSeeder extends Seeder
{
    /**
     * Run the database seeds for real Tontine SHEKELE testing dataset.
     */
    public function run(): void
    {
        $phone1 = '+2250701020304';
        $phone2 = '+2250505060708';
        $phone3 = '+2250102030405';
        $phone4 = '+2250788990011';

        // Seed Notification Preferences
        foreach ([$phone1, $phone2, $phone3, $phone4] as $phone) {
            NotificationPreference::firstOrCreate(
                ['user_phone' => $phone],
                [
                    'sms_enabled' => true,
                    'push_enabled' => true,
                    'payment_reminders' => true,
                    'payout_alerts' => true,
                ]
            );
        }

        // =========================================================================
        // TONTINE A : En attente de membres (Ouverte aux adhésions)
        // =========================================================================
        $tontineA = Tontine::create([
            'creator_phone' => $phone1,
            'name' => 'Tontine Abidjan Espoir',
            'contribution_amount' => 25000,
            'frequency' => 'monthly',
            'start_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'max_members' => 5,
            'status' => 'pending',
        ]);

        TontineMember::create([
            'tontine_id' => $tontineA->id,
            'member_phone' => $phone1,
            'turn_order' => 1,
            'status' => 'active',
            'joined_at' => Carbon::now()->subDays(2),
        ]);

        // =========================================================================
        // TONTINE B : Pleine et Active (Tour 1 en cours avec versement à temps)
        // =========================================================================
        $tontineB = Tontine::create([
            'creator_phone' => $phone1,
            'name' => 'Tontine Cocody Business',
            'contribution_amount' => 50000,
            'frequency' => 'weekly',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'max_members' => 3,
            'status' => 'active',
        ]);

        TontineMember::create(['tontine_id' => $tontineB->id, 'member_phone' => $phone1, 'turn_order' => 1, 'status' => 'active', 'joined_at' => Carbon::now()->subDays(3)]);
        TontineMember::create(['tontine_id' => $tontineB->id, 'member_phone' => $phone2, 'turn_order' => 2, 'status' => 'active', 'joined_at' => Carbon::now()->subDays(2)]);
        TontineMember::create(['tontine_id' => $tontineB->id, 'member_phone' => $phone3, 'turn_order' => 3, 'status' => 'active', 'joined_at' => Carbon::now()->subDay()]);

        $bDue1 = Carbon::now()->addDays(5)->endOfDay();
        $bDue2 = Carbon::now()->addWeeks(1)->addDays(5)->endOfDay();
        $bDue3 = Carbon::now()->addWeeks(2)->addDays(5)->endOfDay();

        $roundB1 = TontineRound::create(['tontine_id' => $tontineB->id, 'round_number' => 1, 'beneficiary_phone' => $phone1, 'due_date' => $bDue1, 'total_expected_amount' => 150000, 'total_collected_amount' => 50000, 'status' => 'active', 'payout_status' => 'pending']);
        TontineRound::create(['tontine_id' => $tontineB->id, 'round_number' => 2, 'beneficiary_phone' => $phone2, 'due_date' => $bDue2, 'total_expected_amount' => 150000, 'total_collected_amount' => 0, 'status' => 'pending', 'payout_status' => 'pending']);
        TontineRound::create(['tontine_id' => $tontineB->id, 'round_number' => 3, 'beneficiary_phone' => $phone3, 'due_date' => $bDue3, 'total_expected_amount' => 150000, 'total_collected_amount' => 0, 'status' => 'pending', 'payout_status' => 'pending']);

        $contribB1 = Contribution::create([
            'tontine_round_id' => $roundB1->id,
            'member_phone' => $phone1,
            'amount' => 50000,
            'payment_provider' => 'wave',
            'transaction_reference' => 'SEED_TXN_B1_001',
            'payment_timestamp' => Carbon::now()->subHours(4),
            'punctuality_status' => 'on_time',
            'payment_status' => 'successful',
        ]);

        Notification::send(new UserNotifiable($phone1), new PaymentReceivedNotification($contribB1, $tontineB->name, 1));
        Notification::send(new UserNotifiable($phone1), new RoundStartedNotification($roundB1, $tontineB->name));

        // =========================================================================
        // TONTINE C : Tour en cours avec versement EN RETARD constaté
        // =========================================================================
        $tontineC = Tontine::create([
            'creator_phone' => $phone2,
            'name' => 'Tontine Marcory Solidarité',
            'contribution_amount' => 100000,
            'frequency' => 'monthly',
            'start_date' => Carbon::now()->subMonth()->format('Y-m-d'),
            'max_members' => 2,
            'status' => 'active',
        ]);

        TontineMember::create(['tontine_id' => $tontineC->id, 'member_phone' => $phone2, 'turn_order' => 1, 'status' => 'active', 'joined_at' => Carbon::now()->subMonth()]);
        TontineMember::create(['tontine_id' => $tontineC->id, 'member_phone' => $phone4, 'turn_order' => 2, 'status' => 'active', 'joined_at' => Carbon::now()->subMonth()]);

        $cDue1 = Carbon::now()->subDays(2)->endOfDay();
        $cDue2 = Carbon::now()->addMonth()->endOfDay();

        $roundC1 = TontineRound::create(['tontine_id' => $tontineC->id, 'round_number' => 1, 'beneficiary_phone' => $phone2, 'due_date' => $cDue1, 'total_expected_amount' => 200000, 'total_collected_amount' => 100000, 'status' => 'active', 'payout_status' => 'pending']);
        TontineRound::create(['tontine_id' => $tontineC->id, 'round_number' => 2, 'beneficiary_phone' => $phone4, 'due_date' => $cDue2, 'total_expected_amount' => 200000, 'total_collected_amount' => 0, 'status' => 'pending', 'payout_status' => 'pending']);

        $contribC1 = Contribution::create([
            'tontine_round_id' => $roundC1->id,
            'member_phone' => $phone2,
            'amount' => 100000,
            'payment_provider' => 'orange_money',
            'transaction_reference' => 'SEED_TXN_C1_001',
            'payment_timestamp' => Carbon::now()->subHours(2), // After due_date => late
            'punctuality_status' => 'late',
            'payment_status' => 'successful',
        ]);

        Notification::send(new UserNotifiable($phone2), new PaymentReceivedNotification($contribC1, $tontineC->name, 1));

        // =========================================================================
        // TONTINE D : Cycle entièrement complété avec l'ensemble des payouts exécutés
        // =========================================================================
        $tontineD = Tontine::create([
            'creator_phone' => $phone3,
            'name' => 'Tontine Plateau Succès',
            'contribution_amount' => 15000,
            'frequency' => 'weekly',
            'start_date' => Carbon::now()->subWeeks(3)->format('Y-m-d'),
            'max_members' => 2,
            'status' => 'completed',
        ]);

        TontineMember::create(['tontine_id' => $tontineD->id, 'member_phone' => $phone3, 'turn_order' => 1, 'status' => 'active', 'joined_at' => Carbon::now()->subWeeks(3)]);
        TontineMember::create(['tontine_id' => $tontineD->id, 'member_phone' => $phone4, 'turn_order' => 2, 'status' => 'active', 'joined_at' => Carbon::now()->subWeeks(3)]);

        $dDue1 = Carbon::now()->subWeeks(2)->endOfDay();
        $dDue2 = Carbon::now()->subWeek()->endOfDay();

        $roundD1 = TontineRound::create(['tontine_id' => $tontineD->id, 'round_number' => 1, 'beneficiary_phone' => $phone3, 'due_date' => $dDue1, 'total_expected_amount' => 30000, 'total_collected_amount' => 30000, 'status' => 'completed', 'payout_status' => 'paid', 'payout_at' => Carbon::now()->subWeeks(2)]);
        $roundD2 = TontineRound::create(['tontine_id' => $tontineD->id, 'round_number' => 2, 'beneficiary_phone' => $phone4, 'due_date' => $dDue2, 'total_expected_amount' => 30000, 'total_collected_amount' => 30000, 'status' => 'completed', 'payout_status' => 'paid', 'payout_at' => Carbon::now()->subWeek()]);

        Contribution::create(['tontine_round_id' => $roundD1->id, 'member_phone' => $phone3, 'amount' => 15000, 'payment_provider' => 'mtn_momo', 'transaction_reference' => 'SEED_TXN_D1_001', 'payment_timestamp' => Carbon::now()->subWeeks(2)->subHours(3), 'punctuality_status' => 'on_time', 'payment_status' => 'successful']);
        Contribution::create(['tontine_round_id' => $roundD1->id, 'member_phone' => $phone4, 'amount' => 15000, 'payment_provider' => 'wave', 'transaction_reference' => 'SEED_TXN_D1_002', 'payment_timestamp' => Carbon::now()->subWeeks(2)->subHours(1), 'punctuality_status' => 'on_time', 'payment_status' => 'successful']);

        Contribution::create(['tontine_round_id' => $roundD2->id, 'member_phone' => $phone3, 'amount' => 15000, 'payment_provider' => 'wave', 'transaction_reference' => 'SEED_TXN_D2_001', 'payment_timestamp' => Carbon::now()->subWeek()->subHours(2), 'punctuality_status' => 'on_time', 'payment_status' => 'successful']);
        Contribution::create(['tontine_round_id' => $roundD2->id, 'member_phone' => $phone4, 'amount' => 15000, 'payment_provider' => 'orange_money', 'transaction_reference' => 'SEED_TXN_D2_002', 'payment_timestamp' => Carbon::now()->subWeek()->subHours(1), 'punctuality_status' => 'on_time', 'payment_status' => 'successful']);

        Notification::send(new UserNotifiable($phone3), new PayoutExecutedNotification($roundD1, $tontineD->name));
        Notification::send(new UserNotifiable($phone4), new PayoutExecutedNotification($roundD2, $tontineD->name));
    }
}
