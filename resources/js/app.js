// US phone input mask (active only when the app country is US).
// Usage: <input x-data x-phone-mask wire:model="phone">
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('phone-mask', (el, _directive, { cleanup }) => {
        if (document.documentElement.dataset.country !== 'US') {
            return;
        }

        let formatting = false;

        const handler = () => {
            if (formatting) {
                return;
            }

            const value = el.value;

            // Leave international numbers and anything longer than 10 digits as typed
            if (value.startsWith('+')) {
                return;
            }

            const digits = value.replace(/\D/g, '');

            if (digits.length > 10) {
                return;
            }

            let out = digits;
            if (digits.length > 6) {
                out = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
            } else if (digits.length > 3) {
                out = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
            } else if (digits.length > 0) {
                out = '(' + digits;
            }

            if (value !== out) {
                formatting = true;
                el.value = out;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                formatting = false;
            }
        };

        el.addEventListener('input', handler);
        cleanup(() => el.removeEventListener('input', handler));
    });
});
