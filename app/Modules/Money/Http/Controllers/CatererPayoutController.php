<?php

namespace App\Modules\Money\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Money\Models\Payout;
use App\Modules\Ops\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatererPayoutController extends Controller
{
    public function index(Request $request, SettlePaymentAction $settle): JsonResponse
    {
        $settle->releaseDuePayouts();

        return response()->json(
            Payout::query()
                ->where('worker_user_id', $request->user()->id)
                ->with(['serviceRequest.eventDetail', 'serviceRequest.taskDetail'])
                ->latest()
                ->get()
        );
    }

    public function confirm(Request $request, Payout $payout, Auditor $auditor): JsonResponse
    {
        if ((int) $payout->worker_user_id !== (int) $request->user()->id) {
            abort(403);
        }

        if (! in_array($payout->status, ['sent', 'pending'], true)) {
            throw ValidationException::withMessages(['payout' => 'This settlement is not sent yet.']);
        }

        $payout->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'disputed_at' => null,
        ]);
        $auditor->record($request->user(), 'payout.confirmed', $payout);

        return response()->json($payout->fresh(['serviceRequest.eventDetail', 'serviceRequest.taskDetail']));
    }
}
