<?php

namespace App\Filament\Concerns;

trait ScrollsToTop
{
    protected function scrollToTop(): void
    {
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const instant = { top: 0, left: 0, behavior: 'instant' };
                window.scrollTo(instant);
                document.documentElement.scrollTo(instant);
                document.scrollingElement?.scrollTo(instant);
                document.querySelectorAll(
                    '.fi-main, .fi-main-ctn, .fi-page, .fi-modal-open .fi-modal-content, .fi-modal-content'
                ).forEach((el) => el.scrollTo(instant));
            });
        JS);
    }
}
