@if (session()->has('message'))
    <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
        {{ session('message') }}
    </div>
@endif
@if (session()->has('error'))
    <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
        {{ session('error') }}
    </div>
@endif
