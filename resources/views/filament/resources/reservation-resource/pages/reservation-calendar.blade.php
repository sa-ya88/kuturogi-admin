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
                        {{ $data['row_header'] }} \ {{ $data['column_header'] }}
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
                        <td>{{ $row['label'] }}</td>
                        @foreach ($data['columns'] as $column)
                            @php($count = $row['cells'][$column]['count'])
                            <td class="kuturogi-calendar__cell">
                                <a
                                    href="{{ $this->cellListUrl($row['row_id'], $column) }}"
                                    @class([
                                        'kuturogi-calendar__link',
                                        'kuturogi-calendar__link--active' => $count > 0,
                                        'kuturogi-calendar__link--empty' => $count === 0,
                                    ])
                                >
                                    {{ $count }}
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($data['columns']) + 1 }}" class="kuturogi-calendar__empty">
                            {{ $data['row_header'] }}データがありません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
