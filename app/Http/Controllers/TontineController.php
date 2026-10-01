<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTontineRequest;
use App\Http\Requests\JoinTontineRequest;
use App\Models\Tontine;
use App\Models\TontineMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller TontineController
 *
 * Handles HTTP requests for creating tontine groups, managing member adhesions,
 * fetching details with ordered members, and retrieving user tontine groups.
 *
 * @package App\Http\Controllers
 */
class TontineController extends Controller
{
    /**
     * Store a newly created tontine group.
     *
     * Registers the authenticated JWT user as the creator and first member (turn_order = 1).
     * Auto-generates an 8-character uppercase unique invitation code upon model creation.
     *
     * @param  CreateTontineRequest  $request  Validated form request instance.
     * @return JsonResponse Returns 201 Created with created tontine and member details.
     */
    public function store(CreateTontineRequest $request): JsonResponse
    {
        $creatorPhone = $request->attributes->get('auth_user_phone');

        $tontine = DB::transaction(function () use ($request, $creatorPhone) {
            $tontine = Tontine::create([
                'creator_phone' => $creatorPhone,
                'name' => $request->name,
                'contribution_amount' => $request->contribution_amount,
                'frequency' => $request->frequency,
                'start_date' => $request->start_date,
                'max_members' => $request->max_members,
                'status' => 'pending',
            ]);

            // Register creator as the first member with turn_order = 1
            TontineMember::create([
                'tontine_id' => $tontine->id,
                'member_phone' => $creatorPhone,
                'turn_order' => 1,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $tontine;
        });

        return response()->json([
            'success' => true,
            'message' => 'Groupe de tontine créé avec succès.',
            'data' => $tontine->load(['members' => fn($q) => $q->orderBy('turn_order', 'asc')]),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display details of a specific tontine group with ordered members list.
     *
     * @param  int  $id  Primary key ID of the tontine.
     * @return JsonResponse Returns 200 OK with tontine data, or 404 Not Found.
     */
    public function show(int $id): JsonResponse
    {
        $tontine = Tontine::with(['members' => fn($q) => $q->orderBy('turn_order', 'asc')])->find($id);

        if (!$tontine) {
            return response()->json([
                'success' => false,
                'message' => 'Groupe de tontine introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $tontine,
        ], Response::HTTP_OK);
    }

    /**
     * Join a tontine group using an invitation code.
     *
     * Verifies that:
     * 1. The invitation code corresponds to an existing tontine.
     * 2. The user is not already a member (HTTP 422).
     * 3. The tontine has not reached its max_members capacity via isFull() (HTTP 422).
     * Automatically assigns the next available incremental turn_order (max + 1).
     *
     * @param  JoinTontineRequest  $request  Validated form request containing invitation_code.
     * @return JsonResponse Returns 201 Created with joined member information, or 422/404 error.
     */
    public function join(JoinTontineRequest $request): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');
        $invitationCode = strtoupper(trim($request->invitation_code));

        $tontine = Tontine::where('invitation_code', $invitationCode)->first();

        if (!$tontine) {
            return response()->json([
                'success' => false,
                'message' => 'Tontine introuvable avec ce code d\'invitation.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if user is already a member
        if ($tontine->members()->where('member_phone', $userPhone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous êtes déjà membre de cette tontine.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if tontine is full
        if ($tontine->isFull()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette tontine a déjà atteint son nombre maximum de membres.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $member = DB::transaction(function () use ($tontine, $userPhone) {
            $nextTurn = ($tontine->members()->max('turn_order') ?? 0) + 1;

            return TontineMember::create([
                'tontine_id' => $tontine->id,
                'member_phone' => $userPhone,
                'turn_order' => $nextTurn,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Vous avez rejoint la tontine avec succès.',
            'data' => [
                'tontine_id' => $tontine->id,
                'tontine_name' => $tontine->name,
                'member' => $member,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * List all tontine groups the authenticated user belongs to.
     *
     * @param  Request  $request  HTTP Request object carrying authenticated user attributes.
     * @return JsonResponse Returns 200 OK with array of tontines.
     */
    public function myTontines(Request $request): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');

        $tontines = Tontine::whereHas('members', function ($q) use ($userPhone) {
            $q->where('member_phone', $userPhone);
        })->with(['members' => fn($q) => $q->orderBy('turn_order', 'asc')])->get();

        return response()->json([
            'success' => true,
            'data' => $tontines,
        ], Response::HTTP_OK);
    }
}
