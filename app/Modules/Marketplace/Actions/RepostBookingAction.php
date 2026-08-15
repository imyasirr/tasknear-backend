<?php

namespace App\Modules\Marketplace\Actions;

use App\Models\User;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Marketplace\Models\VendorOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepostBookingAction
{
    public function __construct(private AutoMatchAction $match) {}

    public function handle(ServiceRequest $request, User $client, array $data): ServiceRequest
    {
        if ((int) $request->requester_id !== (int) $client->id) {
            abort(403);
        }

        if ($request->status !== 'unmatched') {
            throw ValidationException::withMessages(['booking' => 'Only unmatched bookings can be reposted.']);
        }

        if (! $request->payments()->where('status', 'paid')->exists()) {
            throw ValidationException::withMessages(['booking' => 'Deposit must be paid before reposting.']);
        }

        return DB::transaction(function () use ($request, $client, $data) {
            $request->update([
                'scheduled_start' => $data['scheduled_start'],
                'scheduled_end' => $data['scheduled_end'] ?? $data['scheduled_start'],
            ]);

            VendorOffer::query()
                ->where('service_request_id', $request->id)
                ->where('status', 'invited')
                ->update(['status' => 'expired', 'responded_at' => now()]);

            $request->transitionTo('matching', $client, 'Client reposted for a new date');

            return $this->match->handle(
                $request->fresh(['eventDetail.shifts.category', 'taskDetail', 'payments', 'assignments']),
                $client
            );
        });
    }
}
