<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Actions\CreateEventAction;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Marketplace\Actions\RepostBookingAction;
use App\Modules\Marketplace\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ServiceRequest::query()
            ->where('type', 'event')
            ->with(['eventDetail.shifts.category', 'payments', 'assignments.worker', 'vendor.catererProfile', 'vendorOffers']);

        if (! $user->hasRole('admin')) {
            $query->where('requester_id', $user->id);
        }

        return response()->json($query->latest()->get()->each->presentVendor());
    }

    public function store(Request $request, CreateEventAction $action): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'venue_name' => ['nullable', 'string', 'max:160'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'dress_code' => ['nullable', 'string', 'max:80'],
            'meal_included' => ['sometimes', 'boolean'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'notes' => ['nullable', 'string'],
            'shifts' => ['required', 'array', 'min:1'],
            'shifts.*.category_id' => ['required', 'exists:categories,id'],
            'shifts.*.headcount' => ['required', 'integer', 'min:1', 'max:200'],
            'shifts.*.rate_per_worker_inr' => ['nullable', 'integer', 'min:100', 'max:50000'],
            'shifts.*.start_at' => ['nullable', 'date'],
            'shifts.*.end_at' => ['nullable', 'date'],
        ]);

        return response()->json($action->handle($request->user(), $data), 201);
    }

    public function show(Request $request, ServiceRequest $event, AutoMatchAction $match): JsonResponse
    {
        abort_unless($event->type === 'event', 404);
        $this->authorizeView($request, $event);
        $match->closeUnfilledBookings();
        $event->refresh();

        $event->load([
            'eventDetail.shifts.category',
            'eventDetail.shifts.assignments.attendance',
            'eventDetail.shifts.assignments.worker',
            'payments',
            'assignments.attendance',
            'assignments.worker.workerProfile',
            'vendor.catererProfile',
            'vendorOffers',
            'vendorAttendance',
            'payouts',
        ]);

        $hideOtp = ! $request->user()->hasRole('admin') && (int) $event->requester_id !== (int) $request->user()->id;
        $mask = function ($assignment) use ($hideOtp) {
            if ($assignment->status === 'invited') {
                $assignment->setRelation('worker', null);
            }
            if ($hideOtp) {
                $assignment->attendance?->makeHidden(['check_in_otp', 'check_out_otp']);
            }
        };
        $event->eventDetail?->shifts?->each(function ($shift) use ($mask) {
            $shift->assignments->each($mask);
        });
        $event->assignments->each($mask);
        $event->presentVendor();
        $event->presentAttendance(! $hideOtp);

        return response()->json($event);
    }

    public function repost(Request $request, ServiceRequest $event, RepostBookingAction $action): JsonResponse
    {
        abort_unless($event->type === 'event', 404);
        $this->authorizeView($request, $event);

        $data = $request->validate([
            'scheduled_start' => ['required', 'date', 'after:now'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
        ]);

        return response()->json($action->handle($event, $request->user(), $data));
    }

    private function authorizeView(Request $request, ServiceRequest $event): void
    {
        $user = $request->user();
        $isWorker = $event->assignments()->where('worker_user_id', $user->id)->exists();
        $isVendor = (int) $event->vendor_user_id === (int) $user->id;

        if ((int) $event->requester_id !== (int) $user->id && ! $user->hasRole('admin') && ! $isWorker && ! $isVendor) {
            abort(403);
        }
    }
}
