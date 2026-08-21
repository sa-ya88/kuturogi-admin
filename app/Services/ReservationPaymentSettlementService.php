<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * キャンセル時の Stripe 与信取消・返金・違約金再決済。
 */
class ReservationPaymentSettlementService
{
    public function __construct(
        protected StripePaymentService $stripe,
        protected CancelFeeCalculator $cancelFees,
    ) {}

    public function settleForCancellation(Reservation $reservation): Reservation
    {
        if ($reservation->payment_method !== 'credit') {
            return $reservation;
        }

        if (! $this->stripe->isConfigured()) {
            Log::warning('Stripe not configured; skipping payment settlement on cancel.', [
                'reservation_id' => $reservation->id,
            ]);

            return $reservation;
        }

        $status = $reservation->payment_status;
        $pi = $reservation->stripe_payment_intent_id;

        if ($status === Reservation::PAYMENT_AUTHORIZED && filled($pi)) {
            $this->stripe->voidAuthorization($pi);
            $reservation->update([
                'payment_status' => Reservation::PAYMENT_REFUNDED,
                'refunded_at' => now(),
            ]);

            return $reservation->fresh();
        }

        if ($status === Reservation::PAYMENT_PAID && filled($pi)) {
            $this->stripe->refundPaymentIntent($pi);

            $fee = $this->cancelFees->calculate($reservation);
            $updates = [
                'payment_status' => Reservation::PAYMENT_REFUNDED,
                'refunded_at' => now(),
                'cancel_fee_amount' => $fee > 0 ? $fee : null,
                'cancel_fee_uncollected' => false,
                'stripe_cancel_fee_payment_intent_id' => null,
            ];

            if ($fee > 0) {
                $feeIntent = $this->stripe->chargeCancelFee($pi, $fee, [
                    'reservation_id' => (string) $reservation->id,
                    'type' => 'cancel_fee',
                ]);

                if ($feeIntent && in_array($feeIntent->status, ['succeeded', 'requires_capture'], true)) {
                    $updates['stripe_cancel_fee_payment_intent_id'] = $feeIntent->id;
                    $updates['cancel_fee_uncollected'] = false;
                    // 違約金を徴収できた場合も返金済＋違約金記録として扱う
                } else {
                    $updates['cancel_fee_uncollected'] = true;
                }
            }

            $reservation->update($updates);

            return $reservation->fresh();
        }

        return $reservation;
    }

    public function captureAndSync(Reservation $reservation): Reservation
    {
        $reservation = $this->stripe->captureReservation($reservation);
        $this->pushPaymentToKuturogi($reservation);

        return $reservation;
    }

    public function markLocalReceivedAndSync(Reservation $reservation): Reservation
    {
        $reservation = $this->stripe->markLocalReceived($reservation);
        $this->pushPaymentToKuturogi($reservation);

        return $reservation;
    }

    public function pushPaymentToKuturogi(Reservation $reservation): void
    {
        if (config('kuturogi.shared_database')) {
            return;
        }

        if (! $reservation->kuturogi_reservation_id) {
            return;
        }

        try {
            $response = app(KuturogiApiClient::class)->updateReservationPayment(
                (int) $reservation->kuturogi_reservation_id,
                [
                    'payment_status' => $reservation->payment_status,
                    'stripe_payment_intent_id' => $reservation->stripe_payment_intent_id,
                    'stripe_latest_charge_id' => $reservation->stripe_latest_charge_id,
                    'authorized_at' => optional($reservation->authorized_at)?->toIso8601String(),
                    'paid_at' => optional($reservation->paid_at)?->toIso8601String(),
                    'refunded_at' => optional($reservation->refunded_at)?->toIso8601String(),
                    'cancel_fee_amount' => $reservation->cancel_fee_amount,
                    'stripe_cancel_fee_payment_intent_id' => $reservation->stripe_cancel_fee_payment_intent_id,
                    'cancel_fee_uncollected' => (bool) $reservation->cancel_fee_uncollected,
                ]
            );

            if ($response->failed()) {
                Log::warning('Failed to sync payment fields to kuturogi.', [
                    'reservation_id' => $reservation->id,
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to sync payment fields to kuturogi.', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
