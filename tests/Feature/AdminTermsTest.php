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
use Tests\TestCase;

class AdminTermsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(TermsAndConditionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('buyer');
    }

    public function test_non_admin_cannot_access_admin_terms_endpoints(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/admin/terms');

        $response->assertForbidden();
    }

    public function test_admin_can_list_all_terms(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/terms');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_new_terms_version(): void
    {
        $payload = [
            'title' => 'Seller & Auctioneer Agreement v1.0',
            'type' => 'seller_agreement',
            'version' => '1.0',
            'content' => '# Seller Agreement Content',
            'summary_of_changes' => 'Initial seller terms',
            'is_active' => false,
            'is_required' => true,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/terms', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.type', 'seller_agreement')
            ->assertJsonPath('data.version', '1.0');

        $this->assertDatabaseHas('terms_and_conditions', [
            'type' => 'seller_agreement',
            'version' => '1.0',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_cannot_create_duplicate_version_for_same_type(): void
    {
        $payload = [
            'title' => 'Duplicate General v1.0',
            'type' => 'general',
            'version' => '1.0', // already seeded
            'content' => 'Duplicate',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/terms', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['version']);
    }

    public function test_admin_can_update_terms_draft(): void
    {
        $terms = TermsAndCondition::where('type', TermsType::General->value)->firstOrFail();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/terms/{$terms->id}", [
                'title' => 'Updated LiveBids General Terms',
                'summary_of_changes' => 'Clarified clause 4',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Updated LiveBids General Terms')
            ->assertJsonPath('data.summary_of_changes', 'Clarified clause 4');
    }

    public function test_admin_can_publish_new_version_and_deactivate_previous(): void
    {
        $oldGeneral = TermsAndCondition::where('type', TermsType::General->value)->where('version', '1.0')->firstOrFail();
        $this->assertTrue($oldGeneral->is_active);

        // Create draft v2.0
        $newGeneral = TermsAndCondition::create([
            'title' => 'General Terms v2.0',
            'slug' => 'general-v2-0',
            'type' => TermsType::General->value,
            'version' => '2.0',
            'content' => 'Revised content for 2026',
            'is_active' => false,
            'is_required' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/terms/{$newGeneral->id}/publish");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_active', true);

        // Assert v1.0 is now deactivated
        $this->assertFalse($oldGeneral->fresh()->is_active);
        // Assert v2.0 is now active
        $this->assertTrue($newGeneral->fresh()->is_active);
    }

    public function test_admin_can_toggle_enforcement(): void
    {
        $terms = TermsAndCondition::where('type', TermsType::General->value)->firstOrFail();
        $this->assertTrue($terms->is_required);

        // Disable requirement
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/terms/{$terms->id}/enforce", [
                'is_required' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_required', false);

        $this->assertFalse($terms->fresh()->is_required);
    }

    public function test_admin_can_inspect_acceptance_metrics_and_audit_log(): void
    {
        $terms = TermsAndCondition::where('type', TermsType::General->value)->firstOrFail();

        // Have regularUser accept terms
        UserTermsAcceptance::create([
            'user_id' => $this->regularUser->id,
            'terms_id' => $terms->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'MobileApp/1.0',
            'accepted_at' => now(),
        ]);

        // 1. Inspect metrics
        $metricsResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/terms/{$terms->id}");

        $metricsResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.metrics.total_accepted', 1)
            ->assertJsonPath('data.metrics.total_users', 2); // admin + regularUser

        // 2. Inspect audit log
        $auditResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/terms/{$terms->id}/audit-log");

        $auditResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->regularUser->id)
            ->assertJsonPath('data.0.ip_address', '10.0.0.1');
    }
}
