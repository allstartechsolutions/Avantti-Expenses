<x-project-layout :project="$project" active="documents" title="{{ __('Documents') }}">
    <div class="space-y-6">
        @if(session('error'))
            <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                {{ session('error') }}
            </div>
        @endif

        @include('livewire.documents.partials.toolbar', ['showLocation' => true, 'jobSites' => $jobSites])
        @include('livewire.documents.partials.summary')
        @include('livewire.documents.partials.browser')
    </div>

    @include('livewire.documents.partials.upload-modal', ['jobSites' => $jobSites])
    @include('livewire.documents.partials.detail-modal')
    @include('livewire.documents.partials.share-modal')
    @include('livewire.documents.partials.folder-modal')
    @include('livewire.documents.partials.edit-modal', ['jobSites' => $jobSites])
</x-project-layout>
