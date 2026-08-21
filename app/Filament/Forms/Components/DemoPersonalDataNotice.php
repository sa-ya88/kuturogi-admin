<?php

namespace App\Filament\Forms\Components;

use App\Support\DemoMode;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class DemoPersonalDataNotice extends Placeholder
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hiddenLabel()
            ->columnSpanFull()
            ->hidden(fn (): bool => ! DemoMode::enabled())
            ->content(fn (): HtmlString => new HtmlString(
                view('filament.components.demo-personal-data-notice')->render()
            ));
    }

    public static function make(string $name = 'demo_personal_data_notice'): static
    {
        return parent::make($name);
    }
}
