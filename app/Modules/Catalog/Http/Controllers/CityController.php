<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            City::query()->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json(City::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:cities,name'],
            'state' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $city = City::query()->create([
            'name' => $data['name'],
            'state' => $data['state'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($city, 201);
    }

    public function update(Request $request, City $city): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', 'unique:cities,name,'.$city->id],
            'state' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $city->update($data);

        return response()->json($city->fresh());
    }
}
