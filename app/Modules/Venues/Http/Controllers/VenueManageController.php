<?php

namespace App\Modules\Venues\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenuePhoto;
use App\Modules\Venues\Services\VenueAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VenueManageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $venues = Venue::query()
            ->with(['photos'])
            ->where('partner_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Venue $v) => $this->presentVenue($v));

        return response()->json($venues);
    }

    public function show(Request $request, Venue $venue): JsonResponse
    {
        $this->assertOwner($request, $venue);

        return response()->json($this->presentVenue($venue->load('photos')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedVenue($request);
        $user = $request->user();

        $venue = Venue::query()->create([
            ...$data,
            'partner_user_id' => $user->id,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'draft',
        ]);

        return response()->json($this->presentVenue($venue->fresh(['photos'])), 201);
    }

    public function update(Request $request, Venue $venue): JsonResponse
    {
        $this->assertOwner($request, $venue);
        $venue->update($this->validatedVenue($request));

        return response()->json($this->presentVenue($venue->fresh(['photos'])));
    }

    public function publish(Request $request, Venue $venue): JsonResponse
    {
        $this->assertOwner($request, $venue);
        $venue->loadCount('photos');

        if ($venue->photos_count < 1) {
            throw ValidationException::withMessages(['venue' => 'Add at least one photo before publishing.']);
        }

        $venue->update(['status' => 'published']);

        return response()->json($this->presentVenue($venue->fresh(['photos'])));
    }

    public function storePhoto(Request $request, Venue $venue): JsonResponse
    {
        $this->assertOwner($request, $venue);

        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('photo')->store('venue-photos/'.$venue->id, 'public');
        $photo = VenuePhoto::query()->create([
            'venue_id' => $venue->id,
            'path' => $path,
            'sort_order' => (int) $venue->photos()->max('sort_order') + 1,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url(),
            'sort_order' => $photo->sort_order,
        ], 201);
    }

    public function calendar(Request $request, Venue $venue, VenueAvailabilityService $availability): JsonResponse
    {
        $this->assertOwner($request, $venue);
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        return response()->json($availability->calendarMonth($venue, $year, $month));
    }

    /** @return array<string, mixed> */
    private function validatedVenue(Request $request): array
    {
        $fields = config('venues.amenity_fields', []);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'venue_type' => ['required', 'in:lawn,banquet,both'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'capacity_min' => ['required', 'integer', 'min:1', 'max:50000'],
            'capacity_max' => ['required', 'integer', 'gte:capacity_min', 'max:50000'],
            'advance_percent' => ['nullable', 'integer', 'min:10', 'max:100'],
            'price_per_day_inr' => ['required', 'integer', 'min:500', 'max:5000000'],
            'amenities' => ['nullable', 'array'],
        ]);

        $amenities = [];
        foreach ($fields as $key => $meta) {
            $value = $data['amenities'][$key] ?? null;
            if (($meta['type'] ?? '') === 'count') {
                $n = max(0, (int) $value);
                if ($n > 0) {
                    $amenities[$key] = $n;
                }
            } elseif (! empty($value)) {
                $amenities[$key] = true;
            }
        }

        return [
            'name' => $data['name'],
            'venue_type' => $data['venue_type'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'capacity_min' => (int) $data['capacity_min'],
            'capacity_max' => (int) $data['capacity_max'],
            'advance_percent' => (int) ($data['advance_percent'] ?? 30),
            'price_per_day_inr' => (int) $data['price_per_day_inr'],
            'amenities' => $amenities,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $i = 1;
        while (Venue::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function presentVenue(Venue $venue): array
    {
        $fields = config('venues.amenity_fields', []);
        $amenityLabels = [];
        foreach ($venue->amenities ?? [] as $key => $value) {
            if (! isset($fields[$key])) {
                continue;
            }
            $amenityLabels[$key] = array_merge($fields[$key], ['value' => $value]);
        }

        return [
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
            'status' => $venue->status,
            'photos' => $venue->photos->map(fn (VenuePhoto $p) => [
                'id' => $p->id,
                'url' => $p->url(),
                'sort_order' => $p->sort_order,
            ])->values(),
        ];
    }

    private function assertOwner(Request $request, Venue $venue): void
    {
        if ((int) $venue->partner_user_id !== (int) $request->user()->id && ! $request->user()->hasRole('admin')) {
            abort(403);
        }
    }
}
