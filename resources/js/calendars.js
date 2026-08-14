/**
 * Shared behavior for reservation / inventory calendar grids.
 */
document.addEventListener('DOMContentLoaded', () => {
    initCalendars();
});

document.addEventListener('livewire:navigated', () => {
    initCalendars();
});

function initCalendars() {
    document.querySelectorAll('[data-kuturogi-calendar]').forEach((root) => {
        if (root.dataset.calendarReady === '1') {
            return;
        }

        root.dataset.calendarReady = '1';
        root.classList.add('kuturogi-calendar--ready');
    });
}
