<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_name_phone_and_locale(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'locale' => 'bn']);
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->patchJson('/api/v1/auth/profile', [
            'name' => 'New Name',
            'phone' => '+8801700000000',
            'locale' => 'en',
        ]);

        $response->assertOk();
        $fresh = $user->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('+8801700000000', $fresh->phone);
        $this->assertSame('en', $fresh->locale);
    }

    public function test_uploads_and_replaces_an_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $first = $this->withHeader('Authorization', "Bearer {$token}")->patchJson('/api/v1/auth/profile', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);
        $first->assertOk();
        $firstPath = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->withHeader('Authorization', "Bearer {$token}")->patchJson('/api/v1/auth/profile', [
            'avatar' => UploadedFile::fake()->image('avatar2.jpg'),
        ]);
        $second->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($user->fresh()->avatar);
    }

    public function test_rejects_an_invalid_locale(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->patchJson('/api/v1/auth/profile', [
            'locale' => 'fr',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['locale']);
    }
}
