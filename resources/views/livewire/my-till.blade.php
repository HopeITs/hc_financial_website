<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="my-till">
    <x-ui.page-header :title="__('navigation.screens.mytill')" :subtitle="__('my_till.intro')" />

    @if ($actions !== [])
        <div class="flex flex-wrap gap-2" data-test="till-actions">
            @foreach ($actions as $action)
                <x-ui.button :icon="$action['icon']" :href="$action['url']" wire:navigate data-test="till-action-{{ $action['slug'] }}">{{ __('my_till.actions.'.$action['slug']) }}</x-ui.button>
            @endforeach
        </div>
    @endif

    <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.form-select wire:model.live="city" :label="__('treasury.city')" data-test="till-city">
            @foreach ($cities as $option)
                <option value="{{ $option }}">{{ $option === \App\Services\Treasury\CashCounts::NO_CITY ? __('treasury.no_city') : $option }}</option>
            @endforeach
        </x-ui.form-select>
    </x-ui.panel>

    <x-ui.panel :title="__('my_till.accounts')" :subtitle="__('my_till.accounts_hint')" data-test="till-accounts">
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>{{ __('treasury.account') }}</th>
                        <th class="fin-num">{{ __('treasury.book') }}</th>
                        <th class="fin-num">{{ __('my_till.pending') }}</th>
                        <th>{{ __('my_till.counted_today') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $row)
                        <tr wire:key="account-{{ $row['code'] }}" data-test="till-account-{{ $row['code'] }}">
                            <td><span dir="ltr">{{ $row['code'] }}</span> · {{ $row['name'] }}</td>
                            <td class="fin-num"><x-money :amount="$row['book']" :currency="$row['currency']" /></td>
                            <td class="fin-num"><x-money :amount="$row['pending']" :currency="$row['currency']" /></td>
                            <td>
                                @if ($row['counted'] !== null)
                                    <x-ui.status-badge status="done" :label="__('my_till.counted')" />
                                @else
                                    <x-ui.status-badge status="waiting" :label="__('my_till.not_counted')" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="fin-empty">{{ __('my_till.no_accounts') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>

    <x-ui.panel :title="__('my_till.today')" :count="count($moves)" data-test="till-moves">
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>{{ __('vouchers.number') }}</th>
                        <th>{{ __('my_till.description') }}</th>
                        <th>{{ __('treasury.account') }}</th>
                        <th class="fin-num">{{ __('my_till.in') }}</th>
                        <th class="fin-num">{{ __('my_till.out') }}</th>
                        <th>{{ __('my_till.state') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($moves as $n => $move)
                        <tr wire:key="move-{{ $n }}">
                            <td dir="ltr">{{ $move['voucher'] }}</td>
                            <td>{{ $move['description'] }}</td>
                            <td dir="ltr">{{ $move['account'] }}</td>
                            <td class="fin-num">@if ((float) $move['in'] > 0)<x-money :amount="$move['in']" :currency="$move['currency']" />@endif</td>
                            <td class="fin-num">@if ((float) $move['out'] > 0)<x-money :amount="$move['out']" :currency="$move['currency']" />@endif</td>
                            <td><x-ui.status-badge :status="$move['status']" :label="__('vouchers.status.'.$move['status'])" final /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="fin-empty">{{ __('my_till.no_moves') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>

    <x-ui.panel :title="__('my_till.mine')" :subtitle="__('my_till.mine_hint', ['days' => \App\Livewire\MyTill::MY_DAYS])" :count="count($mine)" data-test="till-mine">
        <x-ui.scroll-table :paginate="false">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>{{ __('vouchers.number') }}</th>
                        <th>{{ __('my_till.date') }}</th>
                        <th>{{ __('my_till.description') }}</th>
                        <th>{{ __('my_till.state') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mine as $row)
                        <tr wire:key="mine-{{ $row['voucher']->id }}" data-test="mine-{{ $row['voucher']->id }}">
                            <td dir="ltr">{{ $row['voucher']->id }}</td>
                            <td>{{ $row['voucher']->date->format('Y-m-d') }}</td>
                            <td>
                                {{ $row['voucher']->description }}
                                @if ($row['reason'])
                                    <p class="text-xs text-status-critical" data-test="reason-{{ $row['voucher']->id }}">{{ __('my_till.rejected_because', ['reason' => $row['reason']]) }}</p>
                                @endif
                            </td>
                            <td><x-ui.status-badge :status="$row['badge']" :label="__('vouchers.status.'.$row['badge'])" final /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="fin-empty">{{ __('my_till.no_mine') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.scroll-table>
    </x-ui.panel>
</div>
