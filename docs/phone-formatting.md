# Phone Number Formatting (Country-based)

## Overview
Phone numbers are formatted for **display** based on the configured country (`config('app.country')`, same switch used for address formats and weather units). When the country is `US`, numbers display as `(305) 555-1234`. For any other country, or for numbers that don't look like US numbers, the value is shown exactly as typed.

This is display-only: stored values are never changed, inputs stay free-form, and `tel:` links keep the raw stored value.

## How It Works

**Trait:** `app/Models/Concerns/HasFormattedPhone.php`

- `formatted_phone` accessor — formats the model's `phone` attribute
- `Model::formatPhone(?string $phone)` static — usable anywhere

Formatting rules (US mode only):
1. Strip all non-digits
2. 11 digits starting with `1` → drop the leading `1`
3. Exactly 10 digits → `(XXX) XXX-XXXX`
4. Anything else (extensions, short numbers, foreign numbers) → returned as typed

## Models Using the Trait
`Client`, `Project`, `JobSite`, `Subcontractor`, `SubcontractorEmployee`, `Supplier`, `User`

## Usage in Views
```blade
{{ $client->formatted_phone ?? 'N/A' }}              {{-- display --}}
<a href="tel:{{ $client->phone }}">...</a>            {{-- tel: links keep raw value --}}
```

All existing display usages across the index/show/overview pages were switched to `formatted_phone`; `@if($x->phone)` conditions and `tel:` hrefs intentionally still use the raw attribute.

## Adding a New Phone-Bearing Model
1. `use HasFormattedPhone;` in the model (requires a `phone` attribute)
2. Display with `formatted_phone`, keep `tel:` hrefs on `phone`

## Input Auto-Formatting (US only)

Phone inputs format as the user types via a custom Alpine directive registered in `resources/js/app.js`:

```blade
<input type="text" wire:model.live="phone" x-data x-phone-mask ...>
```

- Active only when `<html data-country="US">` (set from `config('app.country')` in the three layouts: `components/layouts/app.blade.php`, `guest.blade.php`, `app/sidebar.blade.php`). In any other country the directive is a no-op.
- Progressive mask: `3055551234` becomes `(305) 555-1234` as typed; backspacing rebuilds the format from remaining digits.
- Values starting with `+` (international) and values with more than 10 digits are left exactly as typed.
- The directive re-dispatches the `input` event after reformatting so Livewire's `wire:model` syncs the formatted value (i.e., new phones are stored formatted). Existing stored values are not touched until the field is edited.
- Applied to all phone inputs across client, project, job site, subcontractor (incl. employee form), supplier, user, profile, and company forms.

To mask a new phone input, add `x-data x-phone-mask` to it.
