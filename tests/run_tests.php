<?php

use App\Models\Contribution;
use App\Models\NotificationPreference;
use App\Models\Tontine;
use App\Models\TontineMember;
use App\Models\TontineRound;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;
use Carbon\Carbon;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Fresh database and run full seeder
\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

echo "=================================================================================\n";
echo " TONTINE SHEKELE PART 2 - EXHAUSTIVE QA AUTOMATION & AUDIT TEST SUITE\n";
echo "=================================================================================\n\n";

function makeJwtToken(array $extraClaims = []): string
{
    $payload = array_merge([
        'user_id' => 1,
        'phone' => '+2250701020304',
        'is_kyc_verified' => true,
        'exp' => time() + 3600,
        'iat' => time(),
    ], $extraClaims);

    return JWT::encode($payload, config('jwt.secret'), 'HS256');
}

function callApi($kernel, string $method, string $uri, array $data = [], array $headers = []): array
{
    $request = Request::create($uri, $method, $data, [], [], [], json_encode($data));
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('Content-Type', 'application/json');

    foreach ($headers as $key => $value) {
        $request->headers->set($key, $value);
    }

    $response = $kernel->handle($request);
    $content = json_decode($response->getContent(), true) ?? [];

    return [
        'status' => $response->getStatusCode(),
        'json' => $content,
    ];
}

$assertCount = 0;
$passCount = 0;

function assertCondition(bool $condition, string $description) {
    global $assertCount, $passCount;
    $assertCount++;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
    }
}

// -------------------------------------------------------------------------
// SECTION 1: JWT Authentication & KYC Edge Cases
// -------------------------------------------------------------------------
echo "--- Section 1: Security & JWT Validation ---\n";

// Test 1: Missing JWT token returns 401
$res1 = callApi($kernel, 'GET', '/api/my-tontines');
assertCondition($res1['status'] === 401 && $res1['json']['success'] === false, 'Test 1: Missing Bearer token is rejected with 401 Unauthorized');

// Test 2: Invalid JWT signature returns 401
$badToken = JWT::encode(['phone' => '+2250701020304', 'is_kyc_verified' => true, 'exp' => time() + 3600], 'wrong-secret-key-with-at-least-32-bytes-length', 'HS256');
$res2 = callApi($kernel, 'GET', '/api/my-tontines', [], ['Authorization' => 'Bearer ' . $badToken]);
assertCondition($res2['status'] === 401 && $res2['json']['success'] === false, 'Test 2: Invalid JWT signature is rejected with 401 Unauthorized');

// Test 3: Unverified KYC user returns 403
$tokenUnverified = makeJwtToken(['is_kyc_verified' => false]);
$res3 = callApi($kernel, 'GET', '/api/my-tontines', [], ['Authorization' => 'Bearer ' . $tokenUnverified]);
assertCondition($res3['status'] === 403 && $res3['json']['success'] === false, 'Test 3: KYC unverified user is forbidden with 403');

// -------------------------------------------------------------------------
// SECTION 2: Group Creation & Membership Adhesion Edge Cases
// -------------------------------------------------------------------------
echo "\n--- Section 2: Group Creation & Membership Edge Cases ---\n";

$user1Token = makeJwtToken(['phone' => '+2250701020304']);
$user2Token = makeJwtToken(['phone' => '+2250505060708']);
$user3Token = makeJwtToken(['phone' => '+2250102030405']);
$user4Token = makeJwtToken(['phone' => '+2250788990011']);

// Test 4: Create a new Tontine
$res4 = callApi($kernel, 'POST', '/api/tontines', [
    'name' => 'Tontine QA Automation',
    'contribution_amount' => 30000,
    'frequency' => 'weekly',
    'start_date' => date('Y-m-d'),
    'max_members' => 2,
], ['Authorization' => 'Bearer ' . $user1Token]);

assertCondition($res4['status'] === 201 && $res4['json']['success'] === true, 'Test 4: Creating a tontine group returns 201 Created');
$qaTontineId = $res4['json']['data']['id'];
$qaInvitationCode = $res4['json']['data']['invitation_code'];

// Test 5: Rejection when joining invalid invitation code -> 404
$res5 = callApi($kernel, 'POST', '/api/tontines/join', [
    'invitation_code' => 'INVALID8',
], ['Authorization' => 'Bearer ' . $user2Token]);
assertCondition($res5['status'] === 404 && $res5['json']['success'] === false, 'Test 5: Rejection on non-existent invitation code returns 404');

// Test 6: Member 2 joins tontine -> 201 (turn_order = 2)
$res6 = callApi($kernel, 'POST', '/api/tontines/join', [
    'invitation_code' => $qaInvitationCode,
], ['Authorization' => 'Bearer ' . $user2Token]);
assertCondition($res6['status'] === 201 && $res6['json']['data']['member']['turn_order'] === 2, 'Test 6: Member 2 joins tontine and is assigned turn_order = 2');

