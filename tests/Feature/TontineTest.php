<?php

namespace Tests\Feature;

use App\Models\Tontine;
use App\Models\TontineMember;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TontineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to generate a signed JWT token matching Django payload.
     */
    protected function generateJwtToken(array $extraClaims = []): string
    {
        $payload = array_merge([
            'user_id' => 1,
            'phone' => '+221771112233',
            'is_kyc_verified' => true,
            'exp' => time() + 3600,
            'iat' => time(),
        ], $extraClaims);

        return JWT::encode($payload, config('jwt.secret'), 'HS256');
    }

    /**
     * Test missing authorization header is rejected with 401.
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->json('GET', '/api/my-tontines');
        $response->assertStatus(401)
                 ->assertJson(['success' => false]);
    }

    /**
     * Test unverified KYC user is forbidden with 403.
     */
    public function test_unverified_kyc_user_is_forbidden(): void
    {
        $token = $this->generateJwtToken(['is_kyc_verified' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->json('GET', '/api/my-tontines');

        $response->assertStatus(403)
                 ->assertJson(['success' => false]);
    }

    /**
     * Test creating a tontine successfully registers creator as turn 1.
     */
    public function test_create_tontine_successfully(): void
    {
        $token = $this->generateJwtToken(['phone' => '+221771112233']);

        $payload = [
            'name' => 'Tontine Famille Shekele',
            'contribution_amount' => 50000,
            'frequency' => 'monthly',
            'start_date' => now()->addDays(2)->format('Y-m-d'),
            'max_members' => 5,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->json('POST', '/api/tontines', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.name', 'Tontine Famille Shekele')
                 ->assertJsonPath('data.creator_phone', '+221771112233');

        $tontineId = $response->json('data.id');
        $invitationCode = $response->json('data.invitation_code');

        $this->assertEquals(8, strlen($invitationCode));
        $this->assertDatabaseHas('tontines', [
            'id' => $tontineId,
            'creator_phone' => '+221771112233',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('tontine_members', [
            'tontine_id' => $tontineId,
            'member_phone' => '+221771112233',
            'turn_order' => 1,
        ]);
    }

    /**
     * Test fetching tontine details by ID.
     */
    public function test_get_tontine_details(): void
    {
        $creatorToken = $this->generateJwtToken(['phone' => '+221771112233']);

        $tontine = Tontine::create([
            'creator_phone' => '+221771112233',
            'name' => 'Tontine Business',
            'contribution_amount' => 100000,
            'frequency' => 'weekly',
            'start_date' => now()->format('Y-m-d'),
            'max_members' => 10,
        ]);

        TontineMember::create([
            'tontine_id' => $tontine->id,
            'member_phone' => '+221771112233',
            'turn_order' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $creatorToken)
                         ->json('GET', '/api/tontines/' . $tontine->id);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.id', $tontine->id)
                 ->assertJsonPath('data.members.0.turn_order', 1);
    }

    /**
     * Test joining a tontine via invitation code automatically assigns next turn order.
     */
    public function test_join_tontine_successfully(): void
    {
        $creatorToken = $this->generateJwtToken(['phone' => '+221771112233']);
        $member2Token = $this->generateJwtToken(['phone' => '+221772223344']);

        $tontine = Tontine::create([
            'creator_phone' => '+221771112233',
            'name' => 'Tontine Epargne',
            'contribution_amount' => 25000,
            'frequency' => 'monthly',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'max_members' => 3,
        ]);

        TontineMember::create([
            'tontine_id' => $tontine->id,
            'member_phone' => '+221771112233',
            'turn_order' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $member2Token)
                         ->json('POST', '/api/tontines/join', [
                             'invitation_code' => $tontine->invitation_code,
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.member.turn_order', 2)
                 ->assertJsonPath('data.member.member_phone', '+221772223344');

        $this->assertDatabaseHas('tontine_members', [
            'tontine_id' => $tontine->id,
            'member_phone' => '+221772223344',
            'turn_order' => 2,
        ]);
    }

    /**
     * Test user cannot join the same tontine twice.
     */
    public function test_cannot_join_same_tontine_twice(): void
    {
        $creatorToken = $this->generateJwtToken(['phone' => '+221771112233']);

        $tontine = Tontine::create([
            'creator_phone' => '+221771112233',
            'name' => 'Tontine Solo',
            'contribution_amount' => 10000,
            'frequency' => 'weekly',
            'start_date' => now()->format('Y-m-d'),
            'max_members' => 5,
        ]);

        TontineMember::create([
            'tontine_id' => $tontine->id,
            'member_phone' => '+221771112233',
            'turn_order' => 1,
        ]);

        // Creator attempts to join their own tontine again
        $response = $this->withHeader('Authorization', 'Bearer ' . $creatorToken)
                         ->json('POST', '/api/tontines/join', [
                             'invitation_code' => $tontine->invitation_code,
                         ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    /**
     * Test cannot join full tontine.
     */
    public function test_cannot_join_full_tontine(): void
    {
        $token3 = $this->generateJwtToken(['phone' => '+221773334455']);

        $tontine = Tontine::create([
            'creator_phone' => '+221771112233',
            'name' => 'Tontine Duo Max',
            'contribution_amount' => 15000,
            'frequency' => 'monthly',
            'start_date' => now()->format('Y-m-d'),
            'max_members' => 2,
        ]);

        TontineMember::create(['tontine_id' => $tontine->id, 'member_phone' => '+221771112233', 'turn_order' => 1]);
        TontineMember::create(['tontine_id' => $tontine->id, 'member_phone' => '+221772223344', 'turn_order' => 2]);

        // Third member attempts to join full tontine
        $response = $this->withHeader('Authorization', 'Bearer ' . $token3)
                         ->json('POST', '/api/tontines/join', [
                             'invitation_code' => $tontine->invitation_code,
                         ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    /**
     * Test listing user's tontines.
     */
    public function test_get_my_tontines(): void
    {
        $token = $this->generateJwtToken(['phone' => '+221771112233']);

        $tontine1 = Tontine::create([
            'creator_phone' => '+221771112233',
            'name' => 'Tontine 1',
            'contribution_amount' => 10000,
            'frequency' => 'weekly',
            'start_date' => now()->format('Y-m-d'),
            'max_members' => 5,
        ]);
        TontineMember::create(['tontine_id' => $tontine1->id, 'member_phone' => '+221771112233', 'turn_order' => 1]);

        $tontine2 = Tontine::create([
            'creator_phone' => '+221778889900',
            'name' => 'Tontine 2',
            'contribution_amount' => 20000,
            'frequency' => 'monthly',
            'start_date' => now()->format('Y-m-d'),
            'max_members' => 5,
        ]);
        TontineMember::create(['tontine_id' => $tontine2->id, 'member_phone' => '+221778889900', 'turn_order' => 1]);
        TontineMember::create(['tontine_id' => $tontine2->id, 'member_phone' => '+221771112233', 'turn_order' => 2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->json('GET', '/api/my-tontines');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(2, 'data');
    }
}
