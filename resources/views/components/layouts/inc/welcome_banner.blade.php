 <div x-show="welcomeSectionVisible"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="mb-8">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-xl p-8 text-white relative">
            <!-- Close Button -->
            <button @click="closeWelcomeSection()"
                    class="absolute top-4 right-4 p-1.5 rounded-lg hover:bg-white/10 transition-colors duration-200 group">
                <svg class="w-5 h-5 text-white/70 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold mb-2">{{ __('Good Morning, John! 👋') }}</h2>
                    <p class="text-white/80 text-lg">{{ __('Welcome to your :app dashboard. Here\'s what\'s happening today.', ['app' => config('app.name')]) }}</p>
                </div>
                <div class="hidden lg:block">
                    <svg class="w-24 h-24 text-white/30" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
                    </svg>
                </div>
            </div>
        </div>
</div>
