<?php

namespace App\Modules\Money\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RazorpayGateway
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    public function isConfigured(): bool
    {
        return filled($this->keyId()) && filled($this->keySecret());
    }

    public function keyId(): ?string
    {
        $key = config('payments.razorpay.key_id');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function keySecret(): ?string
    {
        $secret = config('payments.razorpay.key_secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /** @param  array<string, mixed>  $notes */
    public function createOrder(int $amountInr, string $receipt, array $notes = []): array
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages(['payment' => 'Razorpay is not configured.']);
        }

        if ($amountInr < 1) {
            throw ValidationException::withMessages(['payment' => 'Amount must be at least ₹1.']);
        }

        try {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->acceptJson()
                ->post(self::BASE_URL.'/orders', [
                    'amount' => $amountInr * 100,
                    'currency' => config('payments.checkout.currency', 'INR'),
                    'receipt' => $receipt,
                    'notes' => $notes,
                ])
                ->throw();
        } catch (RequestException $e) {
            $message = $e->response?->json('error.description') ?: 'Could not create Razorpay order.';

            throw ValidationException::withMessages(['payment' => $message]);
        }

        return $response->json();
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $payload = $orderId.'|'.$paymentId;
        $expected = hash_hmac('sha256', $payload, (string) $this->keySecret());

        return hash_equals($expected, $signature);
    }
}
