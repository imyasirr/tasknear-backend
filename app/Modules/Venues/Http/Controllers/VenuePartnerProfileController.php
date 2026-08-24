<?php

namespace App\Modules\Venues\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Venues\Models\VenuePartnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenuePartnerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = VenuePartnerProfile::query()->where('user_id', $request->user()->id)->first();

        return response()->json($profile);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:80'],
            'gstin' => ['nullable', 'string', 'max:32'],
            'upi_vpa' => ['nullable', 'string', 'max:120'],
        ]);

        $profile = VenuePartnerProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            array_merge($data, ['status' => 'active']),
        );

        return response()->json($profile);
    }
}
