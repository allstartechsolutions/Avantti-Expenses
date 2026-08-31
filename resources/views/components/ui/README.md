# Button Components Documentation

## Overview

This project includes two reusable button components that follow the Avantti design system with consistent styling and the brand color `#3F5189`.

## Components

### 1. `<x-ui.button>` - Normal Button
A full-featured button component with text and optional icons.

### 2. `<x-ui.icon-button>` - Icon-Only Button  
A compact button component that displays only an icon.

## Available Props

### Button Component (`<x-ui.button>`)

| Prop | Type | Default | Options | Description |
|------|------|---------|---------|-------------|
| `variant` | string | `'primary'` | `primary`, `secondary`, `success`, `warning`, `danger`, `ghost`, `outline` | Button style variant |
| `size` | string | `'md'` | `sm`, `md`, `lg`, `xl` | Button size |
| `icon` | string | `null` | Any icon name from the icon component | Icon to display |
| `icon-position` | string | `'left'` | `left`, `right` | Icon position relative to text |
| `href` | string | `null` | Any URL | Makes button render as `<a>` tag |
| `type` | string | `'button'` | `button`, `submit`, `reset` | Button type (when not using href) |
| `disabled` | boolean | `false` | `true`, `false` | Disabled state |

### Icon Button Component (`<x-ui.icon-button>`)

| Prop | Type | Default | Options | Description |
|------|------|---------|---------|-------------|
| `variant` | string | `'primary'` | `primary`, `secondary`, `success`, `warning`, `danger`, `ghost`, `outline` | Button style variant |
| `size` | string | `'md'` | `sm`, `md`, `lg`, `xl` | Button size |
| `icon` | string | `null` | Any icon name from the icon component | Icon to display |
| `href` | string | `null` | Any URL | Makes button render as `<a>` tag |
| `type` | string | `'button'` | `button`, `submit`, `reset` | Button type (when not using href) |
| `disabled` | boolean | `false` | `true`, `false` | Disabled state |

## Available Icons

The icon component supports the following icons:
- `plus` - Plus/Add icon
- `edit` - Edit/Pencil icon
- `trash` - Delete/Trash icon
- `save` - Save/Tag icon
- `search` - Search/Magnifying glass icon
- `download` - Download arrow icon
- `upload` - Upload arrow icon
- `eye` - View/Eye icon
- `settings` - Settings/Gear icon
- `heart` - Heart/Like icon
- `star` - Star/Favorite icon
- `check` - Checkmark icon
- `x` - X/Close icon
- `arrow-right` - Right arrow icon
- `arrow-left` - Left arrow icon
- `chevron-down` - Down chevron icon
- `chevron-up` - Up chevron icon

## Usage Examples

### Basic Usage

```html
<!-- Primary button -->
<x-ui.button variant="primary">Save</x-ui.button>

<!-- Icon-only button -->
<x-ui.icon-button variant="primary" icon="plus" />
```

### With Icons

```html
<!-- Button with left icon -->
<x-ui.button variant="primary" icon="plus">Add User</x-ui.button>

<!-- Button with right icon -->
<x-ui.button variant="outline" icon="download" :icon-position="'right'">Export</x-ui.button>
```

### Different Variants

```html
<x-ui.button variant="primary">Primary</x-ui.button>
<x-ui.button variant="secondary">Secondary</x-ui.button>
<x-ui.button variant="success">Success</x-ui.button>
<x-ui.button variant="warning">Warning</x-ui.button>
<x-ui.button variant="danger">Danger</x-ui.button>
<x-ui.button variant="ghost">Ghost</x-ui.button>
<x-ui.button variant="outline">Outline</x-ui.button>
```

### Different Sizes

```html
<x-ui.button variant="primary" size="sm">Small</x-ui.button>
<x-ui.button variant="primary" size="md">Medium</x-ui.button>
<x-ui.button variant="primary" size="lg">Large</x-ui.button>
<x-ui.button variant="primary" size="xl">Extra Large</x-ui.button>
```

### As Links

```html
<x-ui.button variant="primary" href="/users">View Users</x-ui.button>
<x-ui.icon-button variant="secondary" icon="settings" href="/settings" />
```

### Form Buttons

```html
<form>
    <x-ui.button variant="secondary">Cancel</x-ui.button>
    <x-ui.button variant="primary" type="submit" icon="save">Save Changes</x-ui.button>
</form>
```

### Disabled State

```html
<x-ui.button variant="primary" disabled>Disabled Button</x-ui.button>
<x-ui.icon-button variant="primary" icon="trash" disabled />
```

## Common Use Cases

### Table Actions
```html
<x-ui.icon-button variant="ghost" icon="eye" size="sm" title="View" />
<x-ui.icon-button variant="ghost" icon="edit" size="sm" title="Edit" />  
<x-ui.icon-button variant="ghost" icon="trash" size="sm" title="Delete" />
```

