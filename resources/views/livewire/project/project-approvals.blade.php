<x-project-layout :project="$project" active="approvals" title="{{ __('Approvals') }}">
    <div class="space-y-6">
        @include('livewire.approval.partials.summary-cards', ['summary' => $summary])

        @include('livewire.approval.partials.filters', [
            'scope' => $project,
            'jobSites' => $jobSites,
            'typeOptions' => $typeOptions,
            'reviewerOptions' => $reviewerOptions,
            'createUrl' => route('projects.approvals.create', $project),
            'seedUrl' => route('projects.approvals.seed', $project),
        ])

        @include('livewire.approval.partials.list', [
            'scope' => $project,
            'approvals' => $approvals,
            'showLocationColumn' => true,
            'createUrl' => route('projects.approvals.create', $project),
        ])
    </div>
</x-project-layout>
