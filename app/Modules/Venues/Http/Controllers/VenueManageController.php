<?php

namespace App\Modules\Venues\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenuePhoto;
use App\Modules\Venues\Support\VenueAmenityPresenter;
use App\Modules\Venues\Services\VenueAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $imageCount = $venue->photos()->where('media_type', 'image')->count();
        if ($imageCount < 1) {
            throw ValidationException::withMessages(['venue' => 'Add at least one photo before publishing.']);
        }

        $venue->update(['status' => 'published']);

        return response()->json($this->presentVenue($venue->fresh(['photos'])));
    }

    public function storePhoto(Request $request, Venue $venue): JsonResponse
    {
        $this->assertOwner($request, $venue);

        $file = $request->file('photo');
        if (! $file) {
            throw ValidationException::withMessages(['photo' => 'Choose a photo or video file.']);
        }

        $mime = (string) $file->getMimeType();
        $isVideo = str_starts_with($mime, 'video/');
        $upload = config('venues.upload', []);

        $request->validate([
            'photo' => [
                'required',
                'file',
                $isVideo
                    ? 'mimes:'.implode(',', $upload['video_mimes'] ?? ['mp4', 'webm', 'mov'])
                    : 'mimes:'.implode(',', $upload['image_mimes'] ?? ['jpg', 'jpeg', 'png', 'webp']),
                'max:'.($isVideo ? ($upload['video_max_kb'] ?? 81920) : ($upload['image_max_kb'] ?? 20480)),
            ],
        ]);

        $folder = 'venue-media/'.$venue->id;
        $path = $file->store($folder, 'public');
        $photo = VenuePhoto::query()->create([
            'venue_id' => $venue->id,
            'path' => $path,
            'media_type' => $isVideo ? 'video' : 'image',
            'sort_order' => (int) $venue->photos()->max('sort_order') + 1,
        ]);

        return response()->json($photo->toMediaRow(), 201);
    }

    public function destroyPhoto(Request $request, Venue $venue, VenuePhoto $photo): JsonResponse
    {
        $this->assertOwner($request, $venue);

        if ((int) $photo->venue_id !== (int) $venue->id) {
            abort(404);
        }

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return response()->json(['message' => 'Photo removed.']);
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
        $user = $request->user();
        $venueType = $this->venueTypeForUser($user);

        if (! $venueType && ! $user->hasRole('admin')) {
            abort(403, 'Only lawn or banquet partners can manage venues.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'venue_type' => [$user->hasRole('admin') ? 'required' : 'prohibited', 'in:lawn,banquet'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'capacity_min' => ['required', 'integer', 'min:1', 'max:50000'],
            'capacity_max' => ['required', 'integer', 'gte:capacity_min', 'max:50000'],
            'advance_percent' => ['nullable', 'integer', 'min:10', 'max:100'],
            'price_per_day_inr' => ['required', 'integer', 'min:500', 'max:5000000'],
            'amenities' => ['nullable', 'array'],
            'custom_services' => ['nullable', 'array'],
            'custom_services.*.id' => ['nullable', 'string', 'max:64'],
            'custom_services.*.name' => ['nullable', 'string', 'max:120'],
            'custom_services.*.icon' => ['nullable', 'string', 'max:64'],
            'custom_services.*.note' => ['nullable', 'string', 'max:255'],
            'custom_services.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
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
            'venue_type' => $venueType ?? $data['venue_type'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'capacity_min' => (int) $data['capacity_min'],
            'capacity_max' => (int) $data['capacity_max'],
            'advance_percent' => (int) ($data['advance_percent'] ?? 30),
            'price_per_day_inr' => (int) $data['price_per_day_inr'],
            'amenities' => $amenities,
            'custom_services' => VenueAmenityPresenter::sanitizeCustomServices($data['custom_services'] ?? null),
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
        $custom = $venue->custom_services ?? [];

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
            'custom_services' => $custom,
            'amenity_labels' => VenueAmenityPresenter::labels($venue->amenities ?? [], $custom),
            'amenity_categories' => VenueAmenityPresenter::categories(),
            'status' => $venue->status,
            'photos' => $venue->photos->map(fn (VenuePhoto $p) => $p->toMediaRow())->values(),
        ];
    }

    private function assertOwner(Request $request, Venue $venue): void
    {
        if ((int) $venue->partner_user_id !== (int) $request->user()->id && ! $request->user()->hasRole('admin')) {
            abort(403);
        }
    }

    private function venueTypeForUser(\App\Models\User $user): ?string
    {
        if ($user->hasRole('lawn')) {
            return 'lawn';
        }

        if ($user->hasRole('banquet')) {
            return 'banquet';
        }

        if ($user->hasRole('venue_partner')) {
            return null;
        }

        return null;
    }
}
