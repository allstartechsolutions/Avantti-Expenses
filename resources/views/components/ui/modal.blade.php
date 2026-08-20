@props(['name', 'show' => false, 'maxWidth' => '2xl', 'layer' => 'base'])

@php
// 'full' fills the viewport: a working surface rather than a dialog.
$isFull = $maxWidth === 'full';

$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
    '6xl' => 'sm:max-w-6xl',
    'full' => 'max-w-none',
][$maxWidth];

// A modal opened from inside another one has to paint above it, or the click
// looks like it did nothing. The z-index is inline on purpose: a Tailwind
// class would need the CSS bundle rebuilt before the fix took effect.
$zIndex = $layer === 'top' ? 60 : 50;
@endphp

<div
    x-data="{
        name: '{{ $name }}',
        show: @js($show),
        /*
         * Which modals are open is read from the DOM rather than kept in a
         * variable: Livewire can remove an open modal from the page outright,
         * and a counter would then never come back down — leaving the page
         * permanently unable to scroll.
         */
        openModals() {
            return [...document.querySelectorAll('[data-ui-modal-open=\'true\']')];
        },
        isTopmost() {
            const open = this.openModals();
            if (! open.length) return false;
            const top = open.reduce((best, el) =>
                (parseInt(el.style.zIndex || 0, 10) >= parseInt(best.style.zIndex || 0, 10)) ? el : best
            );
            return top === $el;
        },
        syncScrollLock() {
            // The last modal to close gives the page its scrolling back; a
            // child closing must not unlock the page under its parent.
            if (this.openModals().length) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        },
        init() {
            this.$nextTick(() => this.syncScrollLock());

            this.$watch('show', value => {
                this.$nextTick(() => this.syncScrollLock());

                if (value) {
                    setTimeout(() => this.firstFocusable()?.focus(), 100);
                    this.$dispatch('modal-opened', this.name);
                } else {
                    this.$dispatch('modal-closed', this.name);
                }
            });
        },
        destroy() {
            // Removed from the page while open — release the lock if it was
            // the only thing holding it.
            this.$nextTick(() => this.syncScrollLock());
        },
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-on:open-modal.window="$event.detail == name ? show = true : null"
    x-on:close-modal.window="$event.detail == name ? show = false : null"
    x-on:close.stop="show = false"
    {{-- Escape leaving a full-screen preview must not also close the modal behind it. --}}
    x-on:keydown.escape.window="if (show && isTopmost() && ! document.fullscreenElement) show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    :data-ui-modal-open="show ? 'true' : 'false'"
    data-ui-modal-open="{{ $show ? 'true' : 'false' }}"
    class="fixed inset-0 overflow-y-auto"
    style="display: {{ $show ? 'block' : 'none' }}; z-index: {{ $zIndex }};"
>
    <!-- Backdrop -->
    <div
        x-show="show"
        x-on:click="show = false"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80 transition-opacity"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    <!-- Modal Content -->
    <div class="flex min-h-full justify-center {{ $isFull ? 'items-stretch p-0' : 'items-center p-4' }}">
        <div
            x-show="show"
            x-on:click.stop
            class="relative w-full {{ $maxWidthClass }} bg-white dark:bg-slate-800 shadow-xl transform transition-all {{ $isFull ? 'min-h-screen rounded-none' : 'rounded-lg' }}"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            {{ $slot }}
        </div>
    </div>
</div>