### Form Actions
```html
<div class="flex gap-3">
    <x-ui.button variant="secondary">Cancel</x-ui.button>
    <x-ui.button variant="primary" icon="save">Save Changes</x-ui.button>
</div>
```

### Toolbar
```html
<div class="flex gap-2">
    <x-ui.button variant="outline" icon="plus" size="sm">New</x-ui.button>
    <x-ui.icon-button variant="secondary" icon="download" size="sm" title="Export" />
    <x-ui.icon-button variant="secondary" icon="settings" size="sm" title="Settings" />
</div>
```

## Design System

The button components follow the Avantti design system:
- **Primary Color**: `#3F5189` with gradient to `#4A5A96`
- **Consistent Spacing**: Uses Tailwind's spacing scale
- **Accessible**: Includes focus states and proper contrast ratios
- **Responsive**: Works well on all screen sizes
- **Dark Mode**: Fully supports dark mode variants

## Notes

- All buttons include proper focus styles for accessibility
- Icon buttons should include `title` attribute for accessibility
- Components are built with Tailwind CSS utility classes
- The gradient primary variant uses the Avantti brand colors
- All buttons have smooth hover and transition effects
---

# `<x-ui.search-select>` — type-to-search picker

For a set too large to scroll: hundreds of budget lines, thousands of vendors. The search
runs **on the server**, so the rows never leave the database until somebody types.

```blade
<x-ui.search-select
    wire:model.live.debounce.300ms="supplierSearch"
    :label="__('Supplier')"
    :placeholder="__('Search by name or contact...')"
    :hint="__('Optional — who is being proposed for this.')"
    :results="$supplierResults"          {{-- [['id'=>1,'label'=>'…','meta'=>'…'], …] --}}
    :selectedId="$supplier_id"
    :selectedLabel="$supplierLabel"
    select="selectSupplier"              {{-- Livewire method, called with the id --}}
    clear="clearSupplier"
    :search="$supplierSearch"
    :minChars="2"
    :total="$supplierCount"
    error="supplier_id"
    :empty="__('No vendor matches. Check the spelling, or register the vendor first.')"
    :unavailable="$supplierCount === 0 ? __('No vendors are registered yet.') : null" />
```

| Prop | Purpose |
|---|---|
| `wire:model…` | The string property the box writes to. Pass the debounce you want; it is forwarded verbatim. |
| `results` | Rows to offer: `id`, `label`, optional `meta` (the small second line). Cap them server-side. |
| `selectedId` / `selectedLabel` | What is linked right now. Renders the green check and the "Linked: …" read-back. |
| `select` / `clear` | Livewire method names. `select` receives the id — **re-check that it belongs** inside it. |
| `search` | The current text, so the component can tell "typed nothing" from "typed and found nothing". |
| `minChars`, `total` | Shown in the panel before a search runs. |
| `error` | Validation key; renders the message and reddens the border. |
| `empty` | Advice under "Nothing matches …". |
| `unavailable` | There is nothing to search at all — replaces the field with this sentence. |

**What the component gives you:** ↑↓ to browse, ↵ to take the highlighted row, Esc and
click-away to close, a spinner while the debounced request is in flight, a clear button,
`role="combobox"` / `role="listbox"` / `role="option"`, dark mode, and both the empty and
the not-yet-searched states.

**What the component does not do, and the caller must:** run the query, cap it, and re-verify
the id in `select…()`. The Livewire side of the approvals form
(`app/Livewire/Approval/ApprovalForm.php`, "The pickers" and "Choices") is the reference
implementation — including the `updated…Search()` hook that unlinks the id the moment the
text stops being the label of what was chosen. Without that hook a user types over a chosen
supplier and saves a record pointing at a vendor whose name is no longer on the screen.

---

# `<x-ui.file-drop>` — drag-and-drop for a form's own uploads

**Every upload in this system is drag-and-drop** (the rule lives in `CLAUDE.md`). Use this
one when the record may not exist yet — a create/edit form that holds its files and stores
them on save. When the record already exists, use `<x-ui.file-uploader>` instead: it sends
the bytes straight to storage with presigned URLs and never through PHP.

```blade
<x-ui.file-drop wire:model="newUploads">
    {{-- The slot is the queue: what is waiting, its size, a Remove for each --}}
    @error('newUploads.*') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

    @foreach($uploads as $index => $upload)
        …{{ $upload->getClientOriginalName() }}…
        <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="discardUpload({{ $index }})"
                          :title="__('Remove :file', ['file' => $upload->getClientOriginalName()])" />
    @endforeach
</x-ui.file-drop>
```

| Prop | Default | Purpose |
|---|---|---|
| `wire:model` | — | The Livewire property the files land on. Required. |
| `label` | *Drop files here, or* | The line inside the zone. |
| `hint` | *Up to :size per file.* | The line under it. |
| `accept` | `DocumentSettings::acceptAttribute()` | Override for a single-purpose field (a logo, a CSV). |
| `multiple` | `true` | `false` for a one-file field. |
| `disabled` | `false` | Greys the zone and stops the drop. |

