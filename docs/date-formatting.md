# Dates and Times

## Overview

A date is written differently in the two countries this product ships to, and until 31 Aug
2026 every screen decided for itself how to write one. A Brazilian install showed
`Aug 31, 2026` on **144 screens** — the US order *and* English month names, which no locale
setting fixes, because `format()` never translates.

There were four habits in the codebase at the same time:

| Habit | Count | On a Brazilian install |
|---|---|---|
| `format('M d, Y')` | 133 | `Aug 31, 2026` — wrong order, wrong language |
| `format('m/d/Y')` | 11 | `08/31/2026` — wrong order |
| `format('d/m/Y')` | 29 | right — and wrong on a US install |
| `$dateFormat = country === 'BR' ? … : …` | 39 | right, and copied into 39 files |

There is one answer now, and it lives in `AppServiceProvider::registerDateMacros()`.

## Writing a date

```blade
{{ $expense->expense_date->appDate() }}
{{ $batch->created_at->appDateTime() }}
```

| Call | Brazil | United States | Use it for |
|---|---|---|---|
| `->appDate()` | 31 ago 2026 | Aug 31, 2026 | Almost everything. |
| `->appTime()` | 14:30 | 2:30 PM | A time on its own. |
| `->appDateTime()` | 31 ago 2026 14:30 | Aug 31, 2026 2:30 PM | Audit lines, history rows. |
| `->appDateLong()` | 31 de agosto de 2026 | August 31, 2026 | The face of a document. |
| `->appDateShort()` | 31 ago | Aug 31 | A column too narrow for a year. |
| `->appDateNumeric()` | 31/08/2026 | 08/31/2026 | **The date input, and nothing else.** |

The month is a word on purpose. It is what this product has always shown, and it cannot be
misread the way a bare `08/31` can when the reader is used to the other order.
`appDateLong()` and the month-name forms go through `translatedFormat`, so the word is in the
reader's language rather than always English — which was the original complaint.

The macros are registered on `Carbon`, so they work on every flavour of it: a model attribute
(`Illuminate\Support\Carbon`), `now()`, a parsed string, and `CarbonImmutable`.

### What is deliberately *not* routed through them

Machine formats stay exactly as they are:

| Format | What it is |
|---|---|
| `Y-m-d` | The value of a date field, and every date comparison. |
| `Y-m` | A grouping key on a report. |
| `G`, `H:i` | An hour of the day; a time input's value. |
| `o-\WW` | An ISO week number. |

None of these is read by a person, and none may move when the country does.

## Asking for a date

**Never `<input type="date">`.** A native date input renders in the **browser's** locale,
which has nothing to do with `config('app.country')`: a Brazilian company whose staff run an
en-US browser were typing into `mm/dd/yyyy` all day, and there is no attribute, CSS rule or
`lang` setting that changes it.

```blade
<x-ui.date-input wire:model="expense_date" />
<x-ui.date-input wire:model.live="fromDate" class="{{ $field }}" />
<x-ui.date-input wire:model="expense_paid_date" :disabled="$amountsLocked" />
```

| Prop | Purpose |
|---|---|
| `wire:model` | The property, which holds `Y-m-d` throughout. `.live` is carried through. |
| `placeholder` | Defaults to `dd/mm/aaaa` or `mm/dd/yyyy`. |
| `:disabled`, `:readonly` | Both hide the calendar button. |
| `id` | Defaults to one derived from the property name. |

**What the reader sees** is a text field in this install's order, with the digits masked as
they are typed and a calendar button. **What the server sees is unchanged** — `Y-m-d`, exactly
as a native input sent it — so no component, validation rule or query needed touching when the
89 inputs were converted.

The native control is still on the page: hidden, `aria-hidden`, out of the tab order, and
used only so the calendar button can call `showPicker()` on it. Its own locale never reaches
the reader.

Parsing is strict: a date that does not exist — 31/02/2026 — is rejected rather than being
quietly rolled into March, and a half-typed date clears the value instead of leaving the last
complete one behind, so what is saved is always what is on screen.

The behaviour is `dateInput()` in `resources/js/app.js`. **Changing it needs `npm run build`.**

## Two traps

**A Blade directive inside a component tag stops the tag compiling.** Written as

```blade
<x-ui.date-input wire:model="expense_paid_date" @disabled($amountsLocked) />
```

Blade does not recognise the tag as a component at all: it leaves `<x-ui.date-input …/>` in
the page as literal text, the browser makes nothing of an unknown element, and the field is
simply absent — whether or not the flag is true. Nothing errors. It reached the expense form's
payment section that way. Use `:disabled="$flag"`.

**A variable named `$dateFormat` did not always hold a date format.** One screen used the name
for a date *and time* format; a mechanical replacement turned it into `appDate()` and silently
dropped the time. Read what a variable holds, not what it is called.

## The guards

`tests/Feature/DateFormatSweepTest.php` fails if:

- a display format is written by hand anywhere in `app/` or `resources/views/` — PDF and
  e-mail templates included, which is where a wrong date is most likely to reach a client;
- a native `type="date"` comes back;
- any component tag carries a Blade directive.

`tests/Feature/DateInputTest.php` pins the field itself: the placeholder per country, the
entangled property, `.live` surviving, a stable id, that the box on screen is never the native
control, and that a conditionally disabled field still renders.

## See also

- [Currency Formatting](./currency-formatting.md) — the same question for money.
- `CLAUDE.md` → *Dates and Times Come From the Macros* — the rule in short.
