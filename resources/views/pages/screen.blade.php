<x-layouts.app :title="__('navigation.screens.'.$screen)">
    <div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
        <div>
            <flux:heading size="xl" level="1">{{ __('navigation.screens.'.$screen) }}</flux:heading>
        </div>

        <div class="rounded-xl border border-dashed border-secondary-soft bg-white p-10 text-center dark:border-line dark:bg-surface-1">
            <flux:heading>{{ __('common.screen_not_built_title') }}</flux:heading>
            <flux:text class="mt-1">{{ __('common.screen_not_built_text') }}</flux:text>
        </div>
    </div>
</x-layouts.app>