Drop and click follow the same path — the drop hands its FileList to the hidden input and
fires its change event, so Livewire does the rest. The zone shows real upload progress from
Livewire's own `livewire-upload-*` events.

**The one thing the Livewire side must do** — a plain `wire:model` file input **replaces**
its selection on every pick, so a second drop would silently wipe the first. Bind the zone
to a scratch property and move the files across:

```php
public array $uploads = [];     // the queue, stored on save
public array $newUploads = [];  // what the drop zone writes to

public function updatedNewUploads(): void
{
    // Emptied whatever happens: a file left in here is invisible (the list on
    // screen is the queue, not this) and would fail every later save with no
    // button to remove it.
    $dropped = $this->newUploads;
    $this->newUploads = [];

    foreach ($dropped as $file) {
        if (Validator::make(['file' => $file], ['file' => $this->fileRule()])->fails()) {
            $refused[] = $file->getClientOriginalName();
            continue;
        }

        $this->uploads[] = $file;
    }

    // addError, never validate(): Livewire's validate() ends with a bare
    // resetErrorBag(), so it would wipe the messages on the rest of the form.
    if ($refused ?? false) { $this->addError('newUploads', __('…')); }
}
```

Give `newUploads` **no rule in `rules()`** — the hook empties it on every change, so a
rule there could only ever fail on a file the user cannot see. Render all three error keys
next to the zone: `uploads.*` (your save), `newUploads` (your size check) and
`newUploads.*` — Livewire's own temporary-upload rules, which refuse anything over 12 MB
before it reaches PHP at all. And have the discard action call `$file->delete()`, the way
Livewire's own `_removeUpload()` does, or the temporary file sits in `livewire-tmp` until
the daily sweep.

**Never name that method `removeUpload()`** — `$wire.removeUpload(property, tmpFilename)` is
part of Livewire's own API, so a `wire:click` on it is intercepted in the browser and never
reaches PHP; the request dies with `Property [$0] not found`. Both forms in this module had
that bug. Use `discardUpload()`, and keep it out of the reserved list checked by
`ApprovalFormTest::test_no_action_is_named_after_a_livewire_api_method`.

`app/Livewire/Approval/ApprovalForm.php` is the reference implementation.

---

# `<x-ui.date-input>` — a date field that reads the way this install writes dates

**Never `<input type="date">`.** A native date input renders in the **browser's** locale,
which has nothing to do with `config('app.country')`: a Brazilian install seen through an
en-US browser asks for `mm/dd/yyyy`, and no attribute or CSS rule changes that.

```blade
<x-ui.date-input wire:model="expense_date" />
<x-ui.date-input wire:model.live="fromDate" class="{{ $field }}" />
<x-ui.date-input wire:model="expense_paid_date" :disabled="$amountsLocked" />
```

| Prop | Default | Purpose |
|---|---|---|
| `wire:model` | — | The property. Holds `Y-m-d` throughout; `.live` is carried through. |
| `placeholder` | `dd/mm/aaaa` / `mm/dd/yyyy` | What the box asks for. |
| `disabled`, `readonly` | `false` | Either one hides the calendar button. |
| `id` | derived from the property | Only pass one if a `<label for>` needs it. |

The box on screen is a text field in this install's order, digits masked as they are typed,
with strict parsing — 31/02/2026 is refused rather than quietly becoming 3 March, and a
half-typed date clears the value so what is saved is always what is shown. The native control
stays on the page, hidden and out of the tab order, only so the calendar button can call
`showPicker()` on it. **What crosses to Livewire is `Y-m-d`, exactly as before**, so no rule,
query or component needs to know this exists.

Behaviour: `dateInput()` in `resources/js/app.js` — **changing it needs `npm run build`**.

**The trap that cost an afternoon:** a Blade directive inside a component tag stops Blade
compiling the tag at all. `<x-ui.date-input @disabled($flag) />` is left in the page as
literal text and the field simply is not there, with no error anywhere. Write
`:disabled="$flag"`. `DateFormatSweepTest` now fails on any component tag carrying
`@disabled` / `@checked` / `@readonly` / `@required` / `@class` / `@style`.

Printing a date is the other half: `->appDate()` and friends, in
[docs/date-formatting.md](../../../docs/date-formatting.md).

---

# `<x-ui.modal>` — layering

A modal declares a **resting** layer (`layer="base"`, or `layer="top"` for one that opens
from inside another). On opening it raises itself one step above whatever is already open,
and on closing it drops back. Two modals declaring the same layer are therefore ordered by
**when they opened**, not by the order their partials happen to be `@include`d — which is how
the documents "New Version" panel came to open *underneath* the document it was raised from.

The z-index is a reactive `:style` binding rather than a write to `$el.style`: a Livewire
re-render morphs attributes back to what the server sent, which would drop a raised modal
back under its parent mid-use.
