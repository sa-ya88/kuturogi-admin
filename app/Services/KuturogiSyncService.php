<?php

namespace App\Services;

use App\Events\InventoryUpdated;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\SalesRecord;
use App\Support\RoomDetails;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KuturogiSyncService
{
    public function __construct(
        protected KuturogiApiClient $apiClient,
    ) {}

    public function usesSharedDatabase(): bool
    {
        return (bool) config('kuturogi.shared_database');
    }

    public function syncRooms(): int
    {
        if ($this->usesSharedDatabase()) {
            return 0;
        }
        $response = $this->apiClient->getRooms();
        $response->throw();

        $count = 0;

        foreach ($response->json() as $data) {
            $room = Room::updateOrCreate(
                ['kuturogi_room_id' => $data['id']],
                [
                    'name' => $data['name'],
                    'price_per_person' => $data['price_per_person'] ?? 0,
                    'stock_count' => $data['stock_count'] ?? 0,
                    'available_from' => $data['available_from'] ?? null,
                    'available_to' => $data['available_to'] ?? null,
                    'description' => $data['description'] ?? null,
                    'features' => $data['features'] ?? null,
                    'details' => RoomDetails::normalize($data['details'] ?? null),
                    'images' => $data['images'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                ]
            );

            if (! empty($data['plans'])) {
                $planIds = [];
                foreach ($data['plans'] as $planData) {
                    $plan = $this->upsertPlan($planData);
                    $planIds[] = $plan->id;
                }
                $room->plans()->sync($planIds);
            }

            $count++;
        }

        return $count;
    }

    public function syncPlans(): int
    {
        if ($this->usesSharedDatabase()) {
            return 0;
        }
        $response = $this->apiClient->getPlans();
        $response->throw();

        $count = 0;

        foreach ($response->json() as $data) {
            $plan = $this->upsertPlan($data);

            if (! empty($data['rooms'])) {
                $roomIds = [];
                foreach ($data['rooms'] as $roomData) {
                    $room = Room::updateOrCreate(
                        ['kuturogi_room_id' => $roomData['id']],
                        [
                            'name' => $roomData['name'],
                            'price_per_person' => $roomData['price_per_person'] ?? 0,
                            'is_active' => true,
                        ]
                    );
                    $roomIds[] = $room->id;
                }
                $plan->rooms()->syncWithoutDetaching($roomIds);
            }

            $count++;
        }

        return $count;
    }

    public function syncInventories(?string $from = null, ?string $to = null): int
    {
        if ($this->usesSharedDatabase()) {
            return 0;
        }
        $filters = array_filter([
            'from' => $from ?? now()->format('Y-m-d'),
            'to' => $to ?? now()->addMonths(3)->format('Y-m-d'),
        ]);

        $response = $this->apiClient->getInventories($filters);
        $response->throw();

        $count = 0;

        foreach ($response->json() as $data) {
            $room = Room::where('kuturogi_room_id', $data['room_id'])->first();

            if (! $room) {
                continue;
            }

            RoomInventory::upsertForRoomDate(
                $room->id,
                $data['date'],
                $data['remains'],
                now()
            );

            $count++;
        }

        return $count;
    }

    public function syncReservations(?string $since = null): int
    {
        if ($this->usesSharedDatabase()) {
            return 0;
        }
        $filters = array_filter(['since' => $since]);

        $response = $this->apiClient->listReservations($filters);
        $response->throw();

        $count = 0;
        $items = $response->json('data') ?? $response->json();

        foreach ($items as $data) {
            if ($data['status'] === Reservation::STATUS_CANCELLED) {
                $this->cancelReservation($data['id']);
            } else {
                $this->importReservation($this->normalizeReservationPayload($data));
            }
            $count++;
        }

        return $count;
    }

    public function syncCustomers(?string $since = null): int
    {
        if ($this->usesSharedDatabase()) {
            return 0;
        }
        $filters = array_filter(['since' => $since]);

        $response = $this->apiClient->getUsers($filters);
        $response->throw();

        $count = 0;

        foreach ($response->json() as $data) {
            $this->importCustomer(array_merge($data, ['type' => Customer::TYPE_MEMBER]));
            $count++;
        }

        $count += $this->syncGuestsFromReservations();

        return $count;
    }

    public function syncGuestsFromReservations(): int
    {
        $count = 0;

        $orphanReservations = Reservation::query()
            ->whereNull('customer_id')
            ->where(function ($q) {
                $q->whereNotNull('guest_email')->orWhereNotNull('guest_name');
            })
            ->get();

        foreach ($orphanReservations as $reservation) {
            $customer = Customer::firstOrCreate(
                ['email' => $reservation->guest_email ?? "guest-{$reservation->id}@local"],
                [
                    'name' => $reservation->guest_name ?? 'ゲスト',
                    'name_kana' => $reservation->guest_name_kana,
                    'tel' => $reservation->guest_tel,
                    'type' => Customer::TYPE_GUEST,
                ]
            );

            $reservation->update(['customer_id' => $customer->id]);
            $customer->refreshStayStats();
            $count++;
        }

        Customer::each(fn (Customer $c) => $c->refreshStayStats());

        return $count;
    }

    public function syncAll(): array
    {
        return [
            'rooms' => $this->syncRooms(),
            'plans' => $this->syncPlans(),
            'inventories' => $this->syncInventories(),
            'reservations' => $this->syncReservations(),
            'customers' => $this->syncCustomers(),
        ];
    }

    public function pushReservationToKuturogi(Reservation $reservation): Reservation
    {
        if ($this->usesSharedDatabase()) {
            $reservation->update([
                'kuturogi_reservation_id' => $reservation->id,
                'synced_at' => now(),
            ]);

            return $reservation->fresh();
        }
        $room = $reservation->room;
        $plan = $reservation->plan;

        if (! $room->kuturogi_room_id || ! $plan->kuturogi_plan_id) {
            throw new \RuntimeException('客室またはプランが kuturogi と同期されていません。php artisan kuturogi:sync を実行してください。');
        }

        $response = $this->apiClient->createReservation([
            'plan_id' => $plan->kuturogi_plan_id,
            'room_id' => $room->kuturogi_room_id,
            'checkin_date' => $reservation->checkin_date->format('Y-m-d'),
            'checkout_date' => $reservation->checkout_date->format('Y-m-d'),
            'guest_count' => $reservation->guest_count,
            'room_count' => $reservation->room_count,
            'adult_count' => $reservation->adult_count,
            'child_count' => $reservation->child_count,
            'total_price' => $reservation->total_price,
            'guest_name' => $reservation->guest_name ?? 'Admin Booking',
            'guest_email' => $reservation->guest_email,
            'guest_tel' => $reservation->guest_tel,
            'payment_method' => $reservation->payment_method ?? 'local',
        ]);

        if ($response->failed()) {
            $response->throw();
        }

        $data = $response->json();

        $reservation->update([
            'kuturogi_reservation_id' => $data['id'],
            'status' => Reservation::STATUS_CONFIRMED,
            'synced_at' => now(),
        ]);

        return $reservation->fresh();
    }

    public function cancelOnKuturogi(Reservation $reservation): Reservation
    {
        $settlement = app(ReservationPaymentSettlementService::class);
        $reservation = $settlement->settleForCancellation($reservation);

        if (! $this->usesSharedDatabase() && $reservation->kuturogi_reservation_id) {
            $response = $this->apiClient->cancelReservation(
                $reservation->kuturogi_reservation_id,
                $reservation->room_count
            );

            if ($response->failed()) {
                $response->throw();
            }

            $settlement->pushPaymentToKuturogi($reservation->fresh());
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'synced_at' => now(),
        ]);

        $reservation->salesRecord?->update(['status' => SalesRecord::STATUS_CANCELLED]);

        app(ReservationStayService::class)->releaseOccupanciesForReservation($reservation->fresh());

        return $reservation->fresh();
    }

    public function importReservation(array $payload): Reservation
    {
        return DB::transaction(function () use ($payload) {
            $room = $this->resolveRoom($payload['room_id']);
            $plan = $this->resolvePlan($payload['plan_id']);
            $customer = $this->resolveCustomer($payload);

            $reservation = Reservation::updateOrCreate(
                ['kuturogi_reservation_id' => $payload['id']],
                [
                    'customer_id' => $customer?->id,
                    'room_id' => $room->id,
                    'plan_id' => $plan->id,
                    'checkin_date' => $payload['checkin_date'],
                    'checkout_date' => $payload['checkout_date'],
                    'guest_count' => $payload['guest_count'],
                    'room_count' => $payload['room_count'] ?? 1,
                    'adult_count' => $payload['adult_count'] ?? $payload['guest_count'],
                    'child_count' => $payload['child_count'] ?? 0,
                    'total_price' => $payload['total_price'],
                    'status' => $payload['status'] ?? Reservation::STATUS_CONFIRMED,
                    'payment_method' => $payload['payment_method'] ?? null,
                    'payment_status' => $payload['payment_status']
                        ?? (($payload['payment_method'] ?? null) === 'credit'
                            ? Reservation::PAYMENT_AUTHORIZED
                            : Reservation::PAYMENT_UNPAID),
                    'stripe_payment_intent_id' => $payload['stripe_payment_intent_id'] ?? null,
                    'stripe_latest_charge_id' => $payload['stripe_latest_charge_id'] ?? null,
                    'authorized_at' => $payload['authorized_at'] ?? null,
                    'paid_at' => $payload['paid_at'] ?? null,
                    'refunded_at' => $payload['refunded_at'] ?? null,
                    'cancel_fee_amount' => $payload['cancel_fee_amount'] ?? null,
                    'stripe_cancel_fee_payment_intent_id' => $payload['stripe_cancel_fee_payment_intent_id'] ?? null,
                    'cancel_fee_uncollected' => (bool) ($payload['cancel_fee_uncollected'] ?? false),
                    'guest_name' => $payload['guest_name'] ?? null,
                    'guest_name_kana' => $payload['guest_name_kana'] ?? null,
                    'guest_tel' => $payload['guest_tel'] ?? null,
                    'guest_email' => $payload['guest_email'] ?? null,
                    'selected_choices' => $payload['selected_choices'] ?? null,
                    'selected_option_fees' => $payload['selected_option_fees'] ?? null,
                    'source' => $payload['source'] ?? 'kuturogi',
                    'synced_at' => now(),
                ]
            );

            if ($reservation->status !== Reservation::STATUS_CANCELLED) {
                SalesRecord::updateOrCreate(
                    ['reservation_id' => $reservation->id],
                    [
                        'amount' => $reservation->total_price,
                        'recorded_at' => now(),
                        'status' => SalesRecord::STATUS_RECORDED,
                    ]
                );
            }

            $representatives = $payload['representatives'] ?? null;
            if (! is_array($representatives)) {
                $representatives = null;
            }

            $stayService = app(ReservationStayService::class);
            $stayService->syncStaysForReservation(
                $reservation->fresh(),
                $representatives
            );

            if ($reservation->status !== Reservation::STATUS_CANCELLED) {
                try {
                    $stayService->autoAssignUnits($reservation->fresh(['stays']));
                } catch (\Throwable $e) {
                    Log::warning('Auto room assignment failed during reservation import.', [
                        'reservation_id' => $reservation->id,
                        'kuturogi_reservation_id' => $reservation->kuturogi_reservation_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $reservation->customer?->refreshStayStats();

            return $reservation->fresh(['stays.roomUnit']);
        });
    }

    public function cancelReservation(int $kuturogiReservationId): ?Reservation
    {
        $reservation = Reservation::where('kuturogi_reservation_id', $kuturogiReservationId)->first();

        if (! $reservation) {
            return null;
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'synced_at' => now(),
        ]);

        $reservation->salesRecord?->update(['status' => SalesRecord::STATUS_CANCELLED]);

        app(ReservationStayService::class)->releaseOccupanciesForReservation($reservation->fresh());

        return $reservation->fresh();
    }

    public function importCustomer(array $payload): Customer
    {
        $customer = Customer::updateOrCreate(
            ['kuturogi_user_id' => $payload['id']],
            [
                'type' => Customer::TYPE_MEMBER,
                'name' => $payload['name'],
                'name_kana' => $payload['name_kana'] ?? null,
                'email' => $payload['email'],
                'tel' => $payload['tel'] ?? null,
                'zip_code' => $payload['zip_code'] ?? null,
                'address' => $payload['address'] ?? null,
                'birthday' => $payload['birthday'] ?? null,
                'gender' => $payload['gender'] ?? null,
            ]
        );

        $customer->refreshStayStats();

        return $customer;
    }

    public function pushInventoryToKuturogi(RoomInventory $inventory): void
    {
        if ($this->usesSharedDatabase()) {
            return;
        }
        $room = $inventory->room;

        if (! $room->kuturogi_room_id) {
            Log::warning('Skipping inventory sync: kuturogi_room_id is missing.', [
                'room_id' => $room->id,
            ]);

            return;
        }

        $response = $this->apiClient->updateInventories([
            [
                'room_id' => $room->kuturogi_room_id,
                'date' => $inventory->date->format('Y-m-d'),
                'remains' => $inventory->remains,
            ],
        ]);

        if ($response->failed()) {
            Log::error('Failed to push inventory to kuturogi.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        $inventory->update(['synced_at' => now()]);

        event(new InventoryUpdated($inventory));
    }

    public function pushInventoriesToKuturogi(Room $room, Collection $inventories): void
    {
        if ($this->usesSharedDatabase()) {
            return;
        }
        if (! $room->kuturogi_room_id || $inventories->isEmpty()) {
            return;
        }

        foreach ($inventories->chunk(100) as $chunk) {
            $items = $chunk->map(fn (RoomInventory $inventory): array => [
                'room_id' => $room->kuturogi_room_id,
                'date' => $inventory->date->format('Y-m-d'),
                'remains' => $inventory->remains,
            ])->values()->all();

            $response = $this->apiClient->updateInventories($items);

            if ($response->failed()) {
                Log::error('Failed to push inventories to kuturogi.', [
                    'room_id' => $room->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $response->throw();
            }
        }

        RoomInventory::query()
            ->whereIn('id', $inventories->pluck('id'))
            ->update(['synced_at' => now()]);
    }

    public function pushRoomToKuturogi(Room $room): Room
    {
        if ($this->usesSharedDatabase()) {
            $room->update(['kuturogi_room_id' => $room->kuturogi_room_id ?: $room->id]);

            return $room->fresh();
        }
        $payload = [
            'name' => $room->name,
            'price_per_person' => $room->price_per_person,
            'stock_count' => $room->stock_count,
            'available_from' => $room->available_from?->format('Y-m-d'),
            'available_to' => $room->available_to?->format('Y-m-d'),
            'description' => $room->description,
            'features' => $room->features ?? [],
            'details' => RoomDetails::normalize($room->details),
            'images' => $room->images ?? [],
            'is_active' => $room->is_active,
            'sort_order' => $room->sort_order,
            'plan_ids' => $room->plans()
                ->whereNotNull('kuturogi_plan_id')
                ->pluck('kuturogi_plan_id')
                ->all(),
        ];

        if ($room->kuturogi_room_id) {
            $response = $this->apiClient->updateRoom($room->kuturogi_room_id, $payload);
        } else {
            $existingId = $this->findKuturogiRoomIdByName($room->name);

            if ($existingId) {
                $room->update(['kuturogi_room_id' => $existingId]);
                $response = $this->apiClient->updateRoom($existingId, $payload);
            } else {
                $response = $this->apiClient->createRoom($payload);
            }
        }

        $response->throw();

        if (! $room->kuturogi_room_id) {
            $room->update(['kuturogi_room_id' => $response->json('id')]);
        }

        return $room->fresh();
    }

    public function pruneUnlinkedKuturogiRooms(): array
    {
        if ($this->usesSharedDatabase()) {
            return ['deleted' => 0, 'unpublished' => 0];
        }
        $response = $this->apiClient->getRooms();
        $response->throw();

        $linkedIds = Room::query()
            ->whereNotNull('kuturogi_room_id')
            ->pluck('kuturogi_room_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $deleted = 0;
        $unpublished = 0;

        foreach ($response->json() as $data) {
            $kuturogiRoomId = (int) ($data['id'] ?? 0);

            if ($kuturogiRoomId === 0 || in_array($kuturogiRoomId, $linkedIds, true)) {
                continue;
            }

            $deleteResponse = $this->apiClient->deleteRoom($kuturogiRoomId);

            if ($deleteResponse->successful() || $deleteResponse->status() === 404) {
                $deleted++;

                continue;
            }

            if ($deleteResponse->status() === 422) {
                $this->apiClient->updateRoom($kuturogiRoomId, ['is_active' => false])->throw();
                $unpublished++;

                continue;
            }

            $deleteResponse->throw();
        }

        return [
            'deleted' => $deleted,
            'unpublished' => $unpublished,
        ];
    }

    private function findKuturogiRoomIdByName(string $name): ?int
    {
        $response = $this->apiClient->getRooms();
        $response->throw();

        foreach ($response->json() as $data) {
            if (($data['name'] ?? null) === $name && isset($data['id'])) {
                return (int) $data['id'];
            }
        }

        return null;
    }

    public function pushPlanToKuturogi(Plan $plan): Plan
    {
        if ($this->usesSharedDatabase()) {
            $plan->update(['kuturogi_plan_id' => $plan->kuturogi_plan_id ?: $plan->id]);

            return $plan->fresh();
        }
        $plan->load('rooms');

        $payload = [
            'name' => $plan->name,
            'price_per_person' => $plan->price_per_person,
            'description' => $plan->description ?? '',
            'choice_options' => $plan->choice_options ?? [],
            'images' => $plan->images ?? [],
            'has_breakfast' => $plan->has_breakfast,
            'has_dinner' => $plan->has_dinner,
            'has_checkin_time' => $plan->has_checkin_time,
            'checkin_time' => $plan->formattedCheckinTime(),
            'has_checkout_time' => $plan->has_checkout_time,
            'checkout_time' => $plan->formattedCheckoutTime(),
            'has_early_bird' => $plan->has_early_bird,
            'early_bird_discount_type' => $plan->early_bird_discount_type,
            'early_bird_discount_value' => $plan->early_bird_discount_value,
            'early_bird_days_before' => $plan->early_bird_days_before,
            'room_ids' => $plan->rooms()
                ->whereNotNull('kuturogi_room_id')
                ->pluck('kuturogi_room_id')
                ->all(),
        ];

        if ($plan->kuturogi_plan_id) {
            $response = $this->apiClient->updatePlan($plan->kuturogi_plan_id, $payload);
        } else {
            $response = $this->apiClient->createPlan($payload);
        }

        $response->throw();

        if (! $plan->kuturogi_plan_id) {
            $plan->update(['kuturogi_plan_id' => $response->json('id')]);
        }

        return $plan->fresh();
    }

    public function deletePlanOnKuturogi(Plan $plan): void
    {
        if ($this->usesSharedDatabase() || ! $plan->kuturogi_plan_id) {
            return;
        }

        $response = $this->apiClient->deletePlan($plan->kuturogi_plan_id);

        if ($response->successful() || $response->status() === 404) {
            return;
        }

        throw new \RuntimeException(
            $response->json('message') ?: 'kuturogi 側でプランを削除できませんでした。'
        );
    }

    public function ensurePlanDeletable(Plan $plan): void
    {
        if ($plan->hasBlockingReservations()) {
            throw new \RuntimeException($plan->deletionBlockedMessage());
        }
    }

    public function deletePlanWithSync(Plan $plan): void
    {
        $this->ensurePlanDeletable($plan);
        $this->deletePlanOnKuturogi($plan);
        app(PlanImageService::class)->deletePlanImages($plan);
        $plan->rooms()->detach();
        $plan->roomUnits()->detach();
        Plan::withoutEvents(fn () => $plan->delete());
        $this->pruneUnlinkedKuturogiPlans();
    }

    public function pruneUnlinkedKuturogiPlans(): array
    {
        if ($this->usesSharedDatabase()) {
            return ['deleted' => 0, 'detached' => 0];
        }
        $response = $this->apiClient->getPlans();
        $response->throw();

        $linkedIds = Plan::query()
            ->whereNotNull('kuturogi_plan_id')
            ->pluck('kuturogi_plan_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $deleted = 0;
        $detached = 0;

        foreach ($response->json() as $data) {
            $kuturogiPlanId = (int) ($data['id'] ?? 0);

            if ($kuturogiPlanId === 0 || in_array($kuturogiPlanId, $linkedIds, true)) {
                continue;
            }

            $deleteResponse = $this->apiClient->deletePlan($kuturogiPlanId);

            if ($deleteResponse->successful() || $deleteResponse->status() === 404) {
                $deleted++;

                continue;
            }

            if ($deleteResponse->status() === 422) {
                $this->apiClient->updatePlan($kuturogiPlanId, ['room_ids' => []])->throw();
                $detached++;

                continue;
            }

            $deleteResponse->throw();
        }

        return [
            'deleted' => $deleted,
            'detached' => $detached,
        ];
    }

    public function deleteRoomOnKuturogi(Room $room): void
    {
        if ($this->usesSharedDatabase() || ! $room->kuturogi_room_id) {
            return;
        }

        $response = $this->apiClient->deleteRoom($room->kuturogi_room_id);

        if ($response->successful() || $response->status() === 404) {
            return;
        }

        throw new \RuntimeException(
            $response->json('message') ?: 'kuturogi 側で客室を削除できませんでした。'
        );
    }

    public function ensureRoomDeletable(Room $room): void
    {
        if ($room->hasBlockingReservations()) {
            throw new \RuntimeException($room->deletionBlockedMessage());
        }
    }

    public function deleteRoomWithSync(Room $room): void
    {
        $this->ensureRoomDeletable($room);
        $this->deleteRoomOnKuturogi($room);
        app(RoomImageService::class)->deleteRoomImages($room);
        $room->plans()->detach();
        Room::withoutEvents(fn () => $room->delete());
        $this->pruneUnlinkedKuturogiRooms();
    }

    protected function upsertPlan(array $data): Plan
    {
        return Plan::updateOrCreate(
            ['kuturogi_plan_id' => $data['id']],
            [
                'name' => $data['name'],
                'price_per_person' => $data['price_per_person'] ?? 0,
                'description' => $data['description'] ?? null,
                'choice_options' => $data['choice_options'] ?? null,
                'images' => $data['images'] ?? null,
                'has_breakfast' => $data['has_breakfast'] ?? false,
                'has_dinner' => $data['has_dinner'] ?? false,
                'is_active' => true,
                'has_checkin_time' => $data['has_checkin_time'] ?? false,
                'checkin_time' => $data['checkin_time'] ?? null,
                'has_checkout_time' => $data['has_checkout_time'] ?? false,
                'checkout_time' => $data['checkout_time'] ?? null,
                'has_early_bird' => $data['has_early_bird'] ?? false,
                'early_bird_discount_type' => $data['early_bird_discount_type'] ?? null,
                'early_bird_discount_value' => $data['early_bird_discount_value'] ?? null,
                'early_bird_days_before' => $data['early_bird_days_before'] ?? null,
            ]
        );
    }

    protected function normalizeReservationPayload(array $data): array
    {
        return [
            'id' => $data['id'],
            'user_id' => $data['user_id'] ?? null,
            'room_id' => $data['room_id'],
            'plan_id' => $data['plan_id'],
            'checkin_date' => Carbon::parse($data['checkin_date'])->format('Y-m-d'),
            'checkout_date' => Carbon::parse($data['checkout_date'])->format('Y-m-d'),
            'guest_count' => $data['guest_count'],
            'room_count' => $data['room_count'] ?? 1,
            'total_price' => $data['total_price'],
            'status' => $data['status'],
            'payment_method' => $data['payment_method'] ?? null,
            'payment_status' => $data['payment_status'] ?? null,
            'stripe_payment_intent_id' => $data['stripe_payment_intent_id'] ?? null,
            'stripe_latest_charge_id' => $data['stripe_latest_charge_id'] ?? null,
            'authorized_at' => $data['authorized_at'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'refunded_at' => $data['refunded_at'] ?? null,
            'cancel_fee_amount' => $data['cancel_fee_amount'] ?? null,
            'stripe_cancel_fee_payment_intent_id' => $data['stripe_cancel_fee_payment_intent_id'] ?? null,
            'cancel_fee_uncollected' => $data['cancel_fee_uncollected'] ?? false,
            'guest_name' => $data['guest_name'] ?? null,
            'guest_email' => $data['guest_email'] ?? null,
            'guest_tel' => $data['guest_tel'] ?? null,
        ];
    }

    protected function resolveRoom(int $kuturogiRoomId): Room
    {
        return Room::firstOrCreate(
            ['kuturogi_room_id' => $kuturogiRoomId],
            ['name' => "Room #{$kuturogiRoomId}", 'price_per_person' => 0]
        );
    }

    protected function resolvePlan(int $kuturogiPlanId): Plan
    {
        return Plan::firstOrCreate(
            ['kuturogi_plan_id' => $kuturogiPlanId],
            ['name' => "Plan #{$kuturogiPlanId}", 'price_per_person' => 0]
        );
    }

    protected function resolveCustomer(array $payload): ?Customer
    {
        if (! empty($payload['user_id'])) {
            return Customer::firstOrCreate(
                ['kuturogi_user_id' => $payload['user_id']],
                [
                    'type' => Customer::TYPE_MEMBER,
                    'name' => $payload['guest_name'] ?? 'Unknown',
                    'email' => $payload['guest_email'] ?? null,
                ]
            );
        }

        if (! empty($payload['guest_email'])) {
            return Customer::firstOrCreate(
                ['email' => $payload['guest_email']],
                [
                    'type' => Customer::TYPE_GUEST,
                    'name' => $payload['guest_name'] ?? 'Guest',
                    'tel' => $payload['guest_tel'] ?? null,
                ]
            );
        }

        return null;
    }

    public function overwriteKuturogiFromAdmin(): array
    {
        if ($this->usesSharedDatabase()) {
            return [
                'rooms' => 0,
                'plans' => 0,
                'reservations' => 0,
                'inventories' => 0,
            ];
        }
        $pdo = $this->kuturogiSqlite();
        $pdo->exec('PRAGMA foreign_keys = ON');
        $now = now()->toDateTimeString();

        $roomsPushed = 0;
        foreach (Room::query()->orderBy('sort_order')->get() as $room) {
            $this->pushRoomToKuturogi($room->load('plans'));
            $roomsPushed++;
        }

        $plansPushed = 0;
        foreach (Plan::query()->orderBy('id')->get() as $plan) {
            $this->pushPlanToKuturogi($plan);
            $plansPushed++;
        }

        $validUserIds = [];
        foreach ($pdo->query('SELECT id FROM users') as $row) {
            $validUserIds[(int) $row['id']] = true;
        }

        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM reservations');

            $insert = $pdo->prepare(
                'INSERT INTO reservations (
                    id, user_id, plan_id, room_id, checkin_date, checkout_date,
                    guest_count, room_count, adult_count, child_count, total_price, status,
                    guest_name, guest_name_kana, guest_tel, guest_email,
                    payment_method, payment_status, stripe_payment_intent_id, stripe_latest_charge_id,
                    authorized_at, paid_at, refunded_at, cancel_fee_amount,
                    stripe_cancel_fee_payment_intent_id, cancel_fee_uncollected,
                    selected_choices, selected_option_fees, created_at, updated_at
                ) VALUES (
                    :id, :user_id, :plan_id, :room_id, :checkin_date, :checkout_date,
                    :guest_count, :room_count, :adult_count, :child_count, :total_price, :status,
                    :guest_name, :guest_name_kana, :guest_tel, :guest_email,
                    :payment_method, :payment_status, :stripe_payment_intent_id, :stripe_latest_charge_id,
                    :authorized_at, :paid_at, :refunded_at, :cancel_fee_amount,
                    :stripe_cancel_fee_payment_intent_id, :cancel_fee_uncollected,
                    :selected_choices, :selected_option_fees, :created_at, :updated_at
                )'
            );

            $reservations = Reservation::query()
                ->with(['room', 'plan', 'customer'])
                ->orderBy('id')
                ->get();

            foreach ($reservations as $reservation) {
                $kuturogiRoomId = $reservation->room?->kuturogi_room_id;
                $kuturogiPlanId = $reservation->plan?->kuturogi_plan_id;

                if (! $kuturogiRoomId || ! $kuturogiPlanId) {
                    throw new \RuntimeException("予約 {$reservation->id} の客室またはプランが kuturogi 未連携です。");
                }

                $userId = $reservation->customer?->kuturogi_user_id;
                if ($userId && ! isset($validUserIds[(int) $userId])) {
                    $userId = null;
                }

                $insert->execute([
                    'id' => $reservation->id,
                    'user_id' => $userId,
                    'plan_id' => $kuturogiPlanId,
                    'room_id' => $kuturogiRoomId,
                    'checkin_date' => $reservation->checkin_date?->toDateString(),
                    'checkout_date' => $reservation->checkout_date?->toDateString(),
                    'guest_count' => $reservation->guest_count,
                    'room_count' => $reservation->room_count,
                    'adult_count' => $reservation->adult_count,
                    'child_count' => $reservation->child_count,
                    'total_price' => $reservation->total_price,
                    'status' => $reservation->status,
                    'guest_name' => $reservation->guest_name,
                    'guest_name_kana' => $reservation->guest_name_kana,
                    'guest_tel' => $reservation->guest_tel,
                    'guest_email' => $reservation->guest_email,
                    'payment_method' => $reservation->payment_method,
                    'payment_status' => $reservation->payment_status,
                    'stripe_payment_intent_id' => $reservation->stripe_payment_intent_id,
                    'stripe_latest_charge_id' => $reservation->stripe_latest_charge_id,
                    'authorized_at' => optional($reservation->authorized_at)?->toDateTimeString(),
                    'paid_at' => optional($reservation->paid_at)?->toDateTimeString(),
                    'refunded_at' => optional($reservation->refunded_at)?->toDateTimeString(),
                    'cancel_fee_amount' => $reservation->cancel_fee_amount,
                    'stripe_cancel_fee_payment_intent_id' => $reservation->stripe_cancel_fee_payment_intent_id,
                    'cancel_fee_uncollected' => $reservation->cancel_fee_uncollected ? 1 : 0,
                    'selected_choices' => $reservation->selected_choices
                        ? json_encode($reservation->selected_choices, JSON_UNESCAPED_UNICODE)
                        : null,
                    'selected_option_fees' => $reservation->selected_option_fees
                        ? json_encode($reservation->selected_option_fees, JSON_UNESCAPED_UNICODE)
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $reservation->update([
                    'kuturogi_reservation_id' => $reservation->id,
                    'synced_at' => now(),
                ]);
            }

            $maxId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM reservations')->fetchColumn();
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'reservations'");
            if ($maxId > 0) {
                $pdo->exec("INSERT INTO sqlite_sequence (name, seq) VALUES ('reservations', {$maxId})");
            }

            $linkedRoomIds = Room::query()
                ->whereNotNull('kuturogi_room_id')
                ->pluck('kuturogi_room_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($linkedRoomIds !== []) {
                $placeholders = implode(',', array_fill(0, count($linkedRoomIds), '?'));
                $pdo->prepare("DELETE FROM room_inventories WHERE room_id IN ({$placeholders})")
                    ->execute($linkedRoomIds);
            }

            $inventoryInsert = $pdo->prepare(
                'INSERT INTO room_inventories (room_id, date, remains, created_at, updated_at)
                 VALUES (:room_id, :date, :remains, :created_at, :updated_at)'
            );

            $inventoriesPushed = 0;
            foreach (Room::query()->whereNotNull('kuturogi_room_id')->with('inventories')->get() as $room) {
                foreach ($room->inventories as $inventory) {
                    $inventoryInsert->execute([
                        'room_id' => $room->kuturogi_room_id,
                        'date' => $inventory->date->toDateString(),
                        'remains' => $inventory->remains,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inventoriesPushed++;
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->pruneUnlinkedKuturogiRooms();
        $this->pruneUnlinkedKuturogiPlans();

        try {
            app(PricingSettingsService::class)->pushToKuturogi();
        } catch (\Throwable $e) {
            Log::warning('Failed to push pricing settings while overwriting kuturogi.', [
                'message' => $e->getMessage(),
            ]);
        }

        return [
            'rooms' => $roomsPushed,
            'plans' => $plansPushed,
            'reservations' => $reservations->count(),
            'inventories' => $inventoriesPushed,
        ];
    }

    protected function kuturogiSqlite(): \PDO
    {
        $path = (string) env(
            'KUTUROGI_DATABASE_PATH',
            dirname(base_path()).'/kuturogi/database/database.sqlite'
        );

        if (! is_file($path)) {
            throw new \RuntimeException("kuturogi の SQLite が見つかりません: {$path}");
        }

        $pdo = new \PDO('sqlite:'.$path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