// Test 7: Rejection when joining full tontine -> 422
$res7 = callApi($kernel, 'POST', '/api/tontines/join', [
    'invitation_code' => $qaInvitationCode,
], ['Authorization' => 'Bearer ' . $user3Token]);
assertCondition($res7['status'] === 422 && $res7['json']['success'] === false, 'Test 7: Rejection when joining full tontine returns 422');

// Test 8: Rejection when member joins the same tontine twice -> 422
$res8 = callApi($kernel, 'POST', '/api/tontines/join', [
    'invitation_code' => $qaInvitationCode,
], ['Authorization' => 'Bearer ' . $user2Token]);
assertCondition($res8['status'] === 422 && $res8['json']['success'] === false, 'Test 8: Rejection when member attempts duplicate join returns 422');

// -------------------------------------------------------------------------
// SECTION 3: Starting Tontine & Rotation Rounds Schedule
// -------------------------------------------------------------------------
echo "\n--- Section 3: Financial Engine - Starting Tontine & Rounds ---\n";

// Test 9: Start Tontine -> 200
$res9 = callApi($kernel, 'POST', "/api/tontines/{$qaTontineId}/start", [], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res9['status'] === 200 && $res9['json']['success'] === true, 'Test 9: Starting tontine generates schedule and returns 200 OK');

$rounds = $res9['json']['data']['rounds'] ?? [];
assertCondition(count($rounds) === 2, 'Test 9b: Generated exactly 2 rotation rounds matching max_members = 2');
$round1Id = $rounds[0]['id'];
$round2Id = $rounds[1]['id'];

// Test 10: Rejection when starting already active tontine -> 400
$res10 = callApi($kernel, 'POST', "/api/tontines/{$qaTontineId}/start", [], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res10['status'] === 400 && $res10['json']['success'] === false, 'Test 10: Rejection when starting already active tontine returns 400 Bad Request');

// -------------------------------------------------------------------------
// SECTION 4: Contributions, Punctuality & Edge Cases
// -------------------------------------------------------------------------
echo "\n--- Section 4: Mobile Money Contributions & Punctuality ---\n";

// Test 11: On-time contribution (payment_timestamp <= due_date)
$res11 = callApi($kernel, 'POST', "/api/rounds/{$round1Id}/pay", [
    'payment_provider' => 'wave',
    'amount' => 30000,
    'transaction_reference' => 'QA_TXN_001',
    'payment_timestamp' => date('Y-m-d H:i:s'),
], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res11['status'] === 201 && $res11['json']['data']['punctuality_status'] === 'on_time', 'Test 11: Contribution before due date receives punctuality_status = on_time');

// Test 12: Rejection on duplicate contribution by same member for same round -> 400
$res12 = callApi($kernel, 'POST', "/api/rounds/{$round1Id}/pay", [
    'payment_provider' => 'wave',
    'amount' => 30000,
    'transaction_reference' => 'QA_TXN_DUP_001',
], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res12['status'] === 400 && $res12['json']['success'] === false, 'Test 12: Rejection on duplicate contribution for same round returns 400');

// Test 13: Rejection on duplicate transaction reference -> 422
$res13 = callApi($kernel, 'POST', "/api/rounds/{$round1Id}/pay", [
    'payment_provider' => 'orange_money',
    'amount' => 30000,
    'transaction_reference' => 'QA_TXN_001',
], ['Authorization' => 'Bearer ' . $user2Token]);
assertCondition($res13['status'] === 422 && !empty($res13['json']['errors']), 'Test 13: Rejection on duplicate transaction reference returns 422');

// Test 14: Rejection on contribution to non-active round (Round 2 is pending) -> 400
$res14 = callApi($kernel, 'POST', "/api/rounds/{$round2Id}/pay", [
    'payment_provider' => 'mtn_momo',
    'amount' => 30000,
    'transaction_reference' => 'QA_TXN_PENDING_ROUND',
], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res14['status'] === 400 && $res14['json']['success'] === false, 'Test 14: Rejection when paying on pending/inactive round returns 400');

// Test 15: Late contribution & Automatic Payout execution
$lateTimestamp = Carbon::now()->addDays(10)->format('Y-m-d H:i:s');
$res15 = callApi($kernel, 'POST', "/api/rounds/{$round1Id}/pay", [
    'payment_provider' => 'orange_money',
    'amount' => 30000,
    'transaction_reference' => 'QA_TXN_002',
    'payment_timestamp' => $lateTimestamp,
], ['Authorization' => 'Bearer ' . $user2Token]);
assertCondition($res15['status'] === 201 && $res15['json']['data']['punctuality_status'] === 'late', 'Test 15: Contribution after due date receives punctuality_status = late');

