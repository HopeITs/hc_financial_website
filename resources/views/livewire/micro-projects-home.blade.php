@php
    $stepIcons = ['draft' => 'pencil-square', 'submitted' => 'clock', 'approved' => 'shopping-cart', 'ready' => 'document-check', 'active' => 'banknotes'];
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="micro-home">
    <x-ui.page-header :title="__('navigation.screens.microhome')" :subtitle="__('micro_home.intro')">
        <x-slot:actions>
            @if ($can['receive'])
                <x-ui.button icon="arrow-down-tray" :href="$routes['loans']" wire:navigate data-test="go-receive">{{ __('micro_home.receive') }}</x-ui.button>
            @endif
            @if ($can['newFile'])
                <x-ui.button tone="primary" icon="plus" :href="$routes['files'].'?file=new'" wire:navigate data-test="go-new-file">{{ __('micro.new_file') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- The way of a file, step by step, with how many files are at each step --}}
    <x-ui.panel :title="__('micro_home.steps_title')" :subtitle="__('micro_home.steps_hint')">
        <ol class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" data-test="steps">
            @foreach (\App\Services\Loans\MicroOverview::STEPS as $index => $step)
                <li class="flex flex-col gap-2 rounded-lg border border-secondary-soft p-3 dark:border-line" data-test="step-{{ $step }}">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">{{ $index + 1 }}</span>
                        <flux:icon :name="$stepIcons[$step]" variant="mini" class="size-4 text-secondary-medium dark:text-ink-muted" />
                        <span class="font-semibold">{{ __('micro_home.steps.'.$step.'.title') }}</span>
                    </div>
                    <p class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('micro_home.steps.'.$step.'.hint') }}</p>
                    <div class="mt-auto flex items-end justify-between">
                        <span class="text-2xl font-bold tabular-nums" data-test="step-count-{{ $step }}">{{ $steps[$step] }}</span>
                        @if ($can['files'] && $step !== 'active')
                            <a href="{{ $routes['files'] }}?show={{ $step }}" wire:navigate class="text-sm underline">{{ __('micro_home.show') }}</a>
                        @elseif ($can['loans'] && $step === 'active')
                            <a href="{{ $routes['loans'] }}" wire:navigate class="text-sm underline">{{ __('micro_home.show') }}</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </x-ui.panel>

    {{-- What waits for the person --}}
    @php($waitingTotal = $waiting['approve']->count() + $waiting['returned']->count() + $waiting['ready']->count())
    <x-ui.panel :title="__('micro_home.waiting_title')" :subtitle="$waitingTotal > 0 ? null : __('micro_home.waiting_none')" :count="$waitingTotal" data-test="waiting">
        @if ($waitingTotal > 0)
            <div class="grid gap-4 p-4 lg:grid-cols-3">
                @foreach (['approve' => 'clipboard-document-check', 'returned' => 'arrow-uturn-left', 'ready' => 'document-check'] as $kind => $icon)
                    @if ($waiting[$kind]->isNotEmpty())
                        <div data-test="waiting-{{ $kind }}">
                            <p class="mb-2 flex items-center gap-2 font-semibold"><flux:icon :name="$icon" variant="mini" class="size-4" />{{ __('micro_home.waiting.'.$kind) }}</p>
                            <ul class="flex flex-col gap-1 text-sm">
                                @foreach ($waiting[$kind] as $file)
                                    <li>
                                        <a href="{{ $routes['files'] }}?file={{ $file->id }}" wire:navigate class="underline">{{ $file->beneficiary }}</a>
                                        <span class="text-secondary-medium dark:text-ink-muted">· {{ $file->label() }} · <x-money :amount="$file->requested_amount" :currency="$file->currency" /></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-ui.panel>

    {{-- The loans and the funds --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-tile :label="__('micro_home.open_loans')" :value="(string) $loans" icon="users" data-test="open-loans" />
        <div class="flex flex-col gap-3">
            @forelse ($owed as $currency => $amount)
                <x-ui.stat-tile :label="__('micro_home.owed')" :value="$amount" :unit="$currency" icon="banknotes" />
            @empty
                <x-ui.stat-tile :label="__('micro_home.owed')" value="0.00" icon="banknotes" />
            @endforelse
        </div>
        <div class="flex flex-col gap-3">
            @forelse ($late as $currency => $amount)
                <x-ui.stat-tile :label="__('micro_home.late')" :value="$amount" :unit="$currency" tone="critical" icon="exclamation-triangle" data-test="late-{{ $currency }}" />
            @empty
                <x-ui.stat-tile :label="__('micro_home.late')" value="0.00" tone="good" icon="check-circle" />
            @endforelse
        </div>
        <x-ui.stat-tile :label="__('micro_home.funds_value')" :value="$fundsValue" unit="USD" icon="building-library" />
    </div>

    @if ($lateLoans !== [])
        <x-ui.panel :title="__('micro_home.late_title')" :count="count($lateLoans)">
            <x-ui.scroll-table :paginate="false">
                <table class="fin-table" data-test="late-loans">
                    <thead>
                        <tr>
                            <th>{{ __('loans.beneficiary') }}</th>
                            <th>{{ __('micro.funding') }}</th>
                            <th class="fin-num">{{ __('loans.days_late') }}</th>
                            <th class="fin-num">{{ __('loans.arrears') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lateLoans as $row)
                            <tr wire:key="late-{{ $row['loan']->id }}">
                                <td class="font-medium">{{ $row['loan']->beneficiary }}</td>
                                <td>{{ $row['loan']->label() }}</td>
                                <td class="fin-num" dir="ltr">{{ $row['days'] }}</td>
                                <td class="fin-num"><x-money :amount="$row['amount']" :currency="$row['loan']->currency" /></td>
                                <td>
                                    @if ($can['loans'])
                                        <a href="{{ $routes['loans'] }}?card={{ $row['loan']->id }}" wire:navigate class="text-sm underline">{{ __('loans.open_card') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.scroll-table>
        </x-ui.panel>
    @endif

    {{-- Every screen of the section --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-test="section-screens">
        @foreach (['files' => ['microfiles', 'folder-open'], 'loans' => ['payments', 'banknotes'], 'funds' => ['loanfunds', 'building-library'], 'report' => ['loans', 'chart-bar']] as $key => [$slug, $icon])
            @if ($can[$key])
                <a href="{{ $routes[$key] }}" wire:navigate class="flex flex-col gap-1 rounded-xl border border-secondary-soft bg-white p-4 shadow-card hover:border-primary dark:border-line dark:bg-surface-1" data-test="screen-{{ $slug }}">
                    <span class="flex items-center gap-2 font-semibold"><flux:icon :name="$icon" variant="mini" class="size-4 text-primary" />{{ __('navigation.screens.'.$slug) }}</span>
                    <span class="text-xs text-secondary-medium dark:text-ink-muted">{{ __('navigation.descriptions.'.$slug) }}</span>
                </a>
            @endif
        @endforeach
    </div>
</div>
