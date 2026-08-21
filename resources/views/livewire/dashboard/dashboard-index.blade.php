<div>
    {{--
        M18: this used to be `@if ($role === 'admin')`. The overview is now an
        ability, seeded to the same people who see it today, and everything on
        it obeys the ability of the module it summarises — see DashboardIndex.
    --}}
    @if ($blocks['overview'])
        @include('livewire.dashboard.partials.overview')
    @else
        @include('livewire.dashboard.partials.welcome')
    @endif
</div>
