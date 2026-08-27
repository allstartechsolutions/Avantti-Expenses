# Navigation — the sidebar and the two tab bars

Every menu in the application is **generated from `config/permissions.php`** by
`App\Services\Navigation`. There is no menu list in any Blade file. An entry appears because
the catalogue declares it, its module is switched on, and this person holds its ability —
never because a template remembered to show it, and never because one forgot to hide it.

> **This document was rewritten on 27 Aug 2026.** It previously described hand-written markup
> in `sidebar.blade.php` and told you to add menu items by copying `<a>` tags into it. That
> markup was deleted by the permissions module's E3 pass (see `docs/permissions-module.md`),
> and the tab bar was grouped on 27 Aug 2026. The old instructions would now silently do
> nothing.

---

## The three menus

| Menu | Built by | Rendered by |
|---|---|---|
| Left sidebar | `Navigation::sidebar($user)` | `layouts/inc/sidebar.blade.php` → `x-layouts.inc.nav.group` / `.item` |
| Top-bar icons | `Navigation::header($user)` | `layouts/inc/header.blade.php` |
| Project / job-site tab bar | `Navigation::projectTabBar()` / `jobSiteTabBar()` | `x-project-nav` / `x-jobsite-nav` → `x-ui.tab-bar` |

All three return the same shape: an ordered list of `['type' => 'item', …]` and
`['type' => 'group', …, 'items' => [...]]`. **Groups and flat items share one ordering
space**, so a group's `order` is compared against an item's `order`; a test enforces that no
two claim the same number.

---

## Where the declarations live

| Section of `config/permissions.php` | Holds |
|---|---|
| `groups` | The collapsible **sidebar** groups: name, icon, order, and the route patterns that light one up |
| `menu` | The sidebar and top-bar entries: label, group, order, route, **ability**, active patterns, icon. `header: true` puts an entry in the top bar instead of the sidebar |
| `tab_groups` | The four dropdowns of the **tab bar**: name, icon, order |
| `tabs` | The 17 project / job-site tabs: ability, icon, `group`, and a route + order **per level**. `job_site_route` is null for a project-only tab (Job Sites) |

**Labels come from `lang/en/navigation.php` and `lang/pt_BR/navigation.php`**, keyed by the
tab or group key — not from the global JSON. Menu wording is revised more often than anything
else, and revising it inside a five-thousand-line file is how a tab ends up half-renamed. A
key that is missing there falls back to the English `name` in the config, so nothing is ever
rendered as a raw `navigation.tabs.…` string — but a tab living only on that fallback is
untranslated, which is unfinished work.

---

## The project / job-site tab bar

Seventeen tabs on a project and fifteen on a job site was one row that scrolled sideways on a
laptop and could not be read on a phone. Since 27 Aug 2026 the bar is **three flat tabs and
four dropdowns**:

```
Visão Geral | Locais | Financeiro ▾ | Suprimentos ▾ | Colaboração ▾ | Obra ▾ | Equipe
```

| Group | pt_BR | Tabs |
|---|---|---|
| — (flat) | | Overview · Job Sites *(project only)* · Team |
| `financial` | **Financeiro** | Budget, Expenses, Income, Report |
| `procurement` | **Suprimentos** | Requisitions, Quotations, Purchase Orders, Contracts, Change Orders |
| `collaboration` | **Colaboração** | Documents, RFIs, Approvals |
| `field` | **Obra** | Daily Reports, Tasks |

Three behaviours worth knowing before changing it:

1. **The grouping is presentation only.** `projectTabs()` / `jobSiteTabs()` filter first —
   route exists, module on, ability held on *this* project or site — and `tabBar()` only
   arranges what survived. A tab somebody may not open never reaches a group, so no dropdown
   can expose one. The tab bar is a convenience; the guard on the route or component is the
   boundary.
2. **A group left holding one visible tab is flattened back into a plain tab**, and a group
   left holding none disappears. A customer without the collaboration module sees *Documents*
   as a tab rather than a dropdown that opens onto a single line.
3. **The open tab lights up its group**, and from `md` up the button also shows the open
   tab's name (`Suprimentos / Cotações`), so the bar still says where you are with every
   dropdown closed. The breadcrumb above it uses `Navigation::tabLabel()`.

The bar **wraps** on a narrow screen rather than scrolling sideways: a dropdown panel inside
an `overflow-x-auto` strip is clipped by it.

