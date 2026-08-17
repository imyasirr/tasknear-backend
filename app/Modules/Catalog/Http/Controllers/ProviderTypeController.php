<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ProviderTypes;
use Illuminate\Http\JsonResponse;

class ProviderTypeController extends Controller
{
    public function index(ProviderTypes $types): JsonResponse
    {
        return response()->json($types->all()->values());
    }
}
