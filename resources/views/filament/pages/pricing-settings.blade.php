<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        @if ($this->canSave())
            <div class="flex justify-start">
                <x-filament::button type="submit">
                    保存
                </x-filament::button>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                閲覧のみです。変更は管理者アカウントで行ってください。
            </p>
        @endif
    </form>
</x-filament-panels::page>
