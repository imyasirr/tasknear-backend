<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Models\Attendance;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Ops\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    public function mine(Request $request, AutoMatchAction $match): JsonResponse
    {
        $match->sweepAndRefill();

        $jobs = Assignment::query()
            ->where('worker_user_id', $request->user()->id)
            ->with([
                'serviceRequest.eventDetail',
                'serviceRequest.taskDetail',
                'serviceRequest.requester',
                'shift.category',
                'attendance',
                'payout',
            ])
            ->latest()
            ->get();

        $jobs->each(function (Assignment $assignment) {
            $assignment->attendance?->makeHidden(['check_in_otp', 'check_out_otp']);
        });

        return response()->json($jobs);
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $this->assertOwner($request, $assignment);

        $assignment->load([
            'serviceRequest.eventDetail.shifts.category',
            'serviceRequest.taskDetail.category',
            'serviceRequest.requester',
            'serviceRequest.payments',
            'shift.category',
            'attendance',
            'payout',
        ]);

        return response()->json($assignment);
    }

    public function accept(Request $request, Assignment $assignment, Auditor $auditor, AutoMatchAction $match): JsonResponse
    {
        $this->assertOwner($request, $assignment);

        $profile = $request->user()->workerProfile;
        if (! $profile?->isActive()) {
            throw ValidationException::withMessages(['assignment' => 'KYC must be approved first.']);
        }

        $fresh = DB::transaction(function () use ($assignment, $match) {
            $siblings = Assignment::query()
                ->where('service_request_id', $assignment->service_request_id)
                ->when($assignment->event_shift_id, fn ($q) => $q->where('event_shift_id', $assignment->event_shift_id))
                ->lockForUpdate()
                ->get();

            $row = $siblings->firstWhere('id', $assignment->id) ?? Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $match->expireStale($row->serviceRequest);

            $row->refresh();
            if ($row->status !== 'invited' || ($row->expires_at && $row->expires_at->isPast())) {
                if ($row->status === 'invited') {
                    $row->update(['status' => 'expired', 'responded_at' => now()]);
                }
                throw ValidationException::withMessages(['assignment' => 'This offer expired. Another worker may have taken it.']);
            }

            $needed = $this->slotsNeeded($row->load(['shift', 'serviceRequest']));
            if ($needed < 1) {
                $row->update(['status' => 'expired', 'responded_at' => now()]);
                throw ValidationException::withMessages(['assignment' => 'Someone else accepted this job first.']);
            }

            $row->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            Attendance::query()->firstOrCreate(
                ['assignment_id' => $row->id],
                [
                    'check_in_otp' => (string) random_int(1000, 9999),
                    'check_out_otp' => (string) random_int(1000, 9999),
                ]
            );

            return $row->fresh();
        });

        $match->confirmIfFilled($fresh->serviceRequest, $request->user());
        $auditor->record($request->user(), 'assignment.accepted', $fresh);

        return response()->json($fresh->fresh(['shift.category', 'serviceRequest.eventDetail', 'attendance']));
    }

    public function decline(Request $request, Assignment $assignment, Auditor $auditor, AutoMatchAction $match): JsonResponse
    {
        $this->assertOwner($request, $assignment);

        $assignment->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
        $auditor->record($request->user(), 'assignment.declined', $assignment);
        $match->refill($assignment, $request->user());

        return response()->json($assignment->fresh());
    }

    public function checkIn(Request $request, Assignment $assignment, Auditor $auditor): JsonResponse
    {
        $this->assertOwner($request, $assignment);
        $data = $request->validate(['otp' => ['required', 'string']]);
        $attendance = $assignment->attendance;

        if (! $attendance || $attendance->check_in_otp !== $data['otp']) {
            throw ValidationException::withMessages(['otp' => 'Wrong check-in OTP.']);
        }

        $assignment->update(['status' => 'checked_in']);
        $attendance->update(['check_in_at' => now()]);
        $assignment->serviceRequest->transitionTo('in_progress', $request->user(), 'Worker checked in');
        $auditor->record($request->user(), 'assignment.checked_in', $assignment);

        return response()->json($assignment->fresh('attendance'));
    }

    public function checkOut(Request $request, Assignment $assignment, Auditor $auditor, SettlePaymentAction $settle): JsonResponse
    {
        $this->assertOwner($request, $assignment);
        $data = $request->validate(['otp' => ['required', 'string']]);
        $attendance = $assignment->attendance;

        if (! $attendance || $attendance->check_out_otp !== $data['otp']) {
            throw ValidationException::withMessages(['otp' => 'Wrong check-out OTP.']);
        }

        $assignment->update(['status' => 'checked_out']);
        $attendance->update(['check_out_at' => now()]);
        $settle->createPayoutsForRequest($assignment->serviceRequest);
        $auditor->record($request->user(), 'assignment.checked_out', $assignment);

        $open = $assignment->serviceRequest->assignments()
            ->whereNotIn('status', ['checked_out', 'declined', 'cancelled', 'replaced'])
            ->count();
        if ($open === 0) {
            $assignment->serviceRequest->transitionTo('completed', $request->user(), 'All workers checked out');
        }

        return response()->json($assignment->fresh(['attendance', 'payout']));
    }

    private function assertOwner(Request $request, Assignment $assignment): void
    {
        if ((int) $assignment->worker_user_id !== (int) $request->user()->id) {
            abort(403);
        }
    }

    private function slotsNeeded(Assignment $assignment): int
    {
        $request = $assignment->serviceRequest;
        if (! $request) {
            return 0;
        }

        if ($assignment->event_shift_id && $assignment->shift) {
            return $assignment->shift->openSlots();
        }

        $committed = $request->assignments()->whereIn('status', Assignment::COMMITTED)->count();

        return max(0, $request->required_workers - $committed);
    }
}
