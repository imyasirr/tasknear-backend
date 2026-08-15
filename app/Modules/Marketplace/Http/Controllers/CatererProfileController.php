<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\CatererProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatererProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $this->profile($request, true);

        return response()->json($profile?->load(['skills.category', 'user']));
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'bio' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:80'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'upi_vpa' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();
        $user->assignRole('caterer');

        $profile = CatererProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => $data['company_name'],
                'city' => $data['city'] ?? $user->city,
                'status' => 'active',
                'is_available' => true,
            ]
        );

        $profile->update($data);

        if (! empty($data['city']) && ! $user->city) {
            $user->update(['city' => $data['city']]);
        }

        if ($user->name === 'User '.$user->phone || $user->name === $user->phone) {
            $user->update(['name' => $data['company_name']]);
        }

        return response()->json($profile->fresh(['skills.category', 'user']));
    }

    public function skills(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $profile = $this->profile($request);
        $profile->skills()->whereNotIn('category_id', $data['category_ids'])->delete();

        foreach ($data['category_ids'] as $categoryId) {
            $profile->skills()->firstOrCreate(['category_id' => $categoryId]);
        }

        return response()->json($profile->fresh('skills.category'));
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $profile = $this->profile($request);
        $profile->update(['is_available' => $data['is_available']]);

        return response()->json($profile->fresh());
    }

    private function profile(Request $request, bool $optional = false): ?CatererProfile
    {
        $profile = $request->user()->catererProfile;

        if (! $profile && ! $optional) {
            abort(response()->json(['message' => 'Create a catering company profile first.'], 422));
        }

        return $profile;
    }
}
