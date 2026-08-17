<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Marketplace\Actions\RepostBookingAction;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Tasks\Actions\CreateTaskAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ServiceRequest::query()
            ->where('type', 'task')
            ->with(['taskDetail.category', 'payments', 'assignments.worker', 'vendor.catererProfile', 'vendorOffers']);

        if (! $user->hasRole('admin')) {
            $query->where('requester_id', $user->id);
        }

        return response()->json($query->latest()->get()->each(function (ServiceRequest $row) {
            $row->presentVendor();
            $row->presentWorkerRing();
        }));
    }

    public function store(Request $request, CreateTaskAction $action): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:80'],
            'category_id' => ['required', 'exists:categories,id'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'drop_address' => ['nullable', 'string', 'max:255'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            'required_workers' => ['nullable', 'integer', 'min:1', 'max:10'],
            'rate_per_worker_inr' => ['nullable', 'integer', 'min:100', 'max:50000'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'proof_required' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'provider_type' => ['nullable', 'string', 'max:32'],
        ]);

        return response()->json($action->handle($request->user(), $data), 201);
    }

    public function show(Request $request, ServiceRequest $task, AutoMatchAction $match): JsonResponse
    {
        abort_unless($task->type === 'task', 404);

        $user = $request->user();
        $isWorker = $task->assignments()->where('worker_user_id', $user->id)->exists();
        $isVendor = (int) $task->vendor_user_id === (int) $user->id;
        if ((int) $task->requester_id !== (int) $user->id && ! $user->hasRole('admin') && ! $isWorker && ! $isVendor) {
            abort(403);
        }

        $match->closeUnfilledBookings();
        $task->refresh();

        $task->load(['taskDetail.category', 'payments', 'assignments.worker.workerProfile', 'assignments.attendance', 'vendor.catererProfile', 'vendorOffers', 'vendorAttendance', 'payouts']);

        $task->assignments->each(function ($assignment) {
            if ($assignment->status === 'invited') {
                $assignment->setRelation('worker', null);
            }
        });

        $hideOtp = ! $user->hasRole('admin') && (int) $task->requester_id !== (int) $user->id;
        if ($hideOtp) {
            $task->assignments->each(function ($assignment) {
                $assignment->attendance?->makeHidden(['check_in_otp', 'check_out_otp']);
            });
        }

        $task->presentVendor();
        $task->presentClientCrew(! $hideOtp);
        $task->presentWorkerRing();
        $task->presentAttendance(! $hideOtp);

        return response()->json($task);
    }

    public function repost(Request $request, ServiceRequest $task, RepostBookingAction $action): JsonResponse
    {
        abort_unless($task->type === 'task', 404);

        $user = $request->user();
        if ((int) $task->requester_id !== (int) $user->id && ! $user->hasRole('admin')) {
            abort(403);
        }

        $data = $request->validate([
            'scheduled_start' => ['required', 'date', 'after:now'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
        ]);

        return response()->json($action->handle($task, $request->user(), $data));
    }
}
