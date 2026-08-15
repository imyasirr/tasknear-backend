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
}
