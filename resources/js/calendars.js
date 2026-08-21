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
        root.classList.add('kuturogi-calendar--ready');
    });

    bindAvailabilityTips();
}

let availabilityTipsBound = false;

function bindAvailabilityTips() {
    if (availabilityTipsBound) {
        return;
    }

    availabilityTipsBound = true;

    const tooltip = document.createElement('div');
    tooltip.className = 'kuturogi-calendar__tooltip';
    tooltip.hidden = true;
    tooltip.setAttribute('role', 'tooltip');
    document.body.appendChild(tooltip);

    let anchor = null;

    const hide = () => {
        tooltip.hidden = true;
        anchor = null;
    };

    const show = (cell) => {
        const text = cell.getAttribute('data-tip');

        if (!text) {
            hide();

            return;
        }

        anchor = cell;
        tooltip.textContent = text;
        tooltip.hidden = false;

        const rect = cell.getBoundingClientRect();
        tooltip.style.top = `${rect.bottom + 8}px`;
        tooltip.style.left = `${rect.left + rect.width / 2}px`;
    };

    const cellFromEvent = (event) => {
        const target = event.target;

        return target instanceof Element
            ? target.closest('[data-kuturogi-calendar] [data-tip]')
            : null;
    };

    document.addEventListener('pointerover', (event) => {
        const cell = cellFromEvent(event);

        if (cell) {
            show(cell);
        }
    });

    document.addEventListener('pointerout', (event) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        const cell = cellFromEvent(event);

        if (!cell) {
            return;
        }

        const next = event.relatedTarget;

        if (next instanceof Node && cell.contains(next)) {
            return;
        }

        hide();
    });

    document.addEventListener('focusin', (event) => {
        const cell = cellFromEvent(event);

        if (cell) {
            show(cell);
        }
    });

    document.addEventListener('click', (event) => {
        const cell = cellFromEvent(event);

        if (!cell) {
            hide();

            return;
        }

        if (anchor === cell && !tooltip.hidden) {
            return;
        }

        show(cell);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });

    window.addEventListener('scroll', hide, true);
}
