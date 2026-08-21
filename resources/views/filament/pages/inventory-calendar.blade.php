@assets
    @vite(['resources/css/calendars.css', 'resources/js/calendars.js'])
@endassets

<x-filament-panels::page>
    <form wire:submit="applyFilter" class="mb-6">
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-4">
            表示
        </x-filament::button>
    </form>

    @php($data = $this->getCalendarData())

    <div class="kuturogi-calendar" data-kuturogi-calendar>
        <table class="kuturogi-calendar__table">
            <thead>
                <tr>
                    <th>
                        客室 \ {{ $data['column_header'] }}
                    </th>
                    @foreach ($data['columns'] as $column)
                        <th class="text-center font-medium">
                            {{ $this->formatColumnHeader($column) }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    <tr>
                        <td>{{ $row['room'] }}</td>
                        @foreach ($data['columns'] as $column)
                            @php($cell = $row['cells'][$column])
                            @php($value = $cell['value'])
                            <td
                                @class([
                                    'kuturogi-calendar__cell',
                                    'kuturogi-calendar__cell--zero' => $value === 0,
                                    'kuturogi-calendar__cell--low' => $value === 1,
                                    'kuturogi-calendar__cell--tip' => filled($cell['tip'] ?? null),
                                ])
                                @if (filled($cell['tip'] ?? null))
                                    data-tip="{{ $cell['tip'] }}"
                                    tabindex="0"
                                @endif
                            >
                                {{ $value }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($data['columns']) + 1 }}" class="kuturogi-calendar__empty">
                            客室データがありません。「客室」画面から kuturogi 同期を実行してください。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
