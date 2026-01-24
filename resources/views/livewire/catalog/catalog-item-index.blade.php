<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Catalog</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage products, services, and rental equipment</p>
            </div>
            <x-ui.button
                variant="primary"
                href="{{ route('catalog.create') }}"
                icon="plus">
                Add Item
            </x-ui.button>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search -->
        <div class="md:col-span-2">
            <div class="relative">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, SKU, or description..."
                    class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500"
                >
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>

        <!-- Type Filter -->
        <div>
            <select
                wire:model.live="typeFilter"
                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                <option value="">All Types</option>
                <option value="product">Products</option>
                <option value="service">Services</option>
                <option value="rental">Rentals</option>
            </select>
        </div>

        <!-- Category Filter -->
        <div>
            <select
                wire:model.live="categoryFilter"
                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Items Table -->
    @if($items->count() > 0)
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Item
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Category
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Cost
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Units
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($items as $item)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $item->name }}
                                    </div>
                                    @if($item->sku)
                                        <div class="text-sm text-slate-500 dark:text-slate-400">
                                            SKU: {{ $item->sku }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $typeColors = [
                                            'product' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
                                            'service' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300',
                                            'rental' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-300',
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $typeColors[$item->type] }}">
                                        {{ ucfirst($item->type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $item->category->name ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ Number::currency($item->current_cost, config('app.currency'), config('app.locale')) }}
                                    </div>
                                    @if($item->type === 'product' && $item->units_per_purchase > 0)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ Number::currency($item->unit_cost, config('app.currency'), config('app.locale')) }}/{{ $item->usage_unit }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($item->type === 'product')
                                        <div class="text-sm text-slate-900 dark:text-white">
                                            {{ $item->purchase_unit }}
                                        </div>
                                        @if($item->units_per_purchase)
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ number_format($item->units_per_purchase, 0) }} {{ $item->usage_unit }}
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-sm text-slate-500 dark:text-slate-400">
                                            {{ ucfirst($item->billing_type) ?? '-' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($item->is_active)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300">
                                            Active
                                        </span>
                                    @else
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300">
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <x-ui.button
                                        variant="secondary"
                                        href="{{ route('catalog.edit', $item->id) }}"
                                        icon="edit"
                                        class="inline-flex">
                                        Edit
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $items->links() }}
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No catalog items</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by adding products, services, or rental equipment.</p>
                <div class="mt-6">
                    <x-ui.button
                        variant="primary"
                        href="{{ route('catalog.create') }}"
                        icon="plus">
                        Add Item
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
