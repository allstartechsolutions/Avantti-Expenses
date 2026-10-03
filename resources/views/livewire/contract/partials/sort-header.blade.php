{{-- A sortable column header for the contract payment tables (/contract-payments and the payment batch screen). --}}
@php $active = $sortField === $field; @endphp
<th scope="col"
    class="px-4 py-3 text-{{ $align ?? 'left' }} text-xs font-medium uppercase tracking-wider"
    aria-sort="{{ $active ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <button type="button"
            wire:click="sort('{{ $field }}')"
            title="{{ __('Sort by :column', ['column' => $label]) }}"
            class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-slate-700 dark:hover:text-slate-200 {{ ($align ?? 'left') === 'right' ? 'flex-row-reverse' : '' }} {{ $active ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400' }}">
        <span>{{ $label }}</span>
        @if($active)
            <x-ui.icon :name="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="w-3.5 h-3.5" />
        @else
            <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 opacity-30" />
        @endif
    </button>
</th>
