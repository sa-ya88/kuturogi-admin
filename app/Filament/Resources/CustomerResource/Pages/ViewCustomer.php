<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('基本情報')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('type')
                            ->label('種別')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => $state === 'member' ? '会員' : 'ゲスト')
                            ->color(fn (string $state) => $state === 'member' ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('name')->label('氏名'),
                        Infolists\Components\TextEntry::make('name_kana')->label('フリガナ'),
                        Infolists\Components\TextEntry::make('email')->label('メールアドレス'),
                        Infolists\Components\TextEntry::make('tel')->label('電話'),
                        Infolists\Components\TextEntry::make('birthday')
                            ->label('生年月日')
                            ->date('Y年m月d日'),
                        Infolists\Components\TextEntry::make('gender')
                            ->label('性別')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'male' => '男性',
                                'female' => '女性',
                                'other' => 'その他',
                                default => $state ?? '—',
                            }),
                        Infolists\Components\TextEntry::make('zip_code')->label('郵便番号'),
                        Infolists\Components\TextEntry::make('address')->label('住所')->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('管理情報')
                    ->schema([
                        Infolists\Components\TextEntry::make('tags')
                            ->label('タグ')
                            ->badge()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('メモ')
                            ->placeholder('メモなし')
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('CRM サマリー')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('reservation_count')
                            ->label('予約回数')
                            ->state(fn ($record) => $record->reservation_count),
                        Infolists\Components\TextEntry::make('total_spent')
                            ->label('累計利用額')
                            ->money('jpy')
                            ->state(fn ($record) => $record->total_spent),
                        Infolists\Components\TextEntry::make('last_stayed_at')
                            ->label('最終宿泊')
                            ->date('Y年m月d日'),
                    ]),
            ]);
    }
}
