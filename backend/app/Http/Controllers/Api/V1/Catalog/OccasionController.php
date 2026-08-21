<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Catalog\StoreOccasionRequest;
use App\Http\Requests\Catalog\UpdateOccasionRequest;
use App\Models\Occasion;
use App\Services\Catalog\OccasionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccasionController extends BaseApiController
{
    public function __construct(private readonly OccasionService $occasions) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Occasion::class);

        $occasions = Occasion::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('sort_order')
            ->get();

        return $this->success(['occasions' => $occasions->map(fn (Occasion $o) => $this->present($o))->values()]);
    }

    public function store(StoreOccasionRequest $request): JsonResponse
    {
        $this->authorize('create', Occasion::class);

        $occasion = $this->occasions->create($request->user()->tenant, $request->validated());

        return $this->created($this->present($occasion), 'Occasion created successfully.');
    }

    public function update(UpdateOccasionRequest $request, Occasion $occasion): JsonResponse
    {
        $this->authorize('update', $occasion);

        $updated = $this->occasions->update($occasion, $request->validated());

        return $this->success($this->present($updated), 'Occasion updated successfully.');
    }

    public function destroy(Occasion $occasion): JsonResponse
    {
        $this->authorize('delete', $occasion);

        $this->occasions->delete($occasion);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Occasion $occasion): array
    {
        return [
            'id' => $occasion->id,
            'name' => $occasion->name,
            'slug' => $occasion->slug,
            'icon' => $occasion->icon,
            'sort_order' => $occasion->sort_order,
            'status' => $occasion->status->value,
        ];
    }
}
