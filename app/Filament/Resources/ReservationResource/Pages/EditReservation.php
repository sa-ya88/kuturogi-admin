<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\ReservationResource\Concerns\RemembersReservationListFilters;
use App\Models\Customer;
use App\Models\Reservation;
use App\Services\KuturogiSyncService;
use App\Services\ReservationPaymentSettlementService;
use App\Services\ReservationStayService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReservation extends EditRecord
{
    use RemembersReservationListFilters;

    protected static string $resource = ReservationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $filters = static::extractListFiltersFromQuery(request()->query());

        if ($filters !== []) {
            static::rememberListFilters($filters);
        }

        app(ReservationStayService::class)->syncStaysForReservation($this->record);
        $this->record->refresh()->load(['stays.roomUnit']);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $customer = Customer::query()->find($data['customer_id'] ?? null);

        if ($customer) {
            $data['guest_name'] = $customer->name;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $stayService = app(ReservationStayService::class);
        $stayService->syncStaysForReservation($this->record->fresh());

        if ($this->record->status !== Reservation::STATUS_CANCELLED) {
            try {
                $stayService->autoAssignUnits($this->record->fresh(['stays']));
            } catch (\Throwable $e) {
                Notification::make()
                    ->title('自動部屋割に失敗しました')
                    ->body($e->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::reservationListUrl();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('capturePayment')
                ->label('売上確定')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('売上を確定しますか？')
                ->modalDescription('与信済みのカード決済をキャプチャ（売上確定）します。')
                ->visible(fn () => $this->record->payment_method === 'credit'
                    && $this->record->payment_status === Reservation::PAYMENT_AUTHORIZED
                    && filled($this->record->stripe_payment_intent_id)
                    && $this->record->status !== Reservation::STATUS_CANCELLED)
                ->action(function (ReservationPaymentSettlementService $settlement): void {
                    try {
                        $this->record = $settlement->captureAndSync($this->record);
                        Notification::make()->title('売上を確定しました')->success()->send();
                        $this->refreshFormData(['payment_status', 'paid_at']);
                    } catch (\Throwable $e) {
                        Notification::make()->title('売上確定に失敗しました')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('markLocalReceived')
                ->label('現地受領')
                ->icon('heroicon-o-currency-yen')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('現地払いを受領済みにしますか？')
                ->visible(fn () => $this->record->payment_method === 'local'
                    && $this->record->payment_status === Reservation::PAYMENT_UNPAID
                    && $this->record->status !== Reservation::STATUS_CANCELLED)
                ->action(function (ReservationPaymentSettlementService $settlement): void {
                    try {
                        $this->record = $settlement->markLocalReceivedAndSync($this->record);
                        Notification::make()->title('現地受領を記録しました')->success()->send();
                        $this->refreshFormData(['payment_status', 'paid_at']);
                    } catch (\Throwable $e) {
                        Notification::make()->title('現地受領の記録に失敗しました')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('checkInAll')
                ->label('チェックイン')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === Reservation::STATUS_CONFIRMED
                    && $this->record->stays()->whereNull('checked_in_at')->whereNotNull('room_unit_id')->exists())
                ->action(function (ReservationStayService $stayService): void {
                    try {
                        $stayService->checkInAll($this->record);
                        $this->record->refresh();
                        Notification::make()->title('チェックインしました')->success()->send();
                        $this->refreshFormData(['stay_status', 'payment_status', 'paid_at']);
                    } catch (\Throwable $e) {
                        Notification::make()->title('チェックイン失敗')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('checkOutAll')
                ->label('チェックアウト')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->stays()
                    ->whereNotNull('checked_in_at')
                    ->whereNull('checked_out_at')
                    ->exists())
                ->action(function (ReservationStayService $stayService): void {
                    try {
                        $stayService->checkOutAll($this->record);
                        Notification::make()->title('チェックアウトしました')->success()->send();
                        $this->refreshFormData(['stay_status']);
                    } catch (\Throwable $e) {
                        Notification::make()->title('チェックアウト失敗')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('cancelReservation')
                ->label('予約をキャンセル')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('予約をキャンセルしますか？')
                ->modalDescription('キャンセルすると予約状況が変更され、在庫が復元されます。事前決済がある場合は与信取消または返金処理も行います。')
                ->modalSubmitActionLabel('キャンセルする')
                ->visible(fn () => $this->record->status === Reservation::STATUS_CONFIRMED)
                ->action(function (KuturogiSyncService $syncService) {
                    try {
                        $this->record = $syncService->cancelOnKuturogi($this->record);

                        $body = null;
                        if ($this->record->cancel_fee_uncollected) {
                            $body = '違約金 ¥'.number_format((int) $this->record->cancel_fee_amount).' の自動決済に失敗しました。手動でフォローしてください。';
                        } elseif ((int) $this->record->cancel_fee_amount > 0) {
                            $body = '違約金 ¥'.number_format((int) $this->record->cancel_fee_amount).' を決済しました。';
                        }

                        Notification::make()
                            ->title('予約をキャンセルしました')
                            ->body($body)
                            ->success()
                            ->send();

                        $this->redirect(static::reservationListUrl(), navigate: true);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('キャンセル失敗')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
