<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * kuturogi 顧客サイトの Integration API クライアント。
 *
 * @see INTEGRATION.md
 */
class KuturogiApiClient
{
    protected function client(): PendingRequest
    {
        $apiKey = config('kuturogi.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('KUTUROGI_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim(config('kuturogi.base_url'), '/'))
            ->timeout((int) config('kuturogi.timeout', 10))
            ->acceptJson()
            ->withHeaders([
                'X-Integration-Api-Key' => $apiKey,
            ]);
    }

    public function getRooms(): Response
    {
        return $this->client()->get(config('kuturogi.endpoints.rooms'));
    }

    public function getPlans(): Response
    {
        return $this->client()->get(config('kuturogi.endpoints.plans'));
    }

    public function getInventories(array $filters = []): Response
    {
        return $this->client()->get(config('kuturogi.endpoints.inventories'), $filters);
    }

    public function updateInventories(array $items): Response
    {
        return $this->client()->patch(
            config('kuturogi.endpoints.inventories'),
            ['items' => $items]
        );
    }

    public function updateRoom(int $kuturogiRoomId, array $data): Response
    {
        return $this->client()->patch(
            config('kuturogi.endpoints.rooms')."/{$kuturogiRoomId}",
            $data
        );
    }

    public function createRoom(array $data): Response
    {
        return $this->client()->post(
            config('kuturogi.endpoints.rooms'),
            $data
        );
    }

    public function deleteRoom(int $kuturogiRoomId): Response
    {
        return $this->client()->delete(
            config('kuturogi.endpoints.rooms')."/{$kuturogiRoomId}"
        );
    }

    public function clientForMultipart(): PendingRequest
    {
        $apiKey = config('kuturogi.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('KUTUROGI_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim(config('kuturogi.base_url'), '/'))
            ->timeout((int) config('kuturogi.timeout', 10))
            ->acceptJson()
            ->withHeaders([
                'X-Integration-Api-Key' => $apiKey,
            ]);
    }

    public function updatePlan(int $kuturogiPlanId, array $data): Response
    {
        return $this->client()->patch(
            config('kuturogi.endpoints.plans')."/{$kuturogiPlanId}",
            $data
        );
    }

    public function createPlan(array $data): Response
    {
        return $this->client()->post(
            config('kuturogi.endpoints.plans'),
            $data
        );
    }

    public function deletePlan(int $kuturogiPlanId): Response
    {
        return $this->client()->delete(
            config('kuturogi.endpoints.plans')."/{$kuturogiPlanId}"
        );
    }

    public function listReservations(array $filters = []): Response
    {
        return $this->client()->get(
            config('kuturogi.endpoints.reservations'),
            $filters
        );
    }

    public function getUsers(array $filters = []): Response
    {
        return $this->client()->get(
            config('kuturogi.endpoints.users'),
            $filters
        );
    }

    public function createReservation(array $data): Response
    {
        return $this->client()->post(
            config('kuturogi.endpoints.reservations'),
            $data
        );
    }

    public function cancelReservation(int $kuturogiReservationId, int $roomCount = 1): Response
    {
        return $this->client()->patch(
            config('kuturogi.endpoints.reservations')."/{$kuturogiReservationId}/cancel",
            ['room_count' => $roomCount]
        );
    }

    public function updateReservationPayment(int $kuturogiReservationId, array $data): Response
    {
        return $this->client()->patch(
            config('kuturogi.endpoints.reservations')."/{$kuturogiReservationId}/payment",
            $data
        );
    }

    public function pushPricingSettings(array $payload): Response
    {
        return $this->client()->put(
            config('kuturogi.endpoints.pricing_settings'),
            $payload
        );
    }
}
