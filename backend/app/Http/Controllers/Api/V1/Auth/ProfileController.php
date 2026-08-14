<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * PATCH /api/v1/auth/profile. Notification preferences are deliberately
 * not handled here — `notification_preferences` doesn't exist until Phase
 * 9.A, and Phase 9.B is where that settings UI actually lands.
 */
class ProfileController extends BaseApiController
{
    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill(collect($validated)->except('avatar')->toArray());

        if (array_key_exists('avatar', $validated)) {
            $user->avatar = $validated['avatar'];
        }

        $user->save();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'avatar' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
            'locale' => $user->locale,
        ], message: 'Profile updated successfully.');
    }
}
