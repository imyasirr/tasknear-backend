<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\ProviderType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProviderTypes
{
    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        if (ProviderType::query()->exists()) {
            return ProviderType::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (ProviderType $row) => $row->toConfigRow());
        }

        return collect(config('marketplace.provider_types', []));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function active(): Collection
    {
        return $this->all()->filter(fn (array $row) => (bool) ($row['active'] ?? false))->values();
    }

    public function find(string $slug): ?array
    {
        return $this->all()->first(fn (array $row) => ($row['slug'] ?? '') === $slug);
    }

    public function roleFor(string $slug): string
    {
        return (string) ($this->find($slug)['role'] ?? $slug);
    }

    public function matchMode(string $slug): string
    {
        return (string) ($this->find($slug)['match_mode'] ?? 'vendor');
    }

    /** @return list<string> */
    public function registerRoles(): array
    {
        return $this->active()
            ->pluck('role')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isActive(string $slug): bool
    {
        return $this->active()->contains(fn (array $row) => ($row['slug'] ?? '') === $slug);
    }

    public function assertActive(string $slug): void
    {
        if (! $this->isActive($slug)) {
            throw ValidationException::withMessages([
                'provider_type' => 'This provider type is not available yet.',
            ]);
        }
    }

    public function assertRegisterRole(string $role): void
    {
        if ($role === 'customer') {
            return;
        }

        if (! in_array($role, $this->registerRoles(), true)) {
            throw ValidationException::withMessages([
                'role' => 'This account type is not available yet.',
            ]);
        }
    }
}
