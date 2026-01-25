<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Catalog Item</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Update item details and pricing</p>
            </div>
            <x-ui.button
                variant="secondary"
                href="{{ route('catalog.index') }}"
                icon="arrow-left">
                Back to Catalog
            </x-ui.button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <form wire:submit="save" class="p-6 space-y-6">
                    <!-- Item Type -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Item Type <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-3 gap-4">
                            <label class="relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-all {{ $type === 'product' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500' }}">
                                <input type="radio" wire:model.live="type" value="product" class="sr-only">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">📦</div>
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">Product</div>
                                </div>
                            </label>
                            <label class="relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-all {{ $type === 'service' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500' }}">
                                <input type="radio" wire:model.live="type" value="service" class="sr-only">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">🛠️</div>
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">Service</div>
                                </div>
                            </label>
                            <label class="relative flex items-center justify-center p-4 border-2 rounded-lg cursor-pointer transition-all {{ $type === 'rental' ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500' }}">
                                <input type="radio" wire:model.live="type" value="rental" class="sr-only">
                                <div class="text-center">
                                    <div class="text-2xl mb-2">🏗️</div>
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">Rental</div>
                                </div>
                            </label>
                        </div>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Basic Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="name"
                                    wire:model="name"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="Enter item name">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- SKU -->
                            <div>
                                <label for="sku" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    SKU
                                </label>
                                <input
                                    type="text"
                                    id="sku"
                                    wire:model="sku"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="Optional SKU/Code">
                                @error('sku')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Category -->
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Category
                                </label>
                                <select
                                    id="category_id"
                                    wire:model="category_id"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="">Select category (optional)</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Preferred Supplier (for products and rentals only) -->
                            @if(in_array($type, ['product', 'rental']))
                                <div
                                    x-data="{
                                        open: false,
                                        search: '',
                                        selectedName: '',
                                        suppliers: {{ $suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toJson() }},
                                        get filteredSuppliers() {
                                            if (!this.search) return this.suppliers;
                                            return this.suppliers.filter(s => s.name.toLowerCase().includes(this.search.toLowerCase()));
                                        },
                                        selectSupplier(supplier) {
                                            $wire.set('supplier_id', supplier.id);
                                            this.selectedName = supplier.name;
                                            this.search = '';
                                            this.open = false;
                                        },
                                        clearSelection() {
                                            $wire.set('supplier_id', '');
                                            this.selectedName = '';
                                            this.search = '';
                                        },
                                        init() {
                                            const currentId = $wire.get('supplier_id');
                                            if (currentId) {
                                                const found = this.suppliers.find(s => s.id == currentId);
                                                if (found) this.selectedName = found.name;
                                            }
                                        }
                                    }"
                                    @click.outside="open = false"
                                    class="relative"
                                >
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Preferred Supplier
                                    </label>

                                    <!-- Selected value display / Search input -->
                                    <div class="relative">
                                        <template x-if="selectedName && !open">
                                            <div class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white flex items-center justify-between cursor-pointer" @click="open = true">
                                                <span x-text="selectedName"></span>
                                                <button type="button" @click.stop="clearSelection()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>

                                        <template x-if="!selectedName || open">
                                            <input
                                                type="text"
                                                x-model="search"
                                                @focus="open = true"
                                                @keydown.escape="open = false"
                                                placeholder="Search supplier..."
                                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        </template>
                                    </div>

                                    <!-- Dropdown -->
                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-60 overflow-auto"
                                    >
                                        <template x-if="filteredSuppliers.length === 0">
                                            <div class="px-4 py-2 text-sm text-slate-500 dark:text-slate-400">No suppliers found</div>
                                        </template>

                                        <template x-for="supplier in filteredSuppliers" :key="supplier.id">
                                            <button
                                                type="button"
                                                @click="selectSupplier(supplier)"
                                                class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700 text-sm text-slate-900 dark:text-white"
                                                x-text="supplier.name"
                                            ></button>
                                        </template>
                                    </div>

                                    @error('supplier_id')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            <!-- Description -->
                            <div class="md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Description
                                </label>
                                <textarea
                                    id="description"
                                    wire:model="description"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="Enter item description"></textarea>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Product-Specific Fields -->
                    @if($type === 'product')
                        <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Product Units</h3>
                            <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                                <p class="text-sm text-blue-800 dark:text-blue-300">
                                    Define how this product is purchased vs. how it's used. For example: buy by "Box" but use by "Each", with 100 each per box.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Purchase Unit -->
                                <div>
                                    <label for="purchase_unit" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Purchase Unit <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        id="purchase_unit"
                                        wire:model="purchase_unit"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        <option value="">Select purchase unit</option>
                                        <optgroup label="Count">
                                            <option value="Each">Each</option>
                                            <option value="Piece">Piece</option>
                                            <option value="Unit">Unit</option>
                                            <option value="Dozen">Dozen</option>
                                            <option value="Hundred">Hundred</option>
                                            <option value="Thousand">Thousand</option>
                                        </optgroup>
                                        <optgroup label="Packaging">
                                            <option value="Box">Box</option>
                                            <option value="Case">Case</option>
                                            <option value="Pallet">Pallet</option>
                                            <option value="Bag">Bag</option>
                                            <option value="Bundle">Bundle</option>
                                            <option value="Roll">Roll</option>
                                            <option value="Sheet">Sheet</option>
                                            <option value="Pack">Pack</option>
                                            <option value="Carton">Carton</option>
                                        </optgroup>
                                        <optgroup label="Weight">
                                            <option value="Pound">Pound (lb)</option>
                                            <option value="Ounce">Ounce (oz)</option>
                                            <option value="Ton">Ton</option>
                                            <option value="Kilogram">Kilogram (kg)</option>
                                            <option value="Gram">Gram (g)</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="Gallon">Gallon</option>
                                            <option value="Quart">Quart</option>
                                            <option value="Liter">Liter</option>
                                            <option value="Cubic Yard">Cubic Yard</option>
                                            <option value="Cubic Foot">Cubic Foot</option>
                                        </optgroup>
                                        <optgroup label="Length">
                                            <option value="Foot">Foot (ft)</option>
                                            <option value="Inch">Inch (in)</option>
                                            <option value="Yard">Yard (yd)</option>
                                            <option value="Meter">Meter (m)</option>
                                            <option value="Mile">Mile</option>
                                        </optgroup>
                                        <optgroup label="Area">
                                            <option value="Square Foot">Square Foot (sq ft)</option>
                                            <option value="Square Yard">Square Yard (sq yd)</option>
                                            <option value="Square Meter">Square Meter (sq m)</option>
                                            <option value="Acre">Acre</option>
                                        </optgroup>
                                    </select>
                                    @error('purchase_unit')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Units Per Purchase -->
                                <div>
                                    <label for="units_per_purchase" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Quantity Per Purchase <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        id="units_per_purchase"
                                        wire:model="units_per_purchase"
                                        step="0.01"
                                        min="0.01"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="e.g., 100">
                                    @error('units_per_purchase')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Usage Unit -->
                                <div>
                                    <label for="usage_unit" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Usage Unit <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        id="usage_unit"
                                        wire:model="usage_unit"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        <option value="">Select usage unit</option>
                                        <optgroup label="Count">
                                            <option value="Each">Each</option>
                                            <option value="Piece">Piece</option>
                                            <option value="Unit">Unit</option>
                                            <option value="Dozen">Dozen</option>
                                            <option value="Hundred">Hundred</option>
                                            <option value="Thousand">Thousand</option>
                                        </optgroup>
                                        <optgroup label="Packaging">
                                            <option value="Box">Box</option>
                                            <option value="Case">Case</option>
                                            <option value="Pallet">Pallet</option>
                                            <option value="Bag">Bag</option>
                                            <option value="Bundle">Bundle</option>
                                            <option value="Roll">Roll</option>
                                            <option value="Sheet">Sheet</option>
                                            <option value="Pack">Pack</option>
                                            <option value="Carton">Carton</option>
                                        </optgroup>
                                        <optgroup label="Weight">
                                            <option value="Pound">Pound (lb)</option>
                                            <option value="Ounce">Ounce (oz)</option>
                                            <option value="Ton">Ton</option>
                                            <option value="Kilogram">Kilogram (kg)</option>
                                            <option value="Gram">Gram (g)</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="Gallon">Gallon</option>
                                            <option value="Quart">Quart</option>
                                            <option value="Liter">Liter</option>
                                            <option value="Cubic Yard">Cubic Yard</option>
                                            <option value="Cubic Foot">Cubic Foot</option>
                                        </optgroup>
                                        <optgroup label="Length">
                                            <option value="Foot">Foot (ft)</option>
                                            <option value="Inch">Inch (in)</option>
                                            <option value="Yard">Yard (yd)</option>
                                            <option value="Meter">Meter (m)</option>
                                            <option value="Mile">Mile</option>
                                        </optgroup>
                                        <optgroup label="Area">
                                            <option value="Square Foot">Square Foot (sq ft)</option>
                                            <option value="Square Yard">Square Yard (sq yd)</option>
                                            <option value="Square Meter">Square Meter (sq m)</option>
                                            <option value="Acre">Acre</option>
                                        </optgroup>
                                    </select>
                                    @error('usage_unit')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Service/Rental-Specific Fields -->
                    @if(in_array($type, ['service', 'rental']))
                        <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Billing Information</h3>

                            <div>
                                <label for="billing_type" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Billing Type <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="billing_type"
                                    wire:model="billing_type"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="">Select billing type</option>
                                    <option value="hourly">Hourly</option>
                                    <option value="fixed">Fixed Price</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                                @error('billing_type')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endif

                    <!-- Pricing -->
                    <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Pricing</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Current Cost -->
                            <div>
                                <label for="current_cost" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Cost <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-slate-500 dark:text-slate-400">$</span>
                                    <input
                                        type="number"
                                        id="current_cost"
                                        wire:model="current_cost"
                                        step="0.01"
                                        min="0"
                                        class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="0.00">
                                </div>
                                @error('current_cost')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                @if($type === 'product' && $units_per_purchase > 0 && $current_cost > 0)
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Unit cost: {{ Number::currency(round($current_cost / $units_per_purchase, 2), config('app.currency'), config('app.locale')) }}/{{ $usage_unit ?: 'unit' }}
                                    </p>
                                @endif
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Status
                                </label>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#3F5189]/20 dark:peer-focus:ring-[#3F5189]/40 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-[#3F5189]"></div>
                                    <span class="ms-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ $is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            href="{{ route('catalog.index') }}">
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            icon="check">
                            Save Changes
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Price History Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 sticky top-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Price History</h3>

                    @if($priceHistory->count() > 0)
                        <div class="space-y-4">
                            @foreach($priceHistory as $history)
                                <div class="relative pl-4 pb-4 border-l-2 {{ $loop->last ? 'border-transparent' : 'border-slate-200 dark:border-slate-700' }}">
                                    <!-- Timeline dot -->
                                    <div class="absolute left-0 top-0 -ml-[9px] h-4 w-4 rounded-full {{ $history->price_change >= 0 ? 'bg-red-500' : 'bg-green-500' }}"></div>

                                    <!-- Change details -->
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-semibold {{ $history->price_change >= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                                {{ $history->price_change >= 0 ? '+' : '' }}{{ Number::currency($history->price_change, config('app.currency'), config('app.locale')) }}
                                            </span>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ $history->price_change_percentage >= 0 ? '+' : '' }}{{ number_format($history->price_change_percentage, 1) }}%
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-600 dark:text-slate-400">
                                            {{ Number::currency($history->old_cost, config('app.currency'), config('app.locale')) }} → {{ Number::currency($history->new_cost, config('app.currency'), config('app.locale')) }}
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $history->changed_at->format('M d, Y g:i A') }}
                                        </div>
                                        @if($history->changedBy)
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                by {{ $history->changedBy->name }}
                                            </div>
                                        @endif
                                        @if($history->notes)
                                            <div class="text-xs text-slate-600 dark:text-slate-400 mt-1 italic">
                                                "{{ $history->notes }}"
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="text-slate-400 text-sm">
                                <svg class="mx-auto h-10 w-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p>No price changes yet</p>
                                <p class="text-xs mt-1">Price history will appear here when the cost is updated</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
