<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TontineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Tontine Shekele (Part 2, Part 2.2 & Part 2.3)
|--------------------------------------------------------------------------
|
| All endpoints are protected by the shared Django JWT authentication middleware.
|
*/

Route::middleware('jwt.django')->group(function () {
    // Tontine Group & Membership Management
    Route::post('/tontines', [TontineController::class, 'store']);
    Route::get('/tontines/join', function() {
        return response()->json(['message' => 'Use POST to join a tontine.'], 405);
    });
    Route::post('/tontines/join', [TontineController::class, 'join']);
    Route::get('/my-tontines', [TontineController::class, 'myTontines']);
    Route::get('/tontines/{id}', [TontineController::class, 'show'])->where('id', '[0-9]+');

    // Financial Engine, Rounds & Mobile Money Payments
    Route::post('/tontines/{tontineId}/start', [PaymentController::class, 'start'])->where('tontineId', '[0-9]+');
    Route::get('/tontines/{tontineId}/rounds', [PaymentController::class, 'rounds'])->where('tontineId', '[0-9]+');
    Route::post('/rounds/{roundId}/pay', [PaymentController::class, 'pay'])->where('roundId', '[0-9]+');
    Route::get('/rounds/{roundId}/contributions', [PaymentController::class, 'contributions'])->where('roundId', '[0-9]+');

    // User Notifications & Preferences
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications/preferences', [NotificationController::class, 'getPreferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
});
