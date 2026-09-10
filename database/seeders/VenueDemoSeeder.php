<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Money\Models\Payment;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenueBooking;
use App\Modules\Venues\Models\VenuePartnerProfile;
use App\Modules\Venues\Models\VenuePhoto;
use App\Modules\Venues\Models\VenueSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VenueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $ayesha = $this->user('9000000001', 'Ayesha Khan', 'customer');
        $this->user('9000000002', 'Vikram Singh', 'customer');

        $lawnPartner = $this->partner('9333333331', 'Green Meadows Group', 'lawn', [
            'company_name' => 'Green Meadows Group',
            'bio' => 'Premium open-air lawns for weddings and celebrations across Lucknow.',
            'upi_vpa' => 'greenmeadows@okaxis',
        ]);

        $banquetPartner = $this->partner('9333333332', 'Lucknow Banquet Co', 'banquet', [
            'company_name' => 'Lucknow Banquet Co',
            'bio' => 'AC banquet halls with in-house catering support, valet and event managers.',
            'upi_vpa' => 'lucknowbanquet@oksbi',
        ]);

        $lawns = [
            [
                'slug' => 'green-meadows-lawn',
                'name' => 'Green Meadows Lawn',
                'description' => 'A lush open-air lawn in Gomti Nagar with fairy-light canopy, mandap setup area and green-room tents. Ideal for wedding baraat, reception and sangeet. In-house power backup and parking for 120 cars.',
                'address' => 'Plot 12, Vibhuti Khand, Gomti Nagar',
                'capacity_min' => 150,
                'capacity_max' => 900,
                'price_per_day_inr' => 48000,
                'advance_percent' => 30,
                'amenities' => ['halls' => 1, 'rooms' => 4, 'bathrooms' => 8, 'parking_cars' => 120, 'parking_bikes' => 80, 'garden' => true, 'stage' => true, 'generator' => true, 'wifi' => true],
                'media' => $this->lawnMedia(),
            ],
            [
                'slug' => 'royal-palm-garden',
                'name' => 'Royal Palm Garden',
                'description' => 'Palm-lined lawn in Aliganj with pool-side photo spots, covered dining pavilion and separate kids zone. Popular for mehendi, cocktail and corporate lawn parties.',
                'address' => 'Sector B, Aliganj',
                'capacity_min' => 80,
                'capacity_max' => 550,
                'price_per_day_inr' => 38000,
                'advance_percent' => 25,
                'amenities' => ['halls' => 1, 'rooms' => 2, 'bathrooms' => 6, 'parking_cars' => 70, 'parking_bikes' => 40, 'garden' => true, 'stage' => true, 'kitchen' => true, 'wifi' => true],
                'media' => $this->lawnMedia(2),
            ],
        ];

        $banquets = [
            [
                'slug' => 'gomti-banquet-hall',
                'name' => 'Gomti Banquet Hall',
                'description' => 'Fully air-conditioned hall in Indira Nagar with crystal chandeliers, LED wall and attached bridal suite. Fixed seating for 600 with buffet counters and live kitchen counter option.',
                'address' => 'Faizabad Road, Indira Nagar',
                'capacity_min' => 200,
                'capacity_max' => 650,
                'price_per_day_inr' => 72000,
                'advance_percent' => 35,
                'amenities' => ['halls' => 2, 'rooms' => 6, 'bathrooms' => 10, 'parking_cars' => 90, 'parking_bikes' => 50, 'ac' => true, 'stage' => true, 'kitchen' => true, 'valet' => true, 'generator' => true, 'wifi' => true, 'catering' => true, 'decoration' => true, 'sound_system' => true, 'dry_cleaning' => true],
                'media' => $this->banquetMedia(1),
            ],
            [
                'slug' => 'hazratganj-grand-banquet',
                'name' => 'Hazratganj Grand Banquet',
                'description' => 'Central Hazratganj location — premium hall for corporate galas, engagements and boutique weddings. Valet parking, green room and dedicated event coordinator included.',
                'address' => 'MG Road, Hazratganj',
                'capacity_min' => 120,
                'capacity_max' => 450,
                'price_per_day_inr' => 85000,
                'advance_percent' => 40,
                'amenities' => ['halls' => 1, 'rooms' => 4, 'bathrooms' => 8, 'parking_cars' => 60, 'ac' => true, 'stage' => true, 'kitchen' => true, 'valet' => true, 'wifi' => true, 'catering' => true, 'laundry' => true, 'security' => true],
                'media' => $this->banquetMedia(2),
            ],
            [
                'slug' => 'mahanagar-crystal-hall',
                'name' => 'Mahanagar Crystal Hall',
                'description' => 'Double-height ceiling hall in Mahanagar with modular seating, projector setup and separate vegetarian/non-veg kitchen lines. Best for large wedding receptions.',
                'address' => 'Ring Road, Mahanagar',
                'capacity_min' => 250,
                'capacity_max' => 800,
                'price_per_day_inr' => 95000,
                'advance_percent' => 35,
                'amenities' => ['halls' => 2, 'rooms' => 8, 'bathrooms' => 12, 'parking_cars' => 100, 'parking_bikes' => 60, 'ac' => true, 'stage' => true, 'kitchen' => true, 'generator' => true, 'valet' => true, 'wifi' => true],
                'media' => $this->banquetMedia(3),
            ],
            [
                'slug' => 'alambagh-heritage-banquet',
                'name' => 'Alambagh Heritage Banquet',
                'description' => 'Heritage-themed banquet with jharokha décor, warm lighting and courtyard entrance. Affordable packages for 150–400 guests with in-house décor team tie-up.',
                'address' => 'Kanpur Road, Alambagh',
                'capacity_min' => 100,
                'capacity_max' => 400,
                'price_per_day_inr' => 52000,
                'advance_percent' => 30,
                'amenities' => ['halls' => 1, 'rooms' => 3, 'bathrooms' => 6, 'parking_cars' => 55, 'ac' => true, 'stage' => true, 'kitchen' => true, 'generator' => true],
                'media' => $this->banquetMedia(4),
            ],
            [
                'slug' => 'riverside-royal-banquet',
                'name' => 'Riverside Royal Banquet',
                'description' => 'Gomti riverfront banquet with glass façade, sunset views and terrace cocktail area. Premium choice for engagement dinners and anniversary celebrations.',
                'address' => 'Gomti River Front, Lucknow',
                'capacity_min' => 80,
                'capacity_max' => 350,
                'price_per_day_inr' => 78000,
                'advance_percent' => 35,
                'amenities' => ['halls' => 1, 'rooms' => 5, 'bathrooms' => 7, 'parking_cars' => 75, 'ac' => true, 'stage' => true, 'kitchen' => true, 'valet' => true, 'wifi' => true, 'generator' => true, 'catering' => true, 'dj' => true, 'photography' => true, 'dry_cleaning' => true],
                'media' => $this->banquetMedia(5),
            ],
        ];

        $lawnVenues = [];
        foreach ($lawns as $row) {
            $lawnVenues[] = $this->venue($lawnPartner, $row);
        }

        $banquetVenues = [];
        foreach ($banquets as $row) {
            $banquetVenues[] = $this->venue($banquetPartner, $row);
        }

        $this->booking($ayesha, $lawnVenues[0], [
            'guest_count' => 280,
            'starts_at' => now()->addDays(12)->setTime(18, 0),
            'ends_at' => now()->addDays(12)->setTime(23, 0),
            'status' => 'confirmed',
            'notes' => 'Wedding reception — vegetarian menu',
        ]);

        $this->booking($ayesha, $banquetVenues[0], [
            'guest_count' => 220,
            'starts_at' => now()->addDays(28)->setTime(19, 0),
            'ends_at' => now()->addDays(28)->setTime(23, 30),
            'status' => 'awaiting_payment',
            'notes' => 'Engagement dinner',
        ]);
    }

    private function user(string $phone, string $name, string $role): User
    {
        $user = User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'email' => $phone.'@tasknear.local',
                'password' => Hash::make('password'),
                'password_set_at' => now(),
                'city' => 'Lucknow',
                'locale' => 'en',
            ]
        );
        $user->assignRole($role);

        return $user;
    }

    /** @param  array<string, mixed>  $extra */
    private function partner(string $phone, string $name, string $role, array $extra = []): User
    {
        $user = $this->user($phone, $name, $role);

        VenuePartnerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => $extra['company_name'] ?? $name,
                'bio' => $extra['bio'] ?? null,
                'city' => 'Lucknow',
                'upi_vpa' => $extra['upi_vpa'] ?? null,
                'status' => 'active',
            ]
        );

        return $user->fresh('venuePartnerProfile');
    }

    /** @param  array<string, mixed>  $data */
    private function venue(User $partner, array $data): Venue
    {
        $venue = Venue::query()->updateOrCreate(
            ['slug' => $data['slug']],
            [
                'partner_user_id' => $partner->id,
                'name' => $data['name'],
                'venue_type' => $data['venue_type'] ?? ($partner->hasRole('lawn') ? 'lawn' : 'banquet'),
                'description' => $data['description'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? 'Lucknow',
                'capacity_min' => (int) ($data['capacity_min'] ?? 50),
                'capacity_max' => (int) ($data['capacity_max'] ?? 500),
                'advance_percent' => (int) ($data['advance_percent'] ?? 30),
                'price_per_day_inr' => (int) ($data['price_per_day_inr'] ?? 25000),
                'amenities' => $data['amenities'] ?? ['garden' => true, 'parking_cars' => 40],
                'status' => 'published',
            ]
        );

        $this->seedMedia($venue, $data['media'] ?? []);

        return $venue->fresh(['photos']);
    }

    /** @return list<array{file:string,type:string}> */
    private function lawnMedia(int $variant = 1): array
    {
        $images = match ($variant % 2) {
            0 => ['lawn-hq-2.jpg', 'lawn-hq-4.jpg', 'lawn-hq-5.jpg', 'lawn-hq-1.jpg', 'lawn-hq-3.jpg'],
            default => ['lawn-hq-1.jpg', 'lawn-hq-3.jpg', 'lawn-hq-4.jpg', 'lawn-hq-5.jpg', 'lawn-hq-2.jpg'],
        };

        return [
            ...array_map(fn (string $file) => ['file' => $file, 'type' => 'image'], $images),
            ['file' => 'lawn-tour.mp4', 'type' => 'video'],
        ];
    }

    /** @return list<array{file:string,type:string}> */
    private function banquetMedia(int $variant = 1): array
    {
        $pool = ['banquet-hq-1.jpg', 'banquet-hq-2.jpg', 'banquet-hq-3.jpg', 'banquet-hq-4.jpg', 'banquet-hq-5.jpg', 'banquet-hq-6.jpg'];
        $offset = ($variant - 1) % count($pool);
        $images = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));
        $images = array_slice($images, 0, 5);

        return [
            ...array_map(fn (string $file) => ['file' => $file, 'type' => 'image'], $images),
            ['file' => 'banquet-tour.mp4', 'type' => 'video'],
        ];
    }

    /** @param  list<array{file:string,type:string}|string>  $items */
    private function seedMedia(Venue $venue, array $items): void
    {
        $venue->photos()->each(function (VenuePhoto $photo) {
            Storage::disk('public')->delete($photo->path);
            $photo->delete();
        });

        $disk = Storage::disk('public');
        $disk->makeDirectory('venue-media/'.$venue->id);

        foreach ($items as $i => $item) {
            $asset = is_array($item) ? $item['file'] : $item;
            $type = is_array($item) ? ($item['type'] ?? 'image') : 'image';
            $src = database_path('seeders/assets/venues/'.$asset);
            if (! is_file($src)) {
                continue;
            }

            $filename = pathinfo($asset, PATHINFO_FILENAME).'-'.($i + 1).'.'.pathinfo($asset, PATHINFO_EXTENSION);
            $dest = 'venue-media/'.$venue->id.'/'.$filename;
            $disk->put($dest, file_get_contents($src));

            VenuePhoto::query()->create([
                'venue_id' => $venue->id,
                'path' => $dest,
                'media_type' => $type,
                'sort_order' => $i + 1,
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function booking(User $customer, Venue $venue, array $data): VenueBooking
    {
        $startsAt = $data['starts_at'];
        $endsAt = $data['ends_at'];
        $totalInr = (int) ($data['total_inr'] ?? $venue->price_per_day_inr);
        $advancePercent = max(10, min(100, (int) $venue->advance_percent));
        $advanceInr = (int) max(1, round($totalInr * $advancePercent / 100));
        $balanceInr = max(0, $totalInr - $advanceInr);
        $status = (string) ($data['status'] ?? 'awaiting_payment');
        $slug = 'vb-'.Str::lower(Str::random(10));

        $slot = VenueSlot::query()->create([
            'venue_id' => $venue->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'price_inr' => $totalInr,
            'capacity' => (int) $data['guest_count'],
            'status' => $status === 'confirmed' ? 'booked' : 'held',
        ]);

        $serviceRequest = ServiceRequest::query()->create([
            'requester_id' => $customer->id,
            'vendor_user_id' => $venue->partner_user_id,
            'type' => 'venue',
            'provider_type' => $venue->venue_type,
            'slug' => 'venue-'.Str::lower(Str::random(10)),
            'city' => $venue->city,
            'address' => $venue->address,
            'scheduled_start' => $startsAt,
            'scheduled_end' => $endsAt,
            'budget_inr' => $totalInr,
            'required_workers' => 0,
            'status' => $status === 'confirmed' ? 'confirmed' : 'awaiting_payment',
            'notes' => $data['notes'] ?? null,
        ]);

        $payment = Payment::query()->create([
            'service_request_id' => $serviceRequest->id,
            'payer_id' => $customer->id,
            'amount_inr' => $advanceInr,
            'labor_inr' => $advanceInr,
            'commission_inr' => 0,
            'commission_bps' => 0,
            'fee_waived' => true,
            'gateway' => 'manual',
            'status' => $status === 'confirmed' ? 'paid' : 'pending',
            'paid_at' => $status === 'confirmed' ? now() : null,
        ]);

        return VenueBooking::query()->create([
            'slug' => $slug,
            'venue_id' => $venue->id,
            'slot_id' => $slot->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'customer_user_id' => $customer->id,
            'partner_user_id' => $venue->partner_user_id,
            'guest_count' => (int) $data['guest_count'],
            'total_inr' => $totalInr,
            'advance_inr' => $advanceInr,
            'balance_inr' => $balanceInr,
            'notes' => $data['notes'] ?? null,
            'status' => $status,
            'payment_id' => $payment->id,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
