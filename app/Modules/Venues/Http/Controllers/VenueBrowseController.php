<?php

namespace App\Modules\Venues\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenuePhoto;
use App\Modules\Venues\Services\VenueAvailabilityService;
use App\Modules\Venues\Support\VenueAmenityPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueBrowseController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json([
            'amenity_fields' => VenueAmenityPresenter::fields(),
            'amenity_categories' => VenueAmenityPresenter::categories(),
            'venue_types' => config('venues.venue_types', []),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Venue::query()
            ->with(['photos' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount('photos')
            ->where('status', 'published');

        if ($city = $request->query('city')) {
            $query->where('city', $city);
        }
        if ($type = $request->query('type')) {
            $query->where('venue_type', $type);
        }

        $venues = $query->orderBy('name')->get()->map(function (Venue $v) {
            $custom = $v->custom_services ?? [];
            $labels = VenueAmenityPresenter::labels($v->amenities ?? [], $custom);

            return [
                'id' => $v->id,
                'slug' => $v->slug,
                'name' => $v->name,
                'venue_type' => $v->venue_type,
                'city' => $v->city,
                'address' => $v->address,
                'capacity_min' => $v->capacity_min,
                'capacity_max' => $v->capacity_max,
                'advance_percent' => $v->advance_percent,
                'price_per_day_inr' => $v->price_per_day_inr,
                'description' => $v->description,
                'amenities' => $v->amenities ?? [],
                'custom_services' => $custom,
                'amenity_labels' => $labels,
                'cover_url' => $v->photos->first(fn (VenuePhoto $p) => $p->isImage())?->url(),
                'photo_count' => (int) $v->photos->filter(fn (VenuePhoto $p) => $p->isImage())->count(),
                'video_count' => (int) $v->photos->filter(fn (VenuePhoto $p) => $p->isVideo())->count(),
                'media_count' => (int) ($v->photos_count ?? $v->photos->count()),
                'photos' => $v->photos->take(6)->map(fn (VenuePhoto $p) => $p->toMediaRow())->values(),
            ];
        });

        return response()->json($venues);
    }

    public function show(string $slug, VenueAvailabilityService $availability): JsonResponse
    {
        $venue = Venue::query()
            ->with(['photos', 'partner.venuePartnerProfile'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json([
            'id' => $venue->id,
            'slug' => $venue->slug,
            'name' => $venue->name,
            'venue_type' => $venue->venue_type,
            'description' => $venue->description,
            'address' => $venue->address,
            'city' => $venue->city,
            'capacity_min' => $venue->capacity_min,
            'capacity_max' => $venue->capacity_max,
            'advance_percent' => $venue->advance_percent,
            'price_per_day_inr' => $venue->price_per_day_inr,
            'amenities' => $venue->amenities ?? [],
            'custom_services' => $venue->custom_services ?? [],
            'amenity_labels' => VenueAmenityPresenter::labels($venue->amenities ?? [], $venue->custom_services ?? []),
            'amenity_categories' => VenueAmenityPresenter::categories(),
            'photos' => $venue->photos->map(fn (VenuePhoto $p) => $p->toMediaRow())->values(),
            'partner' => [
                'company_name' => $venue->partner?->venuePartnerProfile?->company_name ?? $venue->partner?->name,
            ],
            'booked_ranges' => $availability->bookedRanges($venue->id),
        ]);
    }
}
