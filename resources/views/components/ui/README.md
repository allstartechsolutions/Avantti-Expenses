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