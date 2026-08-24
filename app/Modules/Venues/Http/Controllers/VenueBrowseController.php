<?php

namespace App\Modules\Venues\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenuePhoto;
use App\Modules\Venues\Services\VenueAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueBrowseController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json([
            'amenity_fields' => config('venues.amenity_fields', []),
            'venue_types' => config('venues.venue_types', []),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Venue::query()
            ->with(['photos' => fn ($q) => $q->orderBy('sort_order')->limit(1)])
            ->where('status', 'published');

        if ($city = $request->query('city')) {
            $query->where('city', $city);
        }
        if ($type = $request->query('type')) {
            $query->where('venue_type', $type);
        }

        $venues = $query->orderBy('name')->get()->map(fn (Venue $v) => [
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
            'amenities' => $v->amenities ?? [],
            'cover_url' => $v->photos->first()?->url(),
        ]);

        return response()->json($venues);
    }

    public function show(string $slug, VenueAvailabilityService $availability): JsonResponse
    {
        $venue = Venue::query()
            ->with(['photos', 'partner.venuePartnerProfile'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $fields = config('venues.amenity_fields', []);
        $amenityLabels = [];
        $rawAmenities = $venue->amenities ?? [];
        // Support old checkbox list: ["hall","ac",...] and new map: {halls:2,ac:true}
        if (array_is_list($rawAmenities)) {
            $legacyMap = [
                'hall' => 'halls', 'rooms' => 'rooms', 'bathrooms' => 'bathrooms',
                'parking' => 'parking_cars', 'ac' => 'ac', 'stage' => 'stage',
                'kitchen' => 'kitchen', 'garden' => 'garden', 'wifi' => 'wifi',
                'generator' => 'generator', 'valet' => 'valet',
            ];
            foreach ($rawAmenities as $key) {
                $mapped = $legacyMap[$key] ?? $key;
                if (! isset($fields[$mapped])) {
                    continue;
                }
                $amenityLabels[$mapped] = array_merge($fields[$mapped], [
                    'value' => ($fields[$mapped]['type'] ?? '') === 'count' ? 1 : true,
                ]);
            }
        } else {
            foreach ($rawAmenities as $key => $value) {
                if (! isset($fields[$key])) {
                    continue;
                }
                $amenityLabels[$key] = array_merge($fields[$key], ['value' => $value]);
            }
        }

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
            'amenity_labels' => $amenityLabels,
            'photos' => $venue->photos->map(fn (VenuePhoto $p) => [
                'id' => $p->id,
                'url' => $p->url(),
            ])->values(),
            'partner' => [
                'company_name' => $venue->partner?->venuePartnerProfile?->company_name ?? $venue->partner?->name,
            ],
            'booked_ranges' => $availability->bookedRanges($venue->id),
        ]);
    }
}
