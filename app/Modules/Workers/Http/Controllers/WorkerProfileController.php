<?php

namespace App\Modules\Workers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workers\Models\WorkerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $this->profile($request, true);

        return response()->json($profile?->load(['skills.category', 'documents', 'user']));
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bio' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:80'],
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:50'],
            'upi_vpa' => ['nullable', 'string', 'max:80'],
            'pan_number' => ['nullable', 'string', 'max:16'],
            'aadhaar_number' => ['nullable', 'string', 'max:16'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:32'],
            'bank_ifsc' => ['nullable', 'string', 'max:16'],
            'bank_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();

        $profile = WorkerProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'pending_kyc', 'city' => $data['city'] ?? $user->city]
        );

        $profile->update($data);

        if (! empty($data['city']) && ! $user->city) {
            $user->update(['city' => $data['city']]);
        }

        return response()->json($profile->fresh(['skills.category', 'documents']));
    }

    public function documents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:aadhaar,pan,selfie,bank'],
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $profile = $this->profile($request);
        $path = $request->file('file')->store('kyc/'.$profile->id, 'local');

        $document = $profile->documents()->create([
            'type' => $data['type'],
            'path' => $path,
            'status' => 'pending',
        ]);

        return response()->json($document, 201);
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

    private function profile(Request $request, bool $optional = false): ?WorkerProfile
    {
        $profile = $request->user()->workerProfile;

        if (! $profile && ! $optional) {
            abort(response()->json(['message' => 'Create a worker profile first.'], 422));
        }

        return $profile;
    }
}