### Two Portuguese decisions

- **No group is called *Solicitações*.** That word already belongs to *Solicitações de
  Compra* (Requisitions), and a group of that name holding *Solicitações de Informação*
  would confuse the two.
- ***Obra*** is the work itself — daily reports and tasks. It does not collide with
  *Locais* (Job Sites), which stays a flat tab of its own.

---

## Adding to a menu

### A sidebar entry

1. Declare the ability's area in `config/permissions.php` (see
   `docs/permissions-for-new-modules.md` — nothing exists until it is declared).
2. Add an entry to `menu`: `key`, `name`, `group` (or omit for a top-level item), `order`,
   `route`, `ability`, `active` patterns, `icon`.
3. Add the label to both `lang/*/navigation.php` files if the wording is not already there.

No Blade file changes. `AbilityCatalogTest` will fail if the entry names an ability that does
not exist, a route that does not exist, an undeclared group, or an order already taken.

### A project / job-site tab

1. Add an entry to `tabs`: `key`, `name`, `ability`, **`group`**, `project_route` +
   `project_order`, `job_site_route` + `job_site_order` (null for project-only), `icon`.
2. Add the label to **both** `navigation.php` files.
3. Guard the route or the component — the tab being hidden is not protection.

Per the parity rule (`docs/project-jobsite-parity-rule.md`), a tab should exist at both
levels unless there is a reason it cannot.

---

## How the sidebar submenus open

The sidebar keeps one open group at a time in Alpine state, initialised from the current
route so the group you are inside is already open on load:

```blade
<body x-data="{ sidebarOpen: false, sidebarCollapsed: false, activeSubmenu: @js(...), toggleSubmenu(menu) { … } }">
```

When the sidebar is collapsed to the icon rail, a group opens as a **flyout** instead
(`railFlyout` in `x-layouts.inc.nav.group`), anchored beside the rail and repositioned on
scroll via the `rail-reposition` event.

---

## Styling reference

| Element | Active class |
|---|---|
| Sidebar link (active) | `text-white bg-gradient-to-r from-[#3F5189] to-[#4A5A96]` |
| Sidebar link (inactive) | `text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700` |
| Sidebar group button (active) | `text-[#3F5189] dark:text-[#4A5A96] bg-slate-100 dark:bg-slate-700` |
| Sidebar group item (active) | `text-[#3F5189] dark:text-[#4A5A96] font-medium` |
| Tab / group button (active) | `border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]` |
| Tab / group button (inactive) | `border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400` |
| Dropdown item (active) | `bg-slate-100 font-medium text-[#3F5189] dark:bg-slate-700/60 dark:text-[#8FA0DC]` |

Icons: sidebar `w-5 h-5 mr-3`, sidebar submenu item `w-4 h-4 mr-2`, tab `h-5 w-5`, chevron
`w-4 h-4`.

---

## The menu as declared today

```
SIDEBAR
├── Dashboard
├── Company
│   ├── Company Info
│   ├── Users
│   └── Roles & Access
├── Projects
│   ├── All Projects
│   ├── Subcontractors
│   ├── Clients
│   ├── Cost Codes
│   ├── Payments
│   ├── Contract Payments
│   └── Payment Batches
├── Catalog
│   ├── All Items
│   ├── Categories
│   └── Suppliers
├── Estimates
├── Invoices
├── Meetings
│   ├── Minutes
│   ├── My Tasks
│   └── Meeting Series
├── Reports
│   ├── Sales Tax Report
│   ├── Accounts Payable
│   ├── Company Financials
│   ├── Expense Report
│   ├── Payment Schedule
│   └── Payment Details
└── Documentation

HEADER
├── Search Projects
├── Messages
├── Fullscreen toggle
└── Settings (declared in `menu` with `header: true`, and shown only to people who can open it)

USER DROPDOWN (sidebar bottom)
├── Profile
└── Logout
```

**Nobody sees all of that.** The list above is what is *declared*; what is *rendered* is
whatever survives the three conditions. `NavigationTest` pins the result for each role, group
by group, and is the file to change — deliberately — when a menu should differ.

---

## Related

- `docs/permissions-module.md` — how the menus came to be generated (E3), and the tests
- `docs/permissions-for-new-modules.md` — the checklist a new module follows
- `docs/translation-system.md` — why menu wording lives in its own lang file
