# Table Action Buttons — Icon-Only Pattern

**Date:** 2026-08-14

## Rule

Action buttons inside table rows (index pages, or any listing table) must be **icon-only** using the `x-ui.icon-button` component. Text+icon buttons (`x-ui.button`) made the action columns too wide.

This applies to table rows only. Keep using `x-ui.button` (icon + text) for:
- Page-header buttons (e.g. "Add Client")
- Empty-state buttons
- Filter/clear buttons
- Modal confirm/cancel buttons
- Form submit/back buttons

## How to Build the Actions Column

### 1. View + Edit — use the shared component

`x-ui.view-edit-buttons` already renders icon-only buttons with tooltips:

```blade
<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <div class="flex items-center justify-end space-x-2">
        <x-ui.view-edit-buttons
            :viewRoute="route('items.show', $item->id)"
            :editRoute="$item->canBeEdited() ? route('items.edit', $item->id) : null" />
        <!-- extra actions here -->
    </div>
</td>
```

Pass `null` to hide a button. `:viewAction` / `:editAction` are available for `wire:click` instead of routes.

### 2. Other actions — use `x-ui.icon-button` directly

Always `size="sm"`, always a `title` attribute (tooltip replaces the removed text label):

```blade
<x-ui.icon-button
    variant="danger"
    size="sm"
    wire:click="deleteItem({{ $item->id }})"
    wire:confirm="Are you sure you want to delete this item?"
    icon="trash"
    title="Delete" />
```

### 3. Disabled buttons — put the tooltip on a wrapper span

`title` on a disabled button doesn't show reliably, so wrap it:

```blade
@if($item->linked_count > 0)
    <span title="Cannot delete: linked to {{ $item->linked_count }} record(s)">
        <x-ui.icon-button variant="danger" size="sm" icon="trash" disabled />
    </span>
@else
    <x-ui.icon-button variant="danger" size="sm" icon="trash" title="Delete"
        wire:click="confirmDeleteItem({{ $item->id }})" />
@endif
```

## Conventions

| Action | Variant | Icon | Title |
|--------|---------|------|-------|
| View | `secondary` | `eye` | View |
| Edit | `secondary` | `edit` | Edit |
| Delete | `danger` | `trash` | Delete |
| Copy/Duplicate | `outline` | `copy` | Copy |
| Set Default | `ghost` | `star` | Set Default |

## Where It's Applied (2026-08-14)

- Shared component: `resources/views/components/ui/view-edit-buttons.blade.php` (converted to icon-only — also affects project/job-site contract and purchase-order listings that reuse it)
- Index pages: user, supplier, client, project, invoice, estimate, payment-batch, catalog items, catalog categories, cost code templates
- Subcontractor index already used this pattern (it was the reference)
