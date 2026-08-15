<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Events\Models\EventDetail;
use App\Modules\Marketplace\Models\CatererProfile;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Marketplace\Models\VendorAttendance;
use App\Modules\Marketplace\Models\VendorOffer;
use App\Modules\Money\Models\Commission;
use App\Modules\Money\Models\LedgerEntry;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Models\Payout;
use App\Modules\Money\Services\Pricing;
use App\Modules\Ops\Models\AuditLog;
use App\Modules\Ops\Models\NotificationLog;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Tasks\Models\TaskDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('phone', '9999999999')->firstOrFail();

        $ayesha = $this->user('9000000001', 'Ayesha Khan', 'customer', 'Lucknow');
        $vikram = $this->user('9000000002', 'Vikram Singh', 'customer', 'Lucknow');

        $this->subscribe($ayesha, 'Pro', 10, 80);

        $royal = $this->caterer('9222222221', 'Royal Kitchen Co', ['waiter', 'helper', 'cleaner'], [
            'bio' => 'Full banquet crew for weddings and receptions in Lucknow.',
            'rating_avg' => 4.8,
            'jobs_completed' => 18,
            'gstin' => '09AABCR1234F1Z5',
            'upi_vpa' => 'royal@okaxis',
        ]);
        $banquet = $this->caterer('9222222222', 'Banquet Hands', ['waiter', 'helper', 'loader'], [
            'bio' => 'Service and loading crew for offices and lawns.',
            'rating_avg' => 4.5,
            'jobs_completed' => 11,
            'gstin' => '09AABCB5678G1Z2',
            'upi_vpa' => 'banquet@oksbi',
        ]);
        $gomti = $this->caterer('9222222223', 'Gomti Servers', ['waiter', 'helper'], [
            'bio' => 'Waiters and helpers for mid-size events.',
            'rating_avg' => 4.3,
            'jobs_completed' => 7,
            'upi_vpa' => 'gomti@okicici',
        ]);
        $loaders = $this->caterer('9222222224', 'Aliganj Loaders', ['loader', 'helper'], [
            'bio' => 'House-shift and loading crew. Same-day jobs.',
            'rating_avg' => 4.1,
            'jobs_completed' => 5,
            'upi_vpa' => 'loaders@paytm',
        ]);
        $this->caterer('9222222225', 'Night Owl Catering', ['waiter', 'cleaner'], [
            'bio' => 'Late-night crew. Currently offline.',
            'rating_avg' => 4.0,
            'jobs_completed' => 3,
            'is_available' => false,
            'upi_vpa' => 'nightowl@okaxis',
        ]);

        $wedding = $this->event($ayesha, [
            'title' => 'Sharma wedding reception',
            'venue_name' => 'Gomti Nagar banquet',
            'city' => 'Lucknow',
            'address' => 'Gomti Nagar, Lucknow',
            'guest_count' => 350,
            'start' => now()->addDays(2)->setTime(18, 0),
            'end' => now()->addDays(2)->setTime(23, 30),
            'status' => 'matching',
            'notes' => 'White shirt, black trousers. Staff meal included.',
            'shifts' => [
                ['slug' => 'waiter', 'headcount' => 6],
                ['slug' => 'helper', 'headcount' => 3],
                ['slug' => 'cleaner', 'headcount' => 2],
            ],
            'history' => [
                [null, 'draft', 'Event created'],
                ['draft', 'awaiting_payment', 'Deposit raised'],
                ['awaiting_payment', 'matching', 'Paid. Ringing catering companies'],
            ],
        ], paid: true);
        $this->offer($wedding, $royal, $admin, 'invited', minutes: 40);
        $this->offer($wedding, $banquet, $admin, 'invited', minutes: 40);
        $this->offer($wedding, $gomti, $admin, 'invited', minutes: 40);
        $this->notify($royal, 'vendor.ringing', $wedding);
        $this->notify($banquet, 'vendor.ringing', $wedding);
        $this->notify($gomti, 'vendor.ringing', $wedding);

        $this->event($ayesha, [
            'title' => 'Corporate lunch — bank office',
            'venue_name' => 'Hazratganj office',
            'city' => 'Lucknow',
            'address' => 'Hazratganj, Lucknow',
            'guest_count' => 80,
            'start' => now()->addDays(5)->setTime(12, 0),
            'end' => now()->addDays(5)->setTime(16, 0),
            'status' => 'awaiting_payment',
            'notes' => 'Pay deposit to notify catering companies.',
            'shifts' => [
                ['slug' => 'waiter', 'headcount' => 4],
            ],
            'history' => [
                [null, 'draft', 'Event created'],
                ['draft', 'awaiting_payment', 'Waiting for deposit'],
            ],
        ], paid: false);

        $tonight = $this->event($ayesha, [
            'title' => 'Tonight banquet service',
            'venue_name' => 'Indira Nagar lawn',
            'city' => 'Lucknow',
            'address' => 'Indira Nagar, Lucknow',
            'guest_count' => 120,
            'start' => now()->setTime(18, 0),
            'end' => now()->setTime(23, 0),
            'status' => 'in_progress',
            'notes' => 'Royal Kitchen Co is on site.',
            'shifts' => [
                ['slug' => 'waiter', 'headcount' => 2],
            ],
            'history' => [
                [null, 'draft', 'Event created'],
                ['draft', 'awaiting_payment', 'Deposit raised'],
                ['awaiting_payment', 'matching', 'Paid. Ringing catering companies'],
                ['matching', 'confirmed', 'Royal Kitchen Co accepted'],
                ['confirmed', 'in_progress', 'Event started'],
            ],
        ], paid: true);
        $this->offer($tonight, $royal, $admin, 'accepted', hoursAgo: 6);
        $this->offer($tonight, $banquet, $admin, 'expired', hoursAgo: 6);
        $this->attendance($tonight, $royal, '4821', '7390', now()->subHour());

        $done = $this->event($ayesha, [
            'title' => 'Last week mehendi',
            'venue_name' => 'Aliganj home',
            'city' => 'Lucknow',
            'address' => 'Aliganj, Lucknow',
            'guest_count' => 60,
            'start' => now()->subDays(6)->setTime(16, 0),
            'end' => now()->subDays(6)->setTime(22, 0),
            'status' => 'completed',
            'notes' => 'Completed by Banquet Hands.',
            'shifts' => [
                ['slug' => 'waiter', 'headcount' => 2],
                ['slug' => 'cleaner', 'headcount' => 1],
            ],
            'history' => [
                [null, 'draft', 'Event created'],
                ['draft', 'awaiting_payment', 'Deposit raised'],
                ['awaiting_payment', 'matching', 'Paid. Ringing catering companies'],
                ['matching', 'confirmed', 'Banquet Hands accepted'],
                ['confirmed', 'in_progress', 'Event started'],
                ['in_progress', 'completed', 'Event finished'],
            ],
        ], paid: true);
        $this->offer($done, $banquet, $admin, 'accepted', hoursAgo: 150);
        $this->offer($done, $royal, $admin, 'declined', hoursAgo: 150);
        $this->attendance($done, $banquet, '1560', '8842', now()->subDays(6)->setTime(16, 10), now()->subDays(6)->setTime(22, 5));
        $this->vendorPayout($done, $banquet, 'sent', now()->subDays(5));

        $task = $this->task($vikram, [
            'title' => 'Fridge shift to new flat',
            'description' => 'Need 2 people to move a double-door fridge down one floor.',
            'city' => 'Lucknow',
            'pickup' => 'Aliganj Sector H',
            'drop' => 'Gomti Nagar Ext',
            'start' => now()->addDay()->setTime(10, 0),
            'end' => now()->addDay()->setTime(12, 0),
            'headcount' => 2,
            'status' => 'matching',
            'notes' => 'Lift is narrow. Carry from the stairs.',
            'history' => [
                [null, 'draft', 'Task created'],
                ['draft', 'awaiting_payment', 'Deposit raised'],
                ['awaiting_payment', 'matching', 'Paid. Ringing catering companies'],
            ],
        ], paid: true);
        $this->offer($task, $banquet, $admin, 'invited', minutes: 40);
        $this->offer($task, $loaders, $admin, 'invited', minutes: 40);
        $this->notify($banquet, 'vendor.ringing', $task);
        $this->notify($loaders, 'vendor.ringing', $task);

        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'action' => 'demo.seeded',
            'subject_type' => ServiceRequest::class,
            'subject_id' => $wedding->id,
            'payload' => ['note' => 'Client + caterer marketplace demo loaded'],
        ]);
    }

    private function subscribe(User $user, string $planName, int $startedDaysAgo, int $daysLeft): void
    {
        $plan = SubscriptionPlan::query()->where('name', $planName)->first();
        if (! $plan) {
            return;
        }

        Subscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'amount_inr' => $plan->price_inr,
            'gateway' => 'manual',
            'gateway_payment_id' => 'sub-demo-'.$user->phone,
            'starts_at' => now()->subDays($startedDaysAgo),
            'ends_at' => now()->addDays($daysLeft),
        ]);
    }

    private function user(string $phone, string $name, string $role, string $city): User
    {
        $user = User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'email' => $phone.'@tasknear.local',
                'password' => Hash::make('password'),
                'password_set_at' => now(),
                'city' => $city,
                'locale' => 'en',
            ]
        );
        $user->assignRole($role);

        return $user;
    }

    private function caterer(string $phone, string $name, array $skills, array $extra = []): User
    {
        $user = $this->user($phone, $name, 'caterer', 'Lucknow');
        $profile = CatererProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => $name,
                'bio' => $extra['bio'] ?? $name.' — Lucknow catering company',
                'city' => 'Lucknow',
                'gstin' => $extra['gstin'] ?? null,
                'upi_vpa' => $extra['upi_vpa'] ?? null,
                'rating_avg' => $extra['rating_avg'] ?? 4.4,
                'jobs_completed' => $extra['jobs_completed'] ?? 6,
                'status' => 'active',
                'is_available' => $extra['is_available'] ?? true,
            ]
        );
        $ids = Category::query()->whereIn('slug', $skills)->pluck('id');
        $profile->skills()->whereNotIn('category_id', $ids)->delete();
        foreach ($ids as $id) {
            $profile->skills()->firstOrCreate(['category_id' => $id]);
        }

        return $user->fresh('catererProfile');
    }

    private function offer(
        ServiceRequest $request,
        User $caterer,
        User $admin,
        string $status,
        ?int $minutes = null,
        int $hoursAgo = 0,
    ): VendorOffer {
        if ($status === 'accepted') {
            $request->update(['vendor_user_id' => $caterer->id]);
        }

        $invitedAt = now()->subHours(max(1, $hoursAgo))->subMinutes(5);

        return VendorOffer::query()->create([
            'service_request_id' => $request->id,
            'caterer_user_id' => $caterer->id,
            'status' => $status,
            'assigned_by' => $admin->id,
            'invited_at' => $invitedAt,
            'expires_at' => $status === 'invited'
                ? now()->addMinutes($minutes ?? 40)
                : $invitedAt->copy()->addMinutes(3),
            'responded_at' => $status === 'invited' ? null : $invitedAt->copy()->addMinutes(2),
        ]);
    }

    private function event(User $client, array $data, bool $paid): ServiceRequest
    {
        $budget = 0;
        $headcount = 0;
        foreach ($data['shifts'] as $shift) {
            $cat = Category::query()->where('slug', $shift['slug'])->firstOrFail();
            $budget += $cat->default_rate_inr * $shift['headcount'];
            $headcount += $shift['headcount'];
        }

        $request = ServiceRequest::query()->create([
            'requester_id' => $client->id,
            'type' => 'event',
            'slug' => ServiceRequest::uniqueSlug($data['title']),
            'city' => $data['city'],
            'address' => $data['address'],
            'scheduled_start' => $data['start'],
            'scheduled_end' => $data['end'],
            'budget_inr' => $budget,
            'required_workers' => $headcount,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        $details = EventDetail::query()->create([
            'service_request_id' => $request->id,
            'title' => $data['title'],
            'venue_name' => $data['venue_name'],
            'guest_count' => $data['guest_count'],
            'dress_code' => 'Black trousers, white shirt',
            'meal_included' => true,
        ]);

        $shiftStatus = match ($data['status']) {
            'completed' => 'completed',
            'in_progress', 'confirmed' => 'confirmed',
            default => 'open',
        };

        foreach ($data['shifts'] as $shift) {
            $cat = Category::query()->where('slug', $shift['slug'])->firstOrFail();
            $details->shifts()->create([
                'category_id' => $cat->id,
                'headcount' => $shift['headcount'],
                'start_at' => $data['start'],
                'end_at' => $data['end'],
                'rate_per_worker_inr' => $cat->default_rate_inr,
                'status' => $shiftStatus,
            ]);
        }

        $this->payment($request, $client, $budget, $paid);
        $this->history($request, $client, $data['history'] ?? []);

        return $request->fresh(['eventDetail.shifts.category']);
    }

    private function task(User $client, array $data, bool $paid): ServiceRequest
    {
        $cat = Category::query()->where('slug', 'loader')->firstOrFail();
        $headcount = (int) $data['headcount'];
        $budget = $cat->default_rate_inr * $headcount;
        $request = ServiceRequest::query()->create([
            'requester_id' => $client->id,
            'type' => 'task',
            'slug' => ServiceRequest::uniqueSlug($data['title']),
            'city' => $data['city'],
            'address' => $data['pickup'],
            'scheduled_start' => $data['start'],
            'scheduled_end' => $data['end'],
            'budget_inr' => $budget,
            'required_workers' => $headcount,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);
        TaskDetail::query()->create([
            'service_request_id' => $request->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'pickup_address' => $data['pickup'],
            'drop_address' => $data['drop'],
            'duration_minutes' => 120,
            'rate_per_worker_inr' => $cat->default_rate_inr,
            'proof_required' => false,
        ]);
        $this->payment($request, $client, $budget, $paid);
        $this->history($request, $client, $data['history'] ?? []);

        return $request->fresh('taskDetail');
    }

    private function payment(ServiceRequest $request, User $payer, int $amount, bool $paid): void
    {
        $quote = app(Pricing::class)->quote($payer, $amount);
        $payment = Payment::query()->create([
            'service_request_id' => $request->id,
            'payer_id' => $payer->id,
            'amount_inr' => $quote['total_inr'],
            'labor_inr' => $quote['labor_inr'],
            'commission_inr' => $quote['commission_inr'],
            'commission_bps' => $quote['commission_bps'],
            'fee_waived' => $quote['fee_waived'],
            'subscription_id' => $quote['subscription_id'],
            'gateway' => 'manual',
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? now() : null,
            'gateway_payment_id' => $paid ? 'demo-'.$request->id : null,
        ]);
        if ($paid) {
            Commission::query()->create([
                'payment_id' => $payment->id,
                'rate_bps' => $quote['commission_bps'],
                'amount_inr' => $quote['commission_inr'],
                'waived' => $quote['fee_waived'],
                'subscription_id' => $quote['subscription_id'],
            ]);
            LedgerEntry::query()->create([
                'account_type' => 'platform',
                'direction' => 'credit',
                'amount_inr' => $quote['total_inr'],
                'entry_type' => 'payment',
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
            ]);
        }
    }

    private function history(ServiceRequest $request, User $actor, array $steps): void
    {
        foreach ($steps as [$from, $to, $note]) {
            $request->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor->id,
                'note' => $note,
            ]);
        }
    }

    private function attendance(
        ServiceRequest $request,
        User $caterer,
        string $inOtp,
        string $outOtp,
        $checkedInAt = null,
        $checkedOutAt = null,
    ): VendorAttendance {
        return VendorAttendance::query()->create([
            'service_request_id' => $request->id,
            'vendor_user_id' => $caterer->id,
            'check_in_otp' => $inOtp,
            'check_out_otp' => $outOtp,
            'check_in_at' => $checkedInAt,
            'check_out_at' => $checkedOutAt,
        ]);
    }

    private function vendorPayout(ServiceRequest $request, User $caterer, string $status, $dueAt): Payout
    {
        $payment = $request->payments()->first();
        $labor = (int) ($payment?->labor_inr ?: $request->budget_inr);
        $sent = in_array($status, ['sent', 'confirmed'], true);

        $payout = Payout::query()->create([
            'worker_user_id' => $caterer->id,
            'service_request_id' => $request->id,
            'amount_inr' => $labor,
            'upi_vpa' => $caterer->catererProfile?->upi_vpa,
            'status' => $status,
            'due_at' => $dueAt,
            'paid_at' => $sent ? $dueAt : null,
            'gateway_transfer_id' => $sent ? 'upi-t1-demo-'.$request->id : null,
        ]);

        LedgerEntry::query()->create([
            'account_type' => 'vendor',
            'account_id' => $caterer->id,
            'direction' => 'credit',
            'amount_inr' => $labor,
            'entry_type' => 'earning',
            'reference_type' => ServiceRequest::class,
            'reference_id' => $request->id,
        ]);

        return $payout;
    }

    private function notify(User $user, string $template, ServiceRequest $request): void
    {
        $title = $request->eventDetail?->title ?? $request->taskDetail?->title ?? 'Job';
        NotificationLog::query()->create([
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'template' => $template,
            'payload' => ['title' => $title, 'request_id' => $request->id],
            'status' => 'logged',
        ]);
    }
}
