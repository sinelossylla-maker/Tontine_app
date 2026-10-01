<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordContributionRequest;
use App\Models\Tontine;
use App\Models\TontineRound;
use App\Services\TontinePaymentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller PaymentController
 *
 * Exposes API endpoints for starting tontine rotation cycles, listing rounds,
 * processing Mobile Money contributions, and retrieving round contribution histories.
 *
 * @package App\Http\Controllers
 */
class PaymentController extends Controller
{
    /**
     * @var TontinePaymentService Financial logic service instance.
     */
    protected TontinePaymentService $paymentService;

    /**
     * PaymentController constructor.
     *
     * @param  TontinePaymentService  $paymentService  Financial engine service.
     */
    public function __construct(TontinePaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Start a tontine group and generate its rotation rounds schedule.
     *
     * @param  int  $tontineId  ID of the target tontine.
     * @return JsonResponse Returns 200 OK with started tontine data, or 400/404 error response.
     */
    public function start(int $tontineId): JsonResponse
    {
        $tontine = Tontine::with('members')->find($tontineId);

        if (!$tontine) {
            return response()->json([
                'success' => false,
                'message' => 'Tontine introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $startedTontine = $this->paymentService->startTontine($tontine);

            return response()->json([
                'success' => true,
                'message' => 'Tontine démarrée avec succès. Le calendrier des tours a été généré.',
                'data' => $startedTontine,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get list of rotation rounds for a specific tontine.
     *
     * @param  int  $tontineId  ID of the target tontine.
     * @return JsonResponse Returns 200 OK with ordered array of rounds.
     */
    public function rounds(int $tontineId): JsonResponse
    {
        $tontine = Tontine::find($tontineId);

        if (!$tontine) {
            return response()->json([
                'success' => false,
                'message' => 'Tontine introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $rounds = $tontine->rounds()->orderBy('round_number', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $rounds,
        ], Response::HTTP_OK);
    }

    /**
     * Record a Mobile Money contribution for a specific active round.
     *
     * Evaluates punctuality (on_time vs late) and triggers automatic payout if full amount is collected.
     *
     * @param  RecordContributionRequest  $request  Form request with payment_provider, amount, transaction_reference.
     * @param  int  $roundId  ID of the active rotation round.
     * @return JsonResponse Returns 201 Created with contribution details, or 400/404 error.
     */
    public function pay(RecordContributionRequest $request, int $roundId): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');
        $round = TontineRound::with('tontine')->find($roundId);

        if (!$round) {
            return response()->json([
                'success' => false,
                'message' => 'Tour de tontine introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $paymentTimestamp = $request->filled('payment_timestamp')
            ? Carbon::parse($request->payment_timestamp)
            : now();

        try {
            $contribution = $this->paymentService->recordContribution(
                $round,
                $userPhone,
                (float) $request->amount,
                $request->payment_provider,
                $request->transaction_reference,
                $paymentTimestamp
            );

            return response()->json([
                'success' => true,
                'message' => 'Versement enregistré avec succès.',
                'data' => $contribution->load('round'),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * List all contributions recorded for a specific tontine round.
     *
     * @param  int  $roundId  ID of the rotation round.
     * @return JsonResponse Returns 200 OK with array of contributions.
     */
    public function contributions(int $roundId): JsonResponse
    {
        $round = TontineRound::find($roundId);

        if (!$round) {
            return response()->json([
                'success' => false,
                'message' => 'Tour de tontine introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $contributions = $round->contributions()->orderBy('payment_timestamp', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $contributions,
        ], Response::HTTP_OK);
    }
}
