<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Services\ProviderTypes;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Marketplace\Models\CatererProfile;
use App\Modules\Workers\Models\WorkerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuthController extends Controller
{
    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'intent' => ['nullable', 'in:login'],
        ]);

        $phone = preg_replace('/\D+/', '', $data['phone']);
        $exists = User::query()->where('phone', $phone)->exists();

        if (($data['intent'] ?? 'login') === 'login' && ! $exists) {
            throw ValidationException::withMessages([
                'phone' => 'No account for this phone. Register first.',
            ]);
        }

        $code = $this->issueOtpCode();

        OtpCode::query()->create([
            'phone' => $phone,
            'code' => $code,
            'purpose' => $data['intent'] ?? 'login',
            'expires_at' => now()->addMinutes((int) config('otp.expires_minutes', 10)),
        ]);

        $payload = ['message' => 'OTP sent.'];
        if ($this->exposeOtpInResponse()) {
            $payload['otp'] = $code;
        }

        return response()->json($payload);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $phone = $this->assertOtp($data['phone'], $data['code'], 'login');
        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => 'No account for this phone. Register first.',
            ]);
        }

        return $this->tokenResponse($user);
    }

    public function register(Request $request): JsonResponse
    {
        $providers = app(ProviderTypes::class);
        $allowedRoles = array_merge(['customer'], $providers->registerRoles());

        $data = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:'.implode(',', $allowedRoles)],
            'city' => ['nullable', 'string', 'max:80'],
            'password' => ['required', Password::min(6)],
            'company_name' => ['nullable', 'string', 'max:160'],
        ]);

        $providers->assertRegisterRole($data['role']);

        $phone = preg_replace('/\D+/', '', $data['phone']);

        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'This phone is already registered. Sign in instead.',
            ]);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'phone' => $phone,
            'city' => $data['city'] ?? null,
            'email' => $phone.'@tasknear.local',
            'password' => $data['password'],
            'password_set_at' => now(),
            'locale' => 'en',
        ]);
        $user->assignRole($data['role']);

        if ($providers->matchMode($data['role']) === 'vendor') {
            CatererProfile::query()->create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'] ?: $data['name'],
                'city' => $data['city'] ?? $user->city,
                'status' => 'active',
                'is_available' => true,
            ]);
        } elseif ($data['role'] !== 'customer') {
            WorkerProfile::query()->create([
                'user_id' => $user->id,
                'bio' => $data['name'],
                'city' => $data['city'] ?? $user->city ?? 'Lucknow',
                'status' => 'pending_kyc',
                'is_available' => true,
            ]);
        }

        return $this->tokenResponse($user->fresh(['roles', 'workerProfile', 'catererProfile']));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $this->userPayload($request->user()->load(['roles', 'workerProfile.skills.category', 'workerProfile.documents', 'catererProfile.skills.category']))
        );
    }

    public function loginWithPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $phone = preg_replace('/\D+/', '', $data['phone']);
        $user = User::query()->where('phone', $phone)->first();

        if (! $user || ! $user->password_set_at || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Invalid phone or password.',
            ]);
        }

        return $this->tokenResponse($user);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'locale' => ['nullable', 'in:en,hi'],
        ]);

        $request->user()->update($data);

        return response()->json(
            $this->userPayload($request->user()->fresh(['roles', 'workerProfile', 'catererProfile']))
        );
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => [$user->password_set_at ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if ($user->password_set_at && ! Hash::check($data['current_password'] ?? '', $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update([
            'password' => $data['password'],
            'password_set_at' => now(),
        ]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $disk = Storage::disk('public');
        $disk->makeDirectory('avatars');

        if ($user->avatar_path) {
            $disk->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json(
            $this->userPayload($user->fresh(['roles', 'workerProfile', 'catererProfile']))
        );
    }

    public function avatar(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user->avatar_path || ! Storage::disk('public')->exists($user->avatar_path)) {
            return response()->json(['message' => 'No photo uploaded.'], 404);
        }

        return Storage::disk('public')->response($user->avatar_path);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function assertOtp(string $phoneRaw, string $code, string $purpose): string
    {
        $phone = preg_replace('/\D+/', '', $phoneRaw);

        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        $devBypass = $this->demoOtpCode() && $code === $this->demoOtpCode();

        if (! $devBypass && (! $otp || ! $otp->isValid($code))) {
            throw ValidationException::withMessages([
                'code' => 'Invalid or expired OTP.',
            ]);
        }

        if ($otp && ! $devBypass) {
            $otp->update(['consumed_at' => now()]);
        }

        return $phone;
    }

    private function demoOtpCode(): ?string
    {
        $configured = config('otp.demo_code');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return app()->environment('local') ? '123456' : null;
    }

    private function issueOtpCode(): string
    {
        $demo = $this->demoOtpCode();
        if ($demo) {
            return $demo;
        }

        return (string) random_int(100000, 999999);
    }

    private function avatarUrl(User $user): ?string
    {
        if (! $user->avatar_path) {
            return null;
        }

        return '/storage/'.$user->avatar_path.'?v='.($user->updated_at?->timestamp ?? time());
    }

    private function exposeOtpInResponse(): bool
    {
        return (bool) config('otp.show_in_response')
            || (bool) $this->demoOtpCode()
            || app()->environment('local');
    }

    private function tokenResponse(User $user): JsonResponse
    {
        $token = $user->createToken('pwa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user->fresh(['roles', 'workerProfile', 'catererProfile'])),
        ]);
    }

    private function userPayload(User $user): array
    {
        $pricing = app(\App\Modules\Money\Services\Pricing::class);
        $sub = $pricing->activeSubscription($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'city' => $user->city,
            'locale' => $user->locale ?: 'en',
            'avatar_url' => $this->avatarUrl($user),
            'password_set' => (bool) $user->password_set_at,
            'roles' => $user->roles->pluck('role')->values(),
            'worker_profile' => $user->workerProfile,
            'caterer_profile' => $user->catererProfile,
            'subscription' => $sub,
        ];
    }
}
