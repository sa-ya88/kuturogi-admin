<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesStaffReadOnlyMutations;
use App\Filament\Resources\NewsResource\Pages;
use App\Models\News;
use App\Support\FieldLimits;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NewsResource extends Resource
{
    use AuthorizesStaffReadOnlyMutations;

    protected static ?string $model = News::class;

    protected static ?string $slug = 'news';

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'お知らせ';

    protected static ?string $modelLabel = 'お知らせ';

    protected static ?string $pluralModelLabel = 'お知らせ';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('お知らせ')
                ->description('保存すると顧客サイトのトップ「お知らせ」とニュース一覧へ反映されます。')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('タイトル')
                        ->required()
                        ->maxLength(FieldLimits::TITLE),
                    Forms\Components\DatePicker::make('published_at')
                        ->label('掲載日')
                        ->required()
                        ->native(false)
                        ->displayFormat('Y/m/d')
                        ->default(now()),
                    Forms\Components\Textarea::make('content')
                        ->label('本文')
                        ->required()
                        ->rows(8)
                        ->maxLength(FieldLimits::DESCRIPTION)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('published_at')
                    ->label('掲載日')
                    ->date('Y年m月d日')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('タイトル')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('content')
                    ->label('本文')
                    ->limit(40)
                    ->wrap(),
            ])
            ->defaultSort('published_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (News $record): string => static::getUrl(
                auth()->user()?->isAdmin() ? 'edit' : 'view',
                ['record' => $record],
            ));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('お知らせ')->schema([
                Infolists\Components\TextEntry::make('title')->label('タイトル'),
                Infolists\Components\TextEntry::make('published_at')
                    ->label('掲載日')
                    ->date('Y年m月d日'),
                Infolists\Components\TextEntry::make('content')
                    ->label('本文')
                    ->prose()
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'view' => Pages\ViewNews::route('/{record}'),
            'edit' => Pages\EditNews::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
