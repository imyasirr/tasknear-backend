<?php

namespace App\Modules\Ops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Services\MatchingSettings;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Subscriptions\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMatchingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'vendor_offer_seconds' => MatchingSettings::vendorOfferSeconds(),
            'min_ring_seconds' => MatchingSettings::MIN_RING_SECONDS,
            'max_ring_seconds' => MatchingSettings::MAX_RING_SECONDS,
        ]);
    }

    public function update(Request $request, Auditor $auditor): JsonResponse
    {
        $data = $request->validate([
            'vendor_offer_seconds' => ['required', 'integer', 'min:'.MatchingSettings::MIN_RING_SECONDS, 'max:'.MatchingSettings::MAX_RING_SECONDS],
        ]);

        $seconds = MatchingSettings::clamp($data['vendor_offer_seconds']);
        PlatformSetting::setValue('vendor_offer_seconds', $seconds);
        $auditor->record($request->user(), 'settings.ring_seconds', null, ['vendor_offer_seconds' => $seconds]);

        return $this->show();
    }
}