// Test 16: Automatic Payout Triggering & Round 2 Activation
$round1Fresh = TontineRound::find($round1Id);
assertCondition($round1Fresh->status === 'completed' && $round1Fresh->payout_status === 'paid', 'Test 16a: Full collection triggers payout (payout_status = paid, status = completed)');

$round2Fresh = TontineRound::find($round2Id);
assertCondition($round2Fresh->status === 'active', 'Test 16b: Next round (Round 2) is automatically activated');

// Test 17: Complete Round 2 -> Tontine is completed
callApi($kernel, 'POST', "/api/rounds/{$round2Id}/pay", ['payment_provider' => 'wave', 'amount' => 30000, 'transaction_reference' => 'QA_TXN_003'], ['Authorization' => 'Bearer ' . $user1Token]);
callApi($kernel, 'POST', "/api/rounds/{$round2Id}/pay", ['payment_provider' => 'mtn_momo', 'amount' => 30000, 'transaction_reference' => 'QA_TXN_004'], ['Authorization' => 'Bearer ' . $user2Token]);

$qaTontineFresh = Tontine::find($qaTontineId);
assertCondition($qaTontineFresh->status === 'completed', 'Test 17: Finishing final round marks overall tontine status = completed');

// -------------------------------------------------------------------------
// SECTION 5: Notifications & User Preferences
// -------------------------------------------------------------------------
echo "\n--- Section 5: Notifications & User Preferences ---\n";

// Test 18: Retrieve user notifications list -> 200
$res18 = callApi($kernel, 'GET', '/api/notifications', [], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res18['status'] === 200 && count($res18['json']['data']) >= 2, 'Test 18: Retrieve notifications list returns 200 OK');

$firstNotifId = $res18['json']['data'][0]['id'];

// Test 19: Mark single notification read -> 200
$res19 = callApi($kernel, 'POST', "/api/notifications/{$firstNotifId}/read", [], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res19['status'] === 200 && $res19['json']['success'] === true, 'Test 19: Mark single notification as read returns 200 OK');

// Test 20: Mark all notifications read -> 200
$res20 = callApi($kernel, 'POST', '/api/notifications/read-all', [], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res20['status'] === 200 && $res20['json']['success'] === true, 'Test 20: Mark all notifications as read returns 200 OK');

// Test 21: Retrieve & Update notification preferences -> 200
$res21a = callApi($kernel, 'GET', '/api/notifications/preferences', [], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res21a['status'] === 200 && $res21a['json']['data']['sms_enabled'] === true, 'Test 21a: Retrieve notification preferences returns 200 OK');

$res21b = callApi($kernel, 'PUT', '/api/notifications/preferences', [
    'sms_enabled' => false,
    'payout_alerts' => false,
], ['Authorization' => 'Bearer ' . $user1Token]);
assertCondition($res21b['status'] === 200 && $res21b['json']['data']['sms_enabled'] === false, 'Test 21b: Update notification preferences returns 200 OK with updated values');

// -------------------------------------------------------------------------
// SECTION 6: Verification of Seeded Dataset
// -------------------------------------------------------------------------
echo "\n--- Section 6: Verification of Seeded Dataset ---\n";

$seededTontineA = Tontine::where('name', 'LIKE', 'Tontine Abidjan%')->first();
assertCondition($seededTontineA && $seededTontineA->status === 'pending', 'Test 22: Seeded Tontine A is in pending state');

$seededTontineB = Tontine::where('name', 'LIKE', 'Tontine Cocody%')->first();
assertCondition($seededTontineB && $seededTontineB->status === 'active' && $seededTontineB->members()->count() === 3, 'Test 23: Seeded Tontine B is active with 3 members');

$seededTontineC = Tontine::where('name', 'LIKE', 'Tontine Marcory%')->first();
$lateContribC = Contribution::where('transaction_reference', 'SEED_TXN_C1_001')->first();
assertCondition($seededTontineC && $lateContribC && $lateContribC->punctuality_status === 'late', 'Test 24: Seeded Tontine C contains late contribution');

$seededTontineD = Tontine::where('name', 'LIKE', 'Tontine Plateau%')->first();
assertCondition($seededTontineD && $seededTontineD->status === 'completed', 'Test 25: Seeded Tontine D is in completed state');

echo "\n=================================================================================\n";
echo " QA Execution Finished: {$passCount}/{$assertCount} assertions passed.\n";
if ($passCount === $assertCount) {
    echo " ALL TESTS PASSED SUCCESSFULLY! (0 FAILURES)\n";
    echo "=================================================================================\n";
    exit(0);
} else {
    echo " SOME TESTS FAILED!\n";
    echo "=================================================================================\n";
    exit(1);
}
