<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Subscriptions\Models\PlatformSetting;
use Carbon\Carbon;

class MatchingSettings
{
    public const DEFAULT_VENDOR_OFFER_SECONDS = 180;

    public const MIN_RING_SECONDS = 30;

    public const MAX_RING_SECONDS = 3600;

    public static function vendorOfferSeconds(?int $override = null): int
    {
        if ($override !== null) {
            return self::clamp($override);
        }

        return self::clamp((int) PlatformSetting::getValue(
            'vendor_offer_seconds',
            self::DEFAULT_VENDOR_OFFER_SECONDS
        ));
    }

    public static function clamp(int $seconds): int
    {
        return max(self::MIN_RING_SECONDS, min(self::MAX_RING_SECONDS, $seconds));
    }

    /** Accept window closes at end of the booking day (scheduled_start date). */
    public static function acceptDeadline(mixed $scheduledStart): Carbon
    {
        if (! $scheduledStart) {
            return now()->endOfDay();
        }

        return Carbon::parse($scheduledStart)->endOfDay();
    }

    public static function canAcceptOffer(?Carbon $expiresAt): bool
    {
        return ! $expiresAt || $expiresAt->isFuture();
    }
}
