@props([
    'project',
    'active' => 'overview'
])

{{--
    The tabs come from config/permissions.php via App\Services\Navigation:
    a tab is here because the catalogue declares it, its module is switched on,
    and this person holds its ability on this project. There is no tab list in
    this file — see docs/permissions-module.md. The grouping of the bar and the
    wording of the labels are config too: `tab_groups` and lang/*/navigation.php.
--}}

<x-ui.tab-bar
    :entries="app(\App\Services\Navigation::class)->projectTabBar(auth()->user(), $project, $active)"
    :scope="$project" />
