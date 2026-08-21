<?php

namespace Tests\Feature\Media;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `tenant` disk resolves to the `local` driver in every environment
 * this app runs test suites in (no real S3/R2 credentials — Decision
 * D-01), so this asserts the *honest failure* PresignedUploadService
 * promises rather than a real presigned URL, which cannot be exercised
 * without a real bucket. See that service's own docblock.
 */
class PresignedUploadTest extends TestCase
{
    use RefreshDatabase;

    private function ownerFor(Tenant $tenant): User
    {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');

        return $owner;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    public function test_requesting_a_presigned_url_against_the_local_disk_fails_clearly(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/media/presigned-upload', [
            'filename' => 'batch-01.jpg',
            'content_type' => 'image/jpeg',
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('error_code', 'media_processing_error');
    }

    public function test_invalid_content_type_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/media/presigned-upload', [
            'filename' => 'batch-01.svg',
            'content_type' => 'image/svg+xml',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('content_type');
    }

    public function test_a_staff_member_cannot_request_a_presigned_upload(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');

        $this->withHeaders($this->bearer($staff))->postJson('/api/v1/media/presigned-upload', [
            'filename' => 'batch-01.jpg',
            'content_type' => 'image/jpeg',
        ])->assertForbidden();
    }
}
