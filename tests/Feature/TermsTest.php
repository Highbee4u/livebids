<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Terms\Enums\TermsType;
use App\Domain\Terms\Models\TermsAndCondition;
use App\Domain\Terms\Models\UserTermsAcceptance;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TermsAndConditionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TermsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(TermsAndConditionSeeder::class);

        // Register temporary test route guarded by 'terms.accepted'
        Route::middleware(['api', 'auth:sanctum', 'terms.accepted'])
            ->get('/api/v1/test-terms-guarded', function () {
                return response()->json(['status' => 'success', 'message' => 'Passed terms check.']);
            });
    }

    public function test_guest_can_list_active_terms(): void
    {
        $response = $this->getJson('/api/v1/terms');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data');
    }

    public function test_guest_can_read_specific_terms_type(): void
    {
        $response = $this->getJson('/api/v1/terms/general');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.type', 'general')
            ->assertJsonPath('data.version', '1.0');
    }

    public function test_reading_non_existent_terms_type_returns_404(): void
    {
        $response = $this->getJson('/api/v1/terms/non_existent_type');

        $response->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error_code', 'TERMS_NOT_FOUND');
    }

    public function test_guest_can_view_version_history(): void
    {
        // Add draft v2.0
        TermsAndCondition::create([
            'title' => 'General Terms v2.0',
            'slug' => 'general-v2-0-test',
            'type' => TermsType::General->value,
            'version' => '2.0',
            'content' => 'Updated content',
            'is_active' => false,
            'is_required' => true,
        ]);

        $response = $this->getJson('/api/v1/terms/general/versions');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_pending_required_terms(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/terms/pending');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data'); // all 3 seeded terms are required and unaccepted
    }

    public function test_user_can_accept_active_terms(): void
    {
        $user = User::factory()->create();
        $terms = TermsAndCondition::where('type', TermsType::General->value)->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->withServerVariables([
                'REMOTE_ADDR' => '192.168.1.100',
                'HTTP_USER_AGENT' => 'LiveBids-Mobile/1.0.0 (Android)',
            ])
            ->postJson('/api/v1/terms/accept', [
                'terms_id' => $terms->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.terms_id', $terms->id);

        $this->assertDatabaseHas('user_terms_acceptances', [
            'user_id' => $user->id,
            'terms_id' => $terms->id,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'LiveBids-Mobile/1.0.0 (Android)',
        ]);
    }

    public function test_user_cannot_accept_inactive_terms(): void
    {
        $user = User::factory()->create();
        $inactiveTerms = TermsAndCondition::create([
            'title' => 'Old Inactive Terms',
            'slug' => 'old-inactive',
            'type' => TermsType::General->value,
            'version' => '0.9',
            'content' => 'Old content',
            'is_active' => false,
            'is_required' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/terms/accept', [
                'terms_id' => $inactiveTerms->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'TERMS_NOT_ACTIVE');
    }

    public function test_user_can_view_own_acceptance_history(): void
    {
        $user = User::factory()->create();
        $terms = TermsAndCondition::where('type', TermsType::General->value)->firstOrFail();

        UserTermsAcceptance::create([
            'user_id' => $user->id,
            'terms_id' => $terms->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/terms/my-acceptances');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.terms_id', $terms->id);
    }

    public function test_middleware_blocks_unconsented_user_from_protected_route(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/test-terms-guarded');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'TERMS_ACCEPTANCE_REQUIRED')
            ->assertJsonCount(3, 'pending_terms');
    }

    public function test_middleware_allows_consented_user_to_proceed(): void
    {
        $user = User::factory()->create();

        // Accept all active required terms
        $allRequired = TermsAndCondition::where('is_active', true)->where('is_required', true)->get();
        foreach ($allRequired as $terms) {
            UserTermsAcceptance::create([
                'user_id' => $user->id,
                'terms_id' => $terms->id,
                'accepted_at' => now(),
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/test-terms-guarded');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Passed terms check.');
    }
}
