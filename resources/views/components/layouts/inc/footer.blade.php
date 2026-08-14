<footer class="transition-all duration-300 py-6 text-center text-sm text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
        :class="sidebarCollapsed ? 'lg:ml-[70px]' : 'lg:ml-[260px]'">
    <p>&copy; {{now()->year}} {{config('app.name')}}. {{ __('Design & Developed by') }} <a href="https://www.allstartechsolutions.com" target="_blank" class="text-[#3F5189] hover:underline">{{ __('AllStar Tech Solutions') }}</a></p>
</footer>
