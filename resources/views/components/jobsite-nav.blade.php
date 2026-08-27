@props([
    'jobSite',
    'active' => 'overview'
])

{{--
    The tabs come from config/permissions.php via App\Services\Navigation:
    a tab is here because the catalogue declares it, its module is switched on,
    and this person holds its ability on this job site — a job-site membership
    overriding the project's where there is one. See docs/permissions-module.md.
    The grouping of the bar and the wording of the labels are config too:
    `tab_groups` and lang/*/navigation.php.
--}}

<x-ui.tab-bar
    :entries="app(\App\Services\Navigation::class)->jobSiteTabBar(auth()->user(), $jobSite, $active)"
    :scope="$jobSite" />
