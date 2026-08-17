<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\StaffInvitationStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Staff\InviteStaffRequest;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Staff\StaffInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pending-invitation surface — creating one (store) and listing/cancelling
 * outstanding ones. Accepting an invitation is a separate, unauthenticated
 * controller (InviteAcceptController) since the invitee has no session at
 * that point.
 */
class StaffInvitationController extends BaseApiController
{
    public function __construct(private readonly StaffInvitationService $invitations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $invitations = StaffInvitation::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('status', StaffInvitationStatus::Pending)
            ->with('inviter:id,name')
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return $this->paginated($invitations->through(fn (StaffInvitation $invitation) => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'role' => $invitation->role,
            'invited_by' => $invitation->inviter?->name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
        ]));
    }

    public function store(InviteStaffRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $invitation = $this->invitations->invite(
            $request->user()->tenant,
            $request->user(),
            $request->validated(),
        );

        return $this->created([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 'Invitation sent.');
    }

    /**
     * $invitation is resolved through the normal (tenant-scoped) route
     * binding — StaffInvitation uses BelongsToTenant, so a cross-tenant id
     * 404s before this method body ever runs, no explicit check needed.
     */
    public function destroy(StaffInvitation $invitation): JsonResponse
    {
        $this->authorize('create', User::class);

        $this->invitations->revoke($invitation);

        return $this->noContent();
    }
}
