# Changelog — the project / job-site tab bar is grouped (2026-08-27)

Seventeen tabs on a project and fifteen on a job site, in one row that scrolled sideways on a
laptop and could not be read at all on a phone. The bar is now **three flat tabs and four
dropdowns**. Nothing about who may see what changed; no table, route or ability moved.

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

---

## 1. What changed

| File | What |
|---|---|
| `config/permissions.php` | New `tab_groups` block (four groups: name, icon, order). Every entry in `tabs` gained a `group`, and the orders were renumbered so the flat list reads group by group |
| `app/Services/Navigation.php` | `projectTabBar()` / `jobSiteTabBar()` fold the filtered tab list into items and groups; `tabLabel()` for one label on its own; `label()` reads the new lang file |
| `resources/views/components/ui/tab-bar.blade.php` | **New.** One component renders both bars — Alpine dropdowns, escape and click-outside to close, focus ring, dark mode |
| `resources/views/components/project-nav.blade.php`, `jobsite-nav.blade.php` | Now four lines each: fetch the bar, hand it to `x-ui.tab-bar` |
| `lang/en/navigation.php`, `lang/pt_BR/navigation.php` | **New.** Every tab and group label |
| `resources/views/components/project-layout.blade.php`, `jobsite-layout.blade.php` | Breadcrumb calls `tabLabel()` |

The two per-level orders now agree. They had differed only about where Change Orders and
Contracts sat, which the grouping settled; the `project_order` / `job_site_order` pair stays
in config so a level can differ again.

## 2. Permissions are untouched

Worth stating plainly, because a dropdown looks like somewhere a hidden thing could hide:
**the grouping is presentation only.** `projectTabs()` / `jobSiteTabs()` still do the
filtering — route exists, module on, ability held *on this project or site* — and `tabBar()`
only arranges what survived. A tab somebody may not open never reaches a group.

A confined site supervisor's job-site bar next to an admin's, from the real resolver:

```
admin                                       site supervisor
  Overview                                    Overview
  [Financial] Budget, Expenses,               Expenses          ← group flattened
              Income, Report
  [Procurement] Requisitions, Quotations,     Requisitions      ← group flattened
                POs, Contracts, COs
  [Collaboration] Documents, RFIs, Approvals  [Collaboration] Documents, RFIs, Approvals
  [Field] Daily Reports, Tasks                [Field] Daily Reports, Tasks
  Team                                        Team
```

Two behaviours that fall out of it:

- **A group left holding one visible tab is flattened back into a plain tab** — a dropdown
  that opens onto a single line is a worse click than the tab itself. A customer without the
  collaboration module gets *Documents* as a tab.
- **A group left holding none disappears**, rather than becoming a heading that opens onto
  nothing — the same rule the sidebar has always had.

And the tab bar is still only a convenience: that supervisor requesting `jobsites.income`
directly gets **403** from the route guard, exactly as before.

## 3. Menu wording moved to its own lang file

`lang/en/navigation.php` and `lang/pt_BR/navigation.php`, keyed by the tab or group key —
not the global JSON. Menu wording is revised more often than anything else, and revising it
inside a five-thousand-line file is how a tab ends up half-renamed. Same reasoning as
`lang/*/collaboration.php`.

A missing key falls back to the English `name` in `config/permissions.php`, so a new tab is
never rendered as a raw `navigation.tabs.…` string — but it is also not flagged, so **a tab
living on that fallback is untranslated work that looks finished.**

Two Portuguese decisions, both deliberate:

- **No group is called *Solicitações*.** The word already belongs to *Solicitações de Compra*
  (Requisitions); a group of that name holding *Solicitações de Informação* would confuse the
  two. The RFI/approvals group is **Colaboração**.
- ***Obra*** is the work itself. It does not collide with *Locais* (Job Sites), which stays a
  flat tab.

## 4. Two things fixed on the way

- **The breadcrumb was never translated.** It printed
  `ucwords(str_replace('-', ' ', $active))`, so a Brazilian reading *Ordens de Compra* in the
  bar got *Purchase Orders* in the breadcrumb directly above it. It calls `tabLabel()` now.
- **Four tabs shared one generic document glyph** — unreadable once they sit in a dropdown
  where the icon is the only thing separating two similar names. Expenses, Purchase Orders,
  Change Orders and Contracts got their own.

## 5. Tests

`NavigationTest` is at 16 cases. Five are new: the shape of the bar at both levels, the open
tab lighting up its group, a group left with one visible tab flattening back into a tab, a
group nobody can see not being rendered, and the labels resolving from the lang file in both
locales.

Two pinned orders were updated deliberately — `NavigationTest`'s flat tab lists and
`TeamTabTest`'s permission-editor matrix, which builds its rows from the same `tabs` block.
The set of areas offered in the editor is unchanged; only the order is.

**Suite: 964 passing.** (`RegistrationTest` ×2 and `ExampleTest` fail identically before this
change — they are not related to it.)

## 6. Docs brought level

`docs/sidebar-navigation.md` was rewritten: it had described hand-written markup in
`sidebar.blade.php` and told the reader to add menu items by copying `<a>` tags into it —
markup the permissions module's E3 pass deleted. It now covers all three menus, and the
old instructions would have silently done nothing.

Also updated: `docs/permissions-module.md` (E3), `docs/permissions-for-new-modules.md` (a new
tab needs a `group` and both lang lines), `docs/translation-system.md` (the per-module PHP
lang files, which it had never mentioned), `docs/project-jobsite-parity-rule.md`,
`docs/jobsite-tabs-to-pages.md` (marked historical) and `docs/README.md`.

## 7. Left open

**Job Sites and Team stay flat, deliberately.** If Team should sit inside *Obra*, it is a
one-line change in `config/permissions.php`.
