<x-layouts.auth :title="__('common.sign_in')">
    <div class="flex flex-col gap-6">
        <div class="flex w-full flex-col text-center">
            <flux:heading size="xl" level="1">{{ __('common.sign_in') }}</flux:heading>
            <flux:subheading>{{ __('common.please_enter_your_credentials_to_sign_in') }}</flux:subheading>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            @php
                $fieldClasses = 'rounded-lg! border! border-blue-100! bg-blue-50! px-4! py-2.5! h-auto! text-secondary-dark! placeholder:text-secondary-medium! focus:outline! focus:outline-2! focus:outline-secondary-medium! focus:outline-offset-2! focus:shadow-none! dark:border-field-line! dark:bg-surface-2! dark:text-ink! dark:placeholder:text-ink-faint! dark:focus:outline-ink-muted!';
            @endphp

            <div>
                <label for="login-username" class="mb-1.5 block text-sm font-semibold text-secondary-dark dark:text-ink">
                    {{ __('common.username') }} <span class="text-primary dark:text-brand-text">*</span>
                </label>
                <flux:input
                    id="login-username"
                    name="username"
                    :value="old('username')"
                    type="text"
                    required
                    autofocus
                    autocomplete="username"
                    :placeholder="__('common.username')"
                    input:class="{{ $fieldClasses }}"
                />
                <flux:error name="username" class="text-primary! dark:text-brand-text!" />
            </div>

            <div class="relative">
                <label for="login-password" class="mb-1.5 block text-sm font-semibold text-secondary-dark dark:text-ink">
                    {{ __('common.password') }} <span class="text-primary dark:text-brand-text">*</span>
                </label>
                <flux:input
                    id="login-password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('common.password')"
                    viewable
                    input:class="{{ $fieldClasses }}"
                />
                <flux:error name="password" class="text-primary! dark:text-brand-text!" />
            </div>

            <flux:button
                type="submit"
                variant="filled"
                class="min-h-11 w-full cursor-pointer! bg-primary! text-white! hover:bg-brand-hover!"
                data-test="login-button"
            >
                {{ __('common.sign_in') }}
            </flux:button>
        </form>
    </div>
</x-layouts.auth>
