<?php

namespace App\Modules\Ops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Marketplace\Models\CatererProfile;
use App\Modules\Events\Actions\ReplaceAssignmentAction;
use App\Modules\Events\Models\EventShift;
use App\Modules\Marketplace\Actions\AssignToRequestAction;
use App\Modules\Marketplace\Actions\AssignWorkerAction;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Marketplace\Services\MatchingSettings;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Models\Payout;
use App\Modules\Ops\Models\AuditLog;
use App\Modules\Ops\Models\NotificationLog;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Trust\Models\Report;
use App\Modules\Workers\Models\WorkerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'clients' => UserRole::query()->where('role', 'customer')->count(),
            'caterers_active' => CatererProfile::query()->where('status', 'active')->count(),
            'workers_active' => WorkerProfile::query()->where('status', 'active')->count(),
            'categories' => \App\Modules\Catalog\Models\Category::query()->where('is_active', true)->count(),
            'provider_types' => \App\Modules\Catalog\Models\ProviderType::query()->where('is_active', true)->count(),
            'events_open' => ServiceRequest::query()->where('type', 'event')->whereNotIn('status', ['completed', 'settled', 'cancelled'])->count(),
            'tasks_open' => ServiceRequest::query()->where('type', 'task')->whereNotIn('status', ['completed', 'settled', 'cancelled'])->count(),
            'payments_pending' => Payment::query()->where('status', 'pending')->count(),
            'reports_open' => Report::query()->where('status', 'open')->count(),
            'recent_bookings' => ServiceRequest::query()
                ->with(['eventDetail', 'taskDetail', 'requester', 'vendor.catererProfile', 'vendorOffers'])
                ->latest()
                ->limit(6)
                ->get()
                ->each(fn (ServiceRequest $row) => $row->presentVendor(false)),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json(
            User::query()->with(['roles', 'catererProfile.skills.category', 'workerProfile.skills.category'])->latest()->get()
        );
    }

    public function bookings(): JsonResponse
    {
        return response()->json(
            ServiceRequest::query()
                ->with([
                    'requester',
                    'eventDetail.shifts.category',
                    'eventDetail.shifts.assignments.worker',
                    'taskDetail',
                    'assignments.worker',
                    'payments',
                    'vendor.catererProfile',
                    'vendorOffers.caterer.catererProfile',
                    'vendorAttendance',
                    'payouts',
                ])
                ->latest()
                ->get()
                ->each(function (ServiceRequest $row) {
                    $row->presentVendor(false);
                    $row->presentAttendance(true);
                })
        );
    }

    public function rematch(Request $request, ServiceRequest $booking, AutoMatchAction $match): JsonResponse
    {
        $data = $request->validate([
            'ring_seconds' => ['nullable', 'integer', 'min:'.MatchingSettings::MIN_RING_SECONDS, 'max:'.MatchingSettings::MAX_RING_SECONDS],
        ]);

        return response()->json($match->handle(
            $booking,
            $request->user(),
            isset($data['ring_seconds']) ? (int) $data['ring_seconds'] : null
        ));
    }

    public function setWorkerStatus(Request $request, WorkerProfile $profile, Auditor $auditor): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,pending_kyc,suspended'],
        ]);
        $profile->update(['status' => $data['status']]);
        $auditor->record($request->user(), 'worker.status', $profile, $data);

        return response()->json($profile->fresh(['user', 'skills.category']));
    }

    public function resolveReport(Request $request, Report $report, Auditor $auditor): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,resolved,dismissed'],
        ]);
        $report->update(['status' => $data['status']]);
        $auditor->record($request->user(), 'report.updated', $report, $data);

        return response()->json($report->fresh(['reporter', 'reported']));
    }

    public function kyc(): JsonResponse
    {
        return response()->json(
            WorkerProfile::query()
                ->with(['user', 'documents', 'skills.category'])
                ->whereIn('status', ['pending_kyc', 'active', 'suspended'])
                ->latest()
                ->get()
        );
    }

    public function approveKyc(Request $request, WorkerProfile $profile, Auditor $auditor): JsonResponse
    {
        $profile->update(['status' => 'active']);
        $profile->documents()->where('status', 'pending')->update(['status' => 'approved']);
        $auditor->record($request->user(), 'kyc.approved', $profile);

        return response()->json($profile->fresh(['user', 'documents']));
    }

    public function rejectKyc(Request $request, WorkerProfile $profile, Auditor $auditor): JsonResponse
    {
        $data = $request->validate(['review_note' => ['nullable', 'string']]);
        $profile->update(['status' => 'pending_kyc']);
        $profile->documents()->where('status', 'pending')->update([
            'status' => 'rejected',
            'review_note' => $data['review_note'] ?? 'Rejected',
        ]);
        $auditor->record($request->user(), 'kyc.rejected', $profile, $data);

        return response()->json($profile->fresh(['user', 'documents']));
    }

    public function fillBoard(): JsonResponse
    {
        $shifts = EventShift::query()
            ->with(['category', 'eventDetail.serviceRequest', 'assignments.worker'])
            ->whereHas('eventDetail.serviceRequest', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'completed', 'settled']);
            })
            ->orderBy('start_at')
            ->get()
            ->map(function (EventShift $shift) {
                return [
                    'id' => $shift->id,
                    'title' => $shift->eventDetail?->title,
                    'city' => $shift->eventDetail?->serviceRequest?->city,
                    'status' => $shift->eventDetail?->serviceRequest?->status,
                    'category' => $shift->category,
                    'headcount' => $shift->headcount,
                    'filled' => $shift->filledCount(),
                    'open' => $shift->openSlots(),
                    'start_at' => $shift->start_at,
                    'end_at' => $shift->end_at,
                    'assignments' => $shift->assignments,
                ];
            });

        return response()->json($shifts);
    }

    public function eligibleWorkers(EventShift $shift): JsonResponse
    {
        $busyIds = Assignment::query()
            ->where('event_shift_id', $shift->id)
            ->whereNotIn('status', ['declined', 'cancelled', 'replaced'])
            ->pluck('worker_user_id');

        $workers = WorkerProfile::query()
            ->where('status', 'active')
            ->whereHas('skills', fn ($q) => $q->where('category_id', $shift->category_id))
            ->whereNotIn('user_id', $busyIds)
            ->with(['user', 'skills.category'])
            ->orderByDesc('rating_avg')
            ->get();

        return response()->json($workers);
    }

    public function assign(Request $request, EventShift $shift, AssignWorkerAction $action): JsonResponse
    {
        $data = $request->validate([
            'worker_user_id' => ['required', 'exists:users,id'],
        ]);

        $worker = User::query()->findOrFail($data['worker_user_id']);

        return response()->json($action->handle($shift, $worker, $request->user()), 201);
    }

    public function replace(Request $request, Assignment $assignment, ReplaceAssignmentAction $action): JsonResponse
    {
        $data = $request->validate([
            'worker_user_id' => ['required', 'exists:users,id'],
            'reason' => ['nullable', 'string'],
        ]);

        $worker = User::query()->findOrFail($data['worker_user_id']);

        return response()->json(
            $action->handle($assignment, $worker, $request->user(), $data['reason'] ?? 'Replacement'),
            201
        );
    }

    public function taskBoard(): JsonResponse
    {
        $tasks = ServiceRequest::query()
            ->where('type', 'task')
            ->whereNotIn('status', ['cancelled', 'completed', 'settled'])
            ->with(['taskDetail', 'assignments.worker'])
            ->latest()
            ->get()
            ->map(function (ServiceRequest $task) {
                $filled = $task->assignments->whereNotIn('status', ['declined', 'cancelled', 'replaced'])->count();

                return [
                    'id' => $task->id,
                    'title' => $task->taskDetail?->title,
                    'city' => $task->city,
                    'status' => $task->status,
                    'required_workers' => $task->required_workers,
                    'filled' => $filled,
                    'open' => max(0, $task->required_workers - $filled),
                    'assignments' => $task->assignments,
                ];
            });

        return response()->json($tasks);
    }

    public function eligibleTaskWorkers(ServiceRequest $task): JsonResponse
    {
        abort_unless($task->type === 'task', 404);
        $busyIds = $task->assignments()
            ->whereNotIn('status', ['declined', 'cancelled', 'replaced'])
            ->pluck('worker_user_id');

        $workers = WorkerProfile::query()
            ->where('status', 'active')
            ->whereNotIn('user_id', $busyIds)
            ->with(['user', 'skills.category'])
            ->orderByDesc('rating_avg')
            ->get();

        return response()->json($workers);
    }

    public function assignTask(Request $request, ServiceRequest $task, AssignToRequestAction $action): JsonResponse
    {
        abort_unless($task->type === 'task', 404);
        $data = $request->validate([
            'worker_user_id' => ['required', 'exists:users,id'],
        ]);

        return response()->json(
            $action->handle($task, User::query()->findOrFail($data['worker_user_id']), $request->user()),
            201
        );
    }

    public function notifications(): JsonResponse
    {
        return response()->json(
            NotificationLog::query()->with('user')->latest()->limit(50)->get()
        );
    }

    public function reports(): JsonResponse
    {
        return response()->json(
            Report::query()->with(['reporter', 'reported', 'payout', 'serviceRequest.eventDetail', 'serviceRequest.taskDetail'])->latest()->get()
        );
    }

    public function payouts(SettlePaymentAction $settle): JsonResponse
    {
        $settle->releaseDuePayouts();

        return response()->json(
            Payout::query()
                ->with([
                    'worker.catererProfile',
                    'serviceRequest.eventDetail',
                    'serviceRequest.taskDetail',
                    'serviceRequest.requester',
                    'assignment.shift.category',
                    'assignment.serviceRequest.eventDetail',
                    'assignment.serviceRequest.taskDetail',
                    'assignment.serviceRequest.requester',
                ])
                ->latest()
                ->get()
        );
    }

    public function sendPayout(Request $request, Payout $payout, Auditor $auditor): JsonResponse
    {
        $payout->update([
            'status' => 'sent',
            'paid_at' => $payout->paid_at ?: now(),
            'gateway_transfer_id' => $payout->gateway_transfer_id ?: 'upi-t1-'.$payout->id,
        ]);
        $auditor->record($request->user(), 'payout.sent', $payout);

        return response()->json($payout->fresh([
            'worker.catererProfile',
            'serviceRequest.eventDetail',
            'serviceRequest.taskDetail',
            'serviceRequest.requester',
        ]));
    }

    public function releasePayout(Request $request, Payout $payout, Auditor $auditor): JsonResponse
    {
        $payout->update([
            'status' => 'confirmed',
            'paid_at' => $payout->paid_at ?: now(),
            'confirmed_at' => now(),
            'disputed_at' => null,
            'gateway_transfer_id' => $payout->gateway_transfer_id ?: 'upi-dev-'.$payout->id,
        ]);
        $auditor->record($request->user(), 'payout.confirmed', $payout);

        if ($payout->id) {
            Report::query()
                ->where('payout_id', $payout->id)
                ->where('status', 'open')
                ->update(['status' => 'resolved']);
        }

        return response()->json($payout->fresh([
            'worker.catererProfile',
            'serviceRequest.eventDetail',
            'serviceRequest.taskDetail',
            'serviceRequest.requester',
            'assignment.serviceRequest.requester',
        ]));
    }

    public function audit(): JsonResponse
    {
        return response()->json(
            AuditLog::query()->with('actor')->latest()->limit(100)->get()
        );
    }
}
