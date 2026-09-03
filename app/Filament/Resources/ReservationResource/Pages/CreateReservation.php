<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\SalesRecord;
use App\Services\KuturogiSyncService;
use App\Services\ReservationStayService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = 'admin';
        $data['adult_count'] = $data['adult_count'] ?? $data['guest_count'];
        $data['child_count'] = $data['child_count'] ?? 0;
        $data['stay_status'] = $data['stay_status'] ?? Reservation::STAY_STATUS_RESERVED;
        $data['payment_method'] = $data['payment_method'] ?? 'local';
        $data['payment_status'] = $data['payment_status'] ?? Reservation::PAYMENT_UNPAID;

        $customer = Customer::query()->find($data['customer_id'] ?? null);

        if ($customer) {
            $data['guest_name'] = $customer->name;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $reservation = $this->record;

        $stayService = app(ReservationStayService::class);
        $stayService->syncStaysForReservation($reservation->fresh());

        try {
            $stayService->autoAssignUnits($reservation->fresh(['stays']));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('自動部屋割に失敗しました')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }

        try {
            app(KuturogiSyncService::class)->pushReservationToKuturogi($reservation->fresh());

            SalesRecord::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'amount' => $reservation->total_price,
                    'recorded_at' => now(),
                    'status' => SalesRecord::STATUS_RECORDED,
                ]
            );

            Notification::make()
                ->title('kuturogi へ予約を反映しました')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('kuturogi 連携エラー')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
