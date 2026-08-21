<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationStay;
use App\Models\RoomUnit;
use App\Models\RoomUnitDateOccupancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReservationStayService
{
    /**
     * @param  array<int, string>|null  $representatives
     */
    public function syncStaysForReservation(Reservation $reservation, ?array $representatives = null): void
    {
        $roomCount = max(1, (int) $reservation->room_count);
        $existing = $reservation->stays()->orderBy('sort_order')->get()->values();

        for ($i = 1; $i <= $roomCount; $i++) {
            $index = $i - 1;
            $name = $this->resolveRepresentativeName($reservation, $representatives, $index);

            if (isset($existing[$index])) {
                $existing[$index]->update([
                    'representative_name' => $name !== '' ? $name : $existing[$index]->representative_name,
                    'sort_order' => $i,
                ]);
            } else {
                $fallback = $index === 0
                    ? (string) ($reservation->guest_name ?: '未設定')
                    : '';

                $reservation->stays()->create([
                    'representative_name' => $name !== '' ? $name : $fallback,
                    'sort_order' => $i,
                ]);
            }
        }

        if ($existing->count() > $roomCount) {
            $extra = $reservation->stays()
                ->where('sort_order', '>', $roomCount)
                ->get();

            foreach ($extra as $stay) {
                $this->clearStayAssignment($stay);
                $stay->delete();
            }
        }

        $this->refreshStayStatus($reservation->fresh(['stays']));
    }

    /**
     * @param  array<int, string>|null  $representatives
     */
    protected function resolveRepresentativeName(Reservation $reservation, ?array $representatives, int $index): string
    {
        if (is_array($representatives) && array_key_exists($index, $representatives)) {
            return trim((string) $representatives[$index]);
        }

        if ($index === 0) {
            return trim((string) ($reservation->guest_name ?: ''));
        }

        return '';
    }

    public function refreshStayStatus(Reservation $reservation): Reservation
    {
        $stays = $reservation->stays()->get();

        if ($stays->isEmpty()) {
            $reservation->update(['stay_status' => Reservation::STAY_STATUS_RESERVED]);

            return $reservation->fresh();
        }

        $checkedInCount = $stays->filter(fn (ReservationStay $stay) => $stay->checked_in_at !== null)->count();
        $checkedOutCount = $stays->filter(fn (ReservationStay $stay) => $stay->checked_out_at !== null)->count();
        $inHouseCount = $stays->filter(fn (ReservationStay $stay) => $stay->isCheckedIn())->count();

        $stayStatus = match (true) {
            $checkedOutCount === $stays->count() => Reservation::STAY_STATUS_CHECKED_OUT,
            $inHouseCount === $stays->count() => Reservation::STAY_STATUS_IN_HOUSE,
            $checkedInCount > 0 => Reservation::STAY_STATUS_PARTIALLY_IN_HOUSE,
            default => Reservation::STAY_STATUS_RESERVED,
        };

        $reservation->update(['stay_status' => $stayStatus]);

        return $reservation->fresh(['stays.roomUnit']);
    }

    public function autoAssignUnits(Reservation $reservation): void
    {
        if ($reservation->status === Reservation::STATUS_CANCELLED) {
            return;
        }

        $reservation->loadMissing('stays');

        foreach ($reservation->stays()->orderBy('sort_order')->get() as $stay) {
            if ($stay->room_unit_id || $stay->checked_out_at) {
                continue;
            }

            $unit = $this->assignableUnitsForStay($stay)->first();

            if (! $unit) {
                throw ValidationException::withMessages([
                    'room_unit_id' => "空きのある個別客室が不足しています（客室タイプの稼働中室数と在庫を確認してください）。",
                ]);
            }

            $this->assignUnit($stay, $unit->id);
        }
    }

    public function releaseOccupanciesForReservation(Reservation $reservation): void
    {
        $unitIds = collect()
            ->merge(
                RoomUnitDateOccupancy::query()
                    ->where('reservation_id', $reservation->id)
                    ->pluck('room_unit_id')
            )
            ->merge(
                $reservation->stays()
                    ->whereNotNull('room_unit_id')
                    ->pluck('room_unit_id')
            )
            ->unique()
            ->filter()
            ->values()
            ->all();

        RoomUnitDateOccupancy::query()
            ->where('reservation_id', $reservation->id)
            ->delete();

        $this->refreshInventoryForReservation($reservation);

        $reservation->stays()
            ->whereNotNull('room_unit_id')
            ->whereNull('checked_out_at')
            ->update(['room_unit_id' => null]);

        foreach ($unitIds as $unitId) {
            $this->releaseUnitIfIdle((int) $unitId);
        }
    }

    public function assignUnit(ReservationStay $stay, ?int $roomUnitId): ReservationStay
    {
        $reservation = $stay->reservation()->firstOrFail();

        if ($reservation->status === Reservation::STATUS_CANCELLED) {
            throw new RuntimeException('キャンセル済み予約には部屋を割り当てできません。');
        }

        if ($stay->checked_out_at !== null) {
            throw new RuntimeException('チェックアウト済みの滞在行は変更できません。');
        }

        if ($roomUnitId === null) {
            if ($stay->checked_in_at !== null) {
                throw new RuntimeException('チェックイン済みの部屋割は解除できません。先にチェックアウトしてください。');
            }

            $this->clearStayAssignment($stay);

            return $stay->fresh(['roomUnit']);
        }

        $unit = RoomUnit::query()->findOrFail($roomUnitId);

        if ((int) $unit->room_id !== (int) $reservation->room_id) {
            throw ValidationException::withMessages([
                'room_unit_id' => '客室タイプが一致しない個別客室は割り当てできません。',
            ]);
        }

        if ($unit->operation_status !== RoomUnit::OPERATION_IN_SERVICE) {
            throw ValidationException::withMessages([
                'room_unit_id' => '停止中の個別客室は割り当てできません。',
            ]);
        }

        if (! $this->isUnitAvailableForReservation($unit, $reservation, $stay->id)) {
            throw ValidationException::withMessages([
                'room_unit_id' => '指定期間でこの個別客室は既に使用中です。',
            ]);
        }

        try {
            return DB::transaction(function () use ($stay, $unit, $reservation) {
                $previousUnitId = $stay->room_unit_id;

                if ($previousUnitId && (int) $previousUnitId !== (int) $unit->id) {
                    $this->clearOccupanciesForStay($stay);
                    $this->releaseUnitIfIdle((int) $previousUnitId);
                }

                $stay->update(['room_unit_id' => $unit->id]);
                $this->writeOccupanciesForStay($stay->fresh(['reservation']), $unit->id);
                $unit->update(['current_status' => RoomUnit::CURRENT_AWAITING_ARRIVAL]);

                return $stay->fresh(['roomUnit']);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'room_unit_id' => '指定期間でこの個別客室は既に使用中です。',
            ]);
        }
    }

    public function checkIn(ReservationStay $stay): ReservationStay
    {
        $reservation = $stay->reservation()->firstOrFail();

        if ($reservation->status === Reservation::STATUS_CANCELLED) {
            throw new RuntimeException('キャンセル済み予約はチェックインできません。');
        }

        if (! $stay->canCheckIn()) {
            throw new RuntimeException('部屋割と代表者名を設定してからチェックインしてください。');
        }

        $stay->update(['checked_in_at' => now(), 'checked_out_at' => null]);
        $stay->roomUnit?->update(['current_status' => RoomUnit::CURRENT_IN_HOUSE]);
        $this->refreshStayStatus($reservation);

        return $stay->fresh(['roomUnit']);
    }

    public function checkOut(ReservationStay $stay): ReservationStay
    {
        if (! $stay->canCheckOut()) {
            throw new RuntimeException('滞在中の部屋のみチェックアウトできます。');
        }

        $reservation = $stay->reservation()->firstOrFail();
        $unitId = $stay->room_unit_id;

        $stay->update(['checked_out_at' => now()]);

        RoomUnitDateOccupancy::query()
            ->where('reservation_id', $reservation->id)
            ->when($unitId, fn ($query) => $query->where('room_unit_id', $unitId))
            ->delete();

        $this->refreshInventoryForReservation($reservation);

        if ($unitId) {
            RoomUnit::query()
                ->where('id', $unitId)
                ->update(['current_status' => RoomUnit::CURRENT_NEEDS_CLEANING]);
        }

        $this->refreshStayStatus($reservation);

        return $stay->fresh(['roomUnit']);
    }

    protected function clearStayAssignment(ReservationStay $stay): void
    {
        $previousUnitId = $stay->room_unit_id;

        $this->clearOccupanciesForStay($stay);

        if ($previousUnitId) {
            $stay->update(['room_unit_id' => null]);
            $this->releaseUnitIfIdle((int) $previousUnitId);
        } else {
            $stay->update(['room_unit_id' => null]);
        }
    }

    protected function clearOccupanciesForStay(ReservationStay $stay): void
    {
        if (! $stay->room_unit_id) {
            return;
        }

        RoomUnitDateOccupancy::query()
            ->where('reservation_id', $stay->reservation_id)
            ->where('room_unit_id', $stay->room_unit_id)
            ->delete();

        $reservation = $stay->reservation ?? $stay->reservation()->first();

        if ($reservation) {
            $this->refreshInventoryForReservation($reservation);
        }
    }

    protected function writeOccupanciesForStay(ReservationStay $stay, int $roomUnitId): void
    {
        $reservation = $stay->reservation ?? $stay->reservation()->firstOrFail();
        $dates = $this->stayNightDates($reservation);
        $now = now();

        foreach ($dates as $date) {
            $existing = RoomUnitDateOccupancy::query()
                ->where('room_unit_id', $roomUnitId)
                ->whereDate('date', $date)
                ->first();

            if ($existing) {
                if ((int) $existing->reservation_id !== (int) $reservation->id) {
                    throw ValidationException::withMessages([
                        'room_unit_id' => '指定期間でこの個別客室は既に使用中です。',
                    ]);
                }

                $existing->update([
                    'date' => $date,
                    'reservation_id' => $reservation->id,
                    'updated_at' => $now,
                ]);

                continue;
            }

            RoomUnitDateOccupancy::query()->create([
                'room_unit_id' => $roomUnitId,
                'date' => $date,
                'reservation_id' => $reservation->id,
            ]);
        }

        $this->refreshInventoryForReservation($reservation);
    }

    protected function refreshInventoryForReservation(Reservation $reservation): void
    {
        $room = $reservation->room ?? $reservation->room()->first();

        if (! $room) {
            return;
        }

        $service = app(RoomInventoryService::class);

        foreach ($this->stayNightDates($reservation) as $date) {
            $service->upsertRemainsForDate($room, $date);
        }
    }

    /**
     * @return list<string>
     */
    protected function stayNightDates(Reservation $reservation): array
    {
        $from = Carbon::parse($reservation->checkin_date)->startOfDay();
        $to = Carbon::parse($reservation->checkout_date)->startOfDay();
        $dates = [];

        for ($date = $from->copy(); $date->lt($to); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    protected function releaseUnitIfIdle(int $roomUnitId): void
    {
        $stillOccupied = RoomUnitDateOccupancy::query()
            ->where('room_unit_id', $roomUnitId)
            ->exists();

        $stillAssigned = ReservationStay::query()
            ->where('room_unit_id', $roomUnitId)
            ->whereNull('checked_out_at')
            ->whereHas('reservation', fn ($query) => $query->where('status', '!=', Reservation::STATUS_CANCELLED))
            ->exists();

        if ($stillOccupied || $stillAssigned) {
            return;
        }

        RoomUnit::query()
            ->where('id', $roomUnitId)
            ->whereIn('current_status', [
                RoomUnit::CURRENT_AWAITING_ARRIVAL,
                RoomUnit::CURRENT_IN_HOUSE,
            ])
            ->update(['current_status' => RoomUnit::CURRENT_BOOKABLE]);
    }

    public function checkInAll(Reservation $reservation): void
    {
        $reservation->stays()
            ->orderBy('sort_order')
            ->get()
            ->each(function (ReservationStay $stay) {
                if ($stay->canCheckIn()) {
                    $this->checkIn($stay);
                }
            });

        $reservation->refresh();

        if (
            $reservation->payment_method === 'credit'
            && $reservation->payment_status === Reservation::PAYMENT_AUTHORIZED
            && filled($reservation->stripe_payment_intent_id)
        ) {
            try {
                app(ReservationPaymentSettlementService::class)->captureAndSync($reservation);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto-capture after check-in failed.', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('チェックインは完了しましたが売上確定に失敗しました')
                    ->body($e->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }

    public function checkOutAll(Reservation $reservation): void
    {
        $reservation->stays()
            ->orderBy('sort_order')
            ->get()
            ->each(function (ReservationStay $stay) {
                if ($stay->canCheckOut()) {
                    $this->checkOut($stay);
                }
            });
    }

    public function isUnitAvailableForReservation(RoomUnit $unit, Reservation $reservation, ?int $ignoreStayId = null): bool
    {
        if ($unit->operation_status !== RoomUnit::OPERATION_IN_SERVICE) {
            return false;
        }

        $dates = $this->stayNightDates($reservation);

        if ($dates === []) {
            return false;
        }

        // 同一予約の別滞在行に既に割り当て済みなら不可
        $assignedToOtherStay = ReservationStay::query()
            ->where('reservation_id', $reservation->id)
            ->where('room_unit_id', $unit->id)
            ->whereNull('checked_out_at')
            ->when($ignoreStayId, fn ($query) => $query->where('id', '!=', $ignoreStayId))
            ->exists();

        if ($assignedToOtherStay) {
            return false;
        }

        $query = RoomUnitDateOccupancy::query()
            ->where('room_unit_id', $unit->id)
            ->where(function ($query) use ($dates): void {
                foreach ($dates as $date) {
                    // SQLite では date が datetime 保存されることがあり whereIn('Y-m-d') が不一致になる
                    $query->orWhereDate('date', $date);
                }
            });

        if ($ignoreStayId) {
            $ignoreStay = ReservationStay::query()->find($ignoreStayId);
            if ($ignoreStay && (int) $ignoreStay->room_unit_id === (int) $unit->id) {
                // 自分の割当を付け替えるときは自予約の占有を除外
                $query->where('reservation_id', '!=', $reservation->id);
            }
        }

        return ! $query->exists();
    }

    /**
     * @return Collection<int, RoomUnit>
     */
    public function assignableUnitsForStay(ReservationStay $stay): Collection
    {
        $reservation = $stay->reservation()->firstOrFail();

        return RoomUnit::query()
            ->assignable()
            ->where('room_id', $reservation->room_id)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->filter(fn (RoomUnit $unit) => $this->isUnitAvailableForReservation($unit, $reservation, $stay->id))
            ->values();
    }
}
