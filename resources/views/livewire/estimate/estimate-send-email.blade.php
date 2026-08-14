<div>
    <x-ui.modal name="send-email-modal" maxWidth="2xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Email Estimate to Client') }}</h3>
        </div>

        <form wire:submit="sendEmail">
            <div class="p-6 space-y-4">
                <!-- To -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('To') }}</label>
                    <div class="px-3 py-2 bg-slate-100 dark:bg-slate-700 rounded-lg text-sm text-slate-600 dark:text-slate-300">
                        {{ $emailTo ?: 'No email address on file' }}
                    </div>
                </div>

                <!-- CC -->
                <div>
                    <label for="cc" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('CC') }} <span class="text-slate-400 font-normal">(optional, comma-separated)</span></label>
                    <input
                        type="text"
                        id="cc"
                        wire:model="cc"
                        placeholder="email@example.com, another@example.com"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm text-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                    >
                    @error('cc') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Subject -->
                <div>
                    <label for="subject" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Subject') }}</label>
                    <input
                        type="text"
                        id="subject"
                        wire:model="subject"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm text-sm focus:ring-[#3F5189] focus:border-[#3F5189] dark:bg-slate-700 dark:text-white"
                    >
                    @error('subject') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Body -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Message') }}</label>
                    <x-ui.tinymce-editor wireModel="body" id="email-body-editor" :height="250" modalName="send-email-modal" />
                    @error('body') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                @if($estimate->isDraft())
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-xs text-blue-700 dark:text-blue-300">
                            This estimate is currently a <strong>draft</strong>. Sending it will automatically change the status to <strong>{{ __('Sent') }}</strong>.
                        </p>
                    </div>
                @endif
            </div>

            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end space-x-3">
                <x-ui.button
                    variant="secondary"
                    type="button"
                    x-on:click="$dispatch('close-modal', 'send-email-modal')">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button
                    variant="primary"
                    type="submit"
                    icon="paper-airplane"
                    wire:loading.attr="disabled"
                    wire:target="sendEmail">
                    <span wire:loading.remove wire:target="sendEmail">{{ __('Send Email') }}</span>
                    <span wire:loading wire:target="sendEmail">{{ __('Sending...') }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
