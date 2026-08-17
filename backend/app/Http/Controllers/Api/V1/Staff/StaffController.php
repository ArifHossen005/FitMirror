<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Staff\UpdateStaffRoleRequest;
use App\Models\User;
use App\Services\Staff\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff CRUD — list, show, update role, deactivate, delete. Every {target}
 * route parameter resolves through Laravel's normal implicit model
 * binding, which still runs through TenantScope — a cross-tenant id 404s
 * before the controller method body ever executes. UserPolicy's own
 * tenant_id check is defense in depth on top of that, plus the checks
 * TenantScope has no way to express (self-action, owner immutability).
 */
class StaffController extends BaseApiController
{
    public function __construct(private readonly StaffService $staffService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $tenantId = $request->user()->tenant_id;

        $staff = User::query()
            ->where('tenant_id', $tenantId)
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return $this->paginated($staff->through(fn (User $user) => $this->present($user)));
    }

    public function show(User $target): JsonResponse
    {
        $this->authorize('view', $target);

        return $this->success($this->present($target));
    }

    public function updateRole(UpdateStaffRoleRequest $request, User $target): JsonResponse
    {
        $this->authorize('update', $target);

        $this->staffService->updateRole($target, $request->validated('role'));

        return $this->success($this->present($target->fresh()));
    }

    public function deactivate(User $target): JsonResponse
    {
        $this->authorize('deactivate', $target);

        $this->staffService->deactivate($target);

        return $this->success($this->present($target->fresh()));
    }

    public function reactivate(User $target): JsonResponse
    {
        $this->authorize('update', $target);

        $this->staffService->reactivate($target);

        return $this->success($this->present($target->fresh()));
    }

    public function destroy(User $target): JsonResponse
    {
        $this->authorize('delete', $target);

        $this->staffService->delete($target);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $target): array
    {
        return [
            'id' => $target->id,
            'name' => $target->name,
            'email' => $target->email,
            'phone' => $target->phone,
            'avatar' => $target->avatar,
            'status' => $target->status->value,
            'is_owner' => $target->isTenantOwner(),
            'roles' => $target->roles->pluck('name'),
            'last_login_at' => $target->last_login_at?->toIso8601String(),
            'created_at' => $target->created_at?->toIso8601String(),
        ];
    }
}
