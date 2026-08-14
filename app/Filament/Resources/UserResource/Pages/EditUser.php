<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return 'ユーザーを更新しました';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $record */
        $record = $this->record;

        if ($record->isAdmin()
            && ($data['role'] ?? null) === User::ROLE_STAFF
            && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1) {
            Notification::make()
                ->title('権限を変更できません')
                ->body('管理者が1名のみのため、このユーザーの権限を一般に変更できません。')
                ->danger()
                ->send();

            throw new Halt();
        }

        if ($record->is(auth()->user()) && ($data['role'] ?? null) === User::ROLE_STAFF) {
            Notification::make()
                ->title('権限を変更できません')
                ->body('ログイン中のユーザー自身の権限を一般に変更することはできません。')
                ->danger()
                ->send();

            throw new Halt();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
