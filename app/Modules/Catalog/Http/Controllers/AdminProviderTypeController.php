<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\ProviderType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProviderTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ProviderType::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $row = ProviderType::query()->create($data);

        return response()->json($row, 201);
    }

    public function update(Request $request, ProviderType $providerType): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['sometimes', 'string', 'max:40', 'alpha_dash', Rule::unique('provider_types', 'slug')->ignore($providerType->id)],
            'role' => ['sometimes', 'string', 'max:40', Rule::unique('provider_types', 'role')->ignore($providerType->id)],
            'match_mode' => ['sometimes', 'in:vendor,worker'],
            'name' => ['sometimes', 'string', 'max:120'],
            'name_hi' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'description_hi' => ['nullable', 'string', 'max:500'],
            'category_slugs' => ['nullable', 'array'],
            'category_slugs.*' => ['string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ]);

        if (array_key_exists('category_slugs', $data)) {
            $data['category_slugs'] = array_values(array_unique($data['category_slugs'] ?? []));
        }

        $providerType->update($data);

        return response()->json($providerType->fresh());
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('provider_types', 'slug')->ignore($ignoreId)],
            'role' => ['required', 'string', 'max:40', Rule::unique('provider_types', 'role')->ignore($ignoreId)],
            'match_mode' => ['required', 'in:vendor,worker'],
            'name' => ['required', 'string', 'max:120'],
            'name_hi' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'description_hi' => ['nullable', 'string', 'max:500'],
            'category_slugs' => ['nullable', 'array'],
            'category_slugs.*' => ['string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ]);

        $data['category_slugs'] = array_values(array_unique($data['category_slugs'] ?? []));

        return $data;
    }
}
