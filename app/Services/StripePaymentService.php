<?php

namespace App\Services;

use App\Models\Reservation;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

class StripePaymentService
{
    protected ?StripeClient $stripe = null;

    public function isConfigured(): bool
    {
        $secret = config('services.stripe.secret');

        return filled($secret) && str_starts_with((string) $secret, 'sk_test_');
    }

    public static function assertTestModeKeys(?string $secret = null, ?string $key = null): void
    {
        $secret ??= config('services.stripe.secret');
        $key ??= config('services.stripe.key');

        if (filled($secret) && ! str_starts_with((string) $secret, 'sk_test_')) {
            throw new RuntimeException('Stripe はテストモード（sk_test_）のシークレットのみ使用できます。');
        }

        if (filled($key) && ! str_starts_with((string) $key, 'pk_test_')) {
            throw new RuntimeException('Stripe はテストモード（pk_test_）の公開鍵のみ使用できます。');
        }
    }

    protected function client(): StripeClient
    {
        if ($this->stripe) {
            return $this->stripe;
        }

        $secret = config('services.stripe.secret');
        if (blank($secret)) {
            throw new RuntimeException('STRIPE_SECRET is not configured.');
        }

        self::assertTestModeKeys($secret, config('services.stripe.key'));

        return $this->stripe = new StripeClient($secret);
    }

    public function retrieveIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId, [
            'expand' => ['latest_charge', 'payment_method'],
        ]);
    }

    public function capture(string $paymentIntentId): PaymentIntent
    {
        $intent = $this->retrieveIntent($paymentIntentId);

        if ($intent->status === 'succeeded') {
            return $intent;
        }

        if ($intent->status !== 'requires_capture') {
            throw new RuntimeException('キャプチャできない決済状態です（'.$intent->status.'）。');
        }

        return $this->client()->paymentIntents->capture($paymentIntentId);
    }

    public function voidAuthorization(string $paymentIntentId): PaymentIntent
    {
        $intent = $this->retrieveIntent($paymentIntentId);

        if (in_array($intent->status, ['canceled', 'cancelled'], true)) {
            return $intent;
        }

        if ($intent->status === 'requires_capture') {
            return $this->client()->paymentIntents->cancel($paymentIntentId);
        }

        throw new RuntimeException('与信取消できない決済状態です（'.$intent->status.'）。');
    }

    public function refundPaymentIntent(string $paymentIntentId): Refund
    {
        return $this->client()->refunds->create([
            'payment_intent' => $paymentIntentId,
        ]);
    }

    public function chargeCancelFee(string $originalPaymentIntentId, int $feeAmountYen, array $metadata = []): ?PaymentIntent
    {
        if ($feeAmountYen <= 0) {
            return null;
        }

        $original = $this->retrieveIntent($originalPaymentIntentId);
        $paymentMethodId = is_string($original->payment_method)
            ? $original->payment_method
            : ($original->payment_method->id ?? null);
        $customerId = is_string($original->customer)
            ? $original->customer
            : ($original->customer->id ?? null);

        if (! $paymentMethodId) {
            return null;
        }

        try {
            $params = [
                'amount' => $feeAmountYen,
                'currency' => 'jpy',
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'off_session' => true,
                'metadata' => $metadata,
            ];
            if ($customerId) {
                $params['customer'] = $customerId;
            }

            return $this->client()->paymentIntents->create($params);
        } catch (ApiErrorException) {
            return null;
        }
    }

    public function captureReservation(Reservation $reservation): Reservation
    {
        if ($reservation->payment_method !== 'credit') {
            throw new RuntimeException('クレジットカード予約のみ売上確定できます。');
        }

        if ($reservation->payment_status === Reservation::PAYMENT_PAID) {
            return $reservation;
        }

        if ($reservation->payment_status !== Reservation::PAYMENT_AUTHORIZED || blank($reservation->stripe_payment_intent_id)) {
            throw new RuntimeException('与信済みの決済がありません。');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Stripe が設定されていません。');
        }

        $intent = $this->capture($reservation->stripe_payment_intent_id);
        $chargeId = is_string($intent->latest_charge)
            ? $intent->latest_charge
            : ($intent->latest_charge->id ?? $reservation->stripe_latest_charge_id);

        $reservation->update([
            'payment_status' => Reservation::PAYMENT_PAID,
            'stripe_latest_charge_id' => $chargeId,
            'paid_at' => now(),
        ]);

        return $reservation->fresh();
    }

    public function markLocalReceived(Reservation $reservation): Reservation
    {
        if ($reservation->payment_method !== 'local') {
            throw new RuntimeException('現地払い予約のみ現地受領できます。');
        }

        if ($reservation->payment_status === Reservation::PAYMENT_PAID) {
            return $reservation;
        }

        $reservation->update([
            'payment_status' => Reservation::PAYMENT_PAID,
            'paid_at' => now(),
        ]);

        return $reservation->fresh();
    }
}
