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

            // Leave international numbers as typed
            if (value.startsWith('+')) {
                return;
            }

            let digits = value.replace(/\D/g, '');

            // 11 digits with a leading 1 -> treat as the 10-digit number
            if (digits.length === 11 && digits.startsWith('1')) {
                digits = digits.slice(1);
            }

            // Cap at 10 digits so the field always holds a well-formed value
            // (longer numbers should be entered with a + prefix)
            if (digits.length > 10) {
                digits = digits.slice(0, 10);
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
                // Keep the caret next to the digit it was at before reformatting
                const digitsBeforeCaret = value
                    .slice(0, el.selectionStart ?? value.length)
                    .replace(/\D/g, '')
                    .length;

                formatting = true;
                el.value = out;

                let pos = 0;
                let seen = 0;
                while (pos < out.length && seen < digitsBeforeCaret) {
                    if (/\d/.test(out[pos])) {
                        seen++;
                    }
                    pos++;
                }
                try {
                    el.setSelectionRange(pos, pos);
                } catch (e) {
                    // selection API unavailable for this input type; caret falls to the end
                }

                el.dispatchEvent(new Event('input', { bubbles: true }));
                formatting = false;
            }
        };

        el.addEventListener('input', handler);
        cleanup(() => el.removeEventListener('input', handler));
    });
});
