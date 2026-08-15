<?php

namespace App\Modules\Trust\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Trust\Models\Rating;
use App\Modules\Trust\Models\Report;
use App\Modules\Workers\Models\WorkerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustController extends Controller
{
    public function rate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_request_id' => ['required', 'exists:service_requests,id'],
            'assignment_id' => ['nullable', 'exists:assignments,id'],
            'ratee_id' => ['required', 'exists:users,id'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $rating = Rating::query()->updateOrCreate(
            [
                'service_request_id' => $data['service_request_id'],
                'rater_id' => $request->user()->id,
                'ratee_id' => $data['ratee_id'],
            ],
            [
                'assignment_id' => $data['assignment_id'] ?? null,
                'stars' => $data['stars'],
                'comment' => $data['comment'] ?? null,
            ]
        );

        $profile = WorkerProfile::query()->where('user_id', $data['ratee_id'])->first();
        if ($profile) {
            $avg = Rating::query()->where('ratee_id', $data['ratee_id'])->avg('stars');
            $profile->update(['rating_avg' => round((float) $avg, 2)]);
        }

        return response()->json($rating, 201);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reported_id' => ['required', 'exists:users,id'],
            'service_request_id' => ['nullable', 'exists:service_requests,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $report = Report::query()->create([
            'reporter_id' => $request->user()->id,
            'reported_id' => $data['reported_id'],
            'service_request_id' => $data['service_request_id'] ?? null,
            'reason' => $data['reason'],
            'status' => 'open',
        ]);

        return response()->json($report, 201);
    }
}
