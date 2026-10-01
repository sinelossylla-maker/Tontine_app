<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller NotificationController
 *
 * Exposes API endpoints for retrieving user notifications from database channel,
 * updating read status (single read / bulk read-all), and managing user notification preferences.
 *
 * @package App\Http\Controllers
 */
class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated JWT user.
     *
     * @param  Request  $request  HTTP request carrying authenticated user phone attribute.
     * @return JsonResponse Returns 200 OK with ordered notifications list.
     */
    public function index(Request $request): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');

        $notifications = DB::table('notifications')
            ->where('notifiable_id', $userPhone)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => json_decode($notification->data, true),
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ], Response::HTTP_OK);
    }

    /**
     * Mark a single notification as read by UUID.
     *
     * @param  string  $id  Notification UUID string.
     * @param  Request  $request  HTTP request.
     * @return JsonResponse Returns 200 OK or 404 Not Found.
     */
    public function markAsRead(string $id, Request $request): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');

        $affected = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', $userPhone)
            ->update(['read_at' => now(), 'updated_at' => now()]);

        if (!$affected) {
            return response()->json([
                'success' => false,
                'message' => 'Notification introuvable ou déjà lue.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue.',
        ], Response::HTTP_OK);
    }

    /**
     * Mark all unread notifications as read for the authenticated user.
     *
     * @param  Request  $request  HTTP request.
     * @return JsonResponse Returns 200 OK with updated notifications count.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');

        $affectedCount = DB::table('notifications')
            ->where('notifiable_id', $userPhone)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Toutes les notifications ont été marquées comme lues ({$affectedCount} notifiées).",
            'updated_count' => $affectedCount,
        ], Response::HTTP_OK);
    }

    /**
     * Retrieve notification preferences for the authenticated user (creates defaults if missing).
     *
     * @param  Request  $request  HTTP request.
     * @return JsonResponse Returns 200 OK with NotificationPreference object.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');

        $preferences = NotificationPreference::firstOrCreate(
            ['user_phone' => $userPhone],
            [
                'sms_enabled' => true,
                'push_enabled' => true,
                'payment_reminders' => true,
                'payout_alerts' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ], Response::HTTP_OK);
    }

    /**
     * Update notification preferences for the authenticated user.
     *
     * @param  UpdateNotificationPreferencesRequest  $request  Validated request instance.
     * @return JsonResponse Returns 200 OK with updated preferences.
     */
    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $userPhone = $request->attributes->get('auth_user_phone');

        $preferences = NotificationPreference::firstOrCreate(
            ['user_phone' => $userPhone]
        );

        $preferences->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Préférences de notifications mises à jour avec succès.',
            'data' => $preferences,
        ], Response::HTTP_OK);
    }
}
