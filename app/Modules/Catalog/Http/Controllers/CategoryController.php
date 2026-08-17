<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->where('is_active', true);

        $for = $request->query('for');
        if ($for === 'task') {
            $query->whereIn('vertical', ['task', 'both']);
        } elseif ($for === 'event') {
            $query->whereIn('vertical', ['event', 'both']);
        }

        return response()->json($query->orderBy('id')->get());
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json(Category::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:40', 'alpha_dash', 'unique:categories,slug'],
            'name' => ['required', 'string', 'max:120'],
            'name_hi' => ['nullable', 'string', 'max:120'],
            'vertical' => ['required', 'in:event,task,both'],
            'default_rate_inr' => ['required', 'integer', 'min:100', 'max:50000'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:30', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = Category::query()->create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'name_hi' => $data['name_hi'] ?? $data['name'],
            'vertical' => $data['vertical'],
            'default_rate_inr' => $data['default_rate_inr'],
            'default_duration_minutes' => $data['default_duration_minutes'] ?? 360,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['sometimes', 'string', 'max:40', 'alpha_dash', 'unique:categories,slug,'.$category->id],
            'name' => ['sometimes', 'string', 'max:120'],
            'name_hi' => ['nullable', 'string', 'max:120'],
            'vertical' => ['sometimes', 'in:event,task,both'],
            'default_rate_inr' => ['sometimes', 'integer', 'min:100', 'max:50000'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:30', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        return response()->json($category->fresh());
    }
}
