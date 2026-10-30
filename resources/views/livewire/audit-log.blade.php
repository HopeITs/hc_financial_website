<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8">
    <x-ui.page-header :title="__('navigation.screens.auditlog')" :subtitle="__('auditlog.intro')">
        <x-slot:actions>
            <x-ui.button tone="primary" wire:click="export" data-test="export-csv">{{ __('ledger.op.export') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-5" data-test="audit-filters">
        <x-ui.form-select wire:model.live="person" :label="__('auditlog.person')" :placeholder="__('ledger.op.all')">
            @foreach ($people as $user)
                <option value="{{ $user->getAuthIdentifier() }}|{{ $user->username }}">{{ $user->username }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="action" :label="__('auditlog.action')" :placeholder="__('ledger.op.all')">
            @foreach ($actions as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-select wire:model.live="source" :label="__('auditlog.source')" :placeholder="__('ledger.op.all')">
            @foreach ($sources as $option)
                <option value="{{ $option }}">{{ __('auditlog.sources.'.$option) }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-input type="date" wire:model.live="from" :label="__('ledger.op.from')" />
        <x-ui.form-input type="date" wire:model.live="to" :label="__('ledger.op.to')" />
    </div>

    <x-ui.scroll-table :paginate="false">
        <table class="fin-table" data-test="audit-rows">
            <thead>
                <tr>
                    <th>{{ __('auditlog.date') }}</th>
                    <th>{{ __('auditlog.person') }}</th>
                    <th>{{ __('auditlog.action') }}</th>
                    <th>{{ __('auditlog.subject') }}</th>
                    <th>{{ __('auditlog.reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $shown = $present($row); @endphp
                    <tr wire:key="audit-{{ $loop->index }}-{{ $row->source }}-{{ $row->at }}-{{ $row->subject }}">
                        <td dir="ltr" class="text-start">{{ $shown['when'] }}</td>
                        <td>{{ $shown['person'] }}</td>
                        <td><flux:badge size="sm" color="zinc">{{ __('auditlog.sources.'.$row->source) }}</flux:badge> {{ $shown['action'] }}</td>
                        <td dir="ltr" class="text-start">
                            @if ($shown['url'])
                                <a href="{{ $shown['url'] }}" wire:navigate class="text-primary hover:underline dark:text-brand-text">{{ $row->source === 'request' ? '#' : '' }}{{ $row->subject }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="fin-empty" data-test="audit-empty">{{ __('auditlog.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.scroll-table>

    {{ $rows->links() }}
</div>
