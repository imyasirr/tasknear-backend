<?php

namespace App\Modules\Venues\Support;

use Illuminate\Support\Str;

class VenueAmenityPresenter
{
    /** @return array<string, array<string, mixed>> */
    public static function fields(): array
    {
        return config('venues.amenity_fields', []);
    }

    /** @return array<string, array{en: string, hi: string}> */
    public static function categories(): array
    {
        return config('venues.amenity_categories', []);
    }

    /**
     * @param  array<string, mixed>|list<string>  $rawAmenities
     * @param  list<array<string, mixed>>|null  $customServices
     * @return array<string, array<string, mixed>>
     */
    public static function labels(array $rawAmenities, ?array $customServices = null): array
    {
        return array_merge(
            self::standardLabels($rawAmenities),
            self::customLabels($customServices ?? [])
        );
    }

    /** @param  array<string, mixed>|list<string>  $rawAmenities */
    public static function standardLabels(array $rawAmenities): array
    {
        $fields = self::fields();
        $labels = [];
        $map = self::normalize($rawAmenities);

        foreach ($map as $key => $value) {
            if (! isset($fields[$key])) {
                continue;
            }
            $labels[$key] = array_merge($fields[$key], [
                'value' => $value,
                'icon' => self::sanitizeIconKey($key),
            ]);
        }

        return $labels;
    }

    /** @param  list<array<string, mixed>>  $customServices */
    public static function customLabels(array $customServices): array
    {
        $labels = [];
        foreach ($customServices as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $id = (string) ($row['id'] ?? Str::lower(Str::random(8)));
            $key = 'custom_'.$id;
            $qty = isset($row['quantity']) ? (int) $row['quantity'] : null;
            $hasQty = $qty !== null && $qty > 0;

            $labels[$key] = [
                'type' => $hasQty ? 'count' : 'bool',
                'category' => 'custom',
                'custom' => true,
                'icon' => self::sanitizeIconKey($row['icon'] ?? null),
                'en' => $name,
                'hi' => $name,
                'note' => trim((string) ($row['note'] ?? '')) ?: null,
                'value' => $hasQty ? $qty : true,
            ];
        }

        return $labels;
    }

    /** @param  list<array<string, mixed>>|null  $raw */
    public static function sanitizeCustomServices(?array $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '' || strlen($id) > 64) {
                $id = Str::lower(Str::random(10));
            }
            $note = trim((string) ($row['note'] ?? ''));
            $qty = isset($row['quantity']) && $row['quantity'] !== '' && $row['quantity'] !== null
                ? max(0, (int) $row['quantity'])
                : null;

            $item = [
                'id' => $id,
                'name' => Str::limit($name, 120, ''),
                'icon' => self::sanitizeIconKey($row['icon'] ?? null),
            ];
            if ($note !== '') {
                $item['note'] = Str::limit($note, 255, '');
            }
            if ($qty !== null && $qty > 0) {
                $item['quantity'] = $qty;
            }
            $out[] = $item;
            if (count($out) >= 100) {
                break;
            }
        }

        return $out;
    }

    public static function sanitizeIconKey(mixed $raw): string
    {
        $key = is_string($raw) ? trim($raw) : '';
        $allowed = config('venues.service_icon_keys', []);

        return in_array($key, $allowed, true) ? $key : 'sparkle';
    }

    /** @param  array<string, mixed>|list<string>  $rawAmenities */
    private static function normalize(array $rawAmenities): array
    {
        if (! array_is_list($rawAmenities)) {
            return $rawAmenities;
        }

        $legacyMap = [
            'hall' => 'halls', 'rooms' => 'rooms', 'bathrooms' => 'bathrooms',
            'parking' => 'parking_cars', 'ac' => 'ac', 'stage' => 'stage',
            'kitchen' => 'kitchen', 'garden' => 'garden', 'wifi' => 'wifi',
            'generator' => 'generator', 'valet' => 'valet',
        ];
        $out = [];
        foreach ($rawAmenities as $key) {
            $mapped = $legacyMap[$key] ?? $key;
            $meta = self::fields()[$mapped] ?? null;
            if (! $meta) {
                continue;
            }
            $out[$mapped] = ($meta['type'] ?? '') === 'count' ? 1 : true;
        }

        return $out;
    }
}
