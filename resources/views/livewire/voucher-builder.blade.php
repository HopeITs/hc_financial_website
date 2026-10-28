@php
    $cell = 'w-full min-w-20 rounded-md border border-secondary-light bg-white px-2 py-1.5 text-sm text-secondary-dark disabled:opacity-60 dark:border-field-line dark:bg-surface-2 dark:text-ink';
    $classLabels = collect(\App\Enums\AccountClass::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]);
@endphp

<div class="flex flex-col gap-6 px-4 pb-10 lg:px-8" data-test="voucher-builder">
    <div>
        <a href="{{ route('entries.vouchers') }}" wire:navigate class="text-sm text-secondary-medium hover:underline dark:text-ink-muted">{{ __('ledger.builder.back') }}</a>
    </div>

    <x-ui.page-header :title="__('ledger.builder.title')" :subtitle="__('ledger.builder.intro')" />

    @if ($failures !== [])
        <div class="rounded-lg border border-status-critical bg-status-critical-soft px-4 py-2 text-sm text-status-critical" role="alert" data-test="failures">
            @foreach ($failures as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <x-ui.panel class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" data-test="voucher-header">
        <x-ui.form-select wire:model.live="type" :label="__('ledger.builder.type')" required>
            @foreach ($types as $option)
                <option value="{{ $option->value }}">{{ $option->value }} · {{ $option->label() }}</option>
            @endforeach
        </x-ui.form-select>
        <x-ui.form-input type="date" wire:model.live="date" :label="__('ledger.builder.date')" required />
        @if ($type === 'CPV')
            <x-ui.form-input wire:model.live.debounce.400ms="requestId" :label="__('ledger.builder.request')" :help="__('ledger.builder.request_help')" dir="ltr" inputmode="numeric" />
        @endif
        <div class="sm:col-span-2 lg:col-span-4">
            <x-ui.form-textarea wire:model.live.debounce.400ms="description" :label="__('ledger.builder.description')" rows="2" />
        </div>
    </x-ui.panel>

    <section class="flex flex-col gap-3">
        <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.builder.lines') }}</h2>

        <x-ui.scroll-table :paginate="false">
            <table class="fin-table" data-test="builder-lines">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="min-w-56">{{ __('ledger.builder.account') }}</th>
                        <th>{{ __('ledger.builder.side') }}</th>
                        <th>{{ __('ledger.builder.currency') }}</th>
                        <th class="min-w-32">{{ __('ledger.builder.amount') }}</th>
                        <th class="min-w-28">{{ __('ledger.builder.rate') }}</th>
                        <th class="fin-num">{{ __('ledger.builder.usd') }}</th>
                        <th class="min-w-32">{{ __('ledger.builder.project') }}</th>
                        <th class="min-w-36">{{ __('ledger.builder.budget_line') }}</th>
                        <th class="min-w-32">{{ __('ledger.builder.fund_source') }}</th>
                        <th class="min-w-28">{{ __('ledger.builder.contact') }}</th>
                        <th class="min-w-24">{{ __('ledger.builder.center') }}</th>
                        <th>{{ __('ledger.builder.lira_type') }}</th>
                        <th><span class="sr-only">{{ __('ledger.builder.remove_line') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $i => $line)
                        @php
                            $effective = $built->rows[$i] ?? null;
                            $currency = $effective['currency'] ?? ($fixedCurrency[$line['account']] ?? $line['currency']);
                            $isFixed = isset($fixedCurrency[$effective['account'] ?? $line['account']]);
                            $isForeign = $currency !== 'USD';
                            $isPounds = $currency === 'SYP';
                        @endphp
                        <tr wire:key="line-{{ $i }}">
                            <td class="text-secondary-medium dark:text-ink-muted">{{ $i + 1 }}</td>
                            <td>
                                <x-ui.form-select bare wire:model.live="lines.{{ $i }}.account" :label="(__('ledger.builder.account')).' '.($i + 1)" data-test="account-{{ $i }}">
                                    <option value="">{{ __('ledger.builder.pick_account') }}</option>
                                    @foreach ($accountGroups as $class => $group)
                                        <optgroup label="{{ $classLabels[$class] ?? $class }}">
                                            @foreach ($group as $account)
                                                <option value="{{ $account->code }}">{{ $account->code }} · {{ $account->localizedName() }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </x-ui.form-select>
                            </td>
                            <td>
                                <x-ui.form-select bare wire:model.live="lines.{{ $i }}.side" :label="(__('ledger.builder.side')).' '.($i + 1)" data-test="side-{{ $i }}">
                                    <option value="debit">{{ __('ledger.statement.dr') }}</option>
                                    <option value="credit">{{ __('ledger.statement.cr') }}</option>
                                </x-ui.form-select>
                            </td>
                            <td>
                                @if ($isFixed)
                                    <span dir="ltr" data-test="currency-{{ $i }}">{{ $currency }}</span>
                                @else
                                    <x-ui.form-select bare wire:model.live="lines.{{ $i }}.currency" :label="(__('ledger.builder.currency')).' '.($i + 1)" data-test="currency-{{ $i }}">
                                        @foreach ($currencyCodes as $code)
                                            <option value="{{ $code }}">{{ $code }}</option>
                                        @endforeach
                                    </x-ui.form-select>
                                @endif
                            </td>
                            <td><input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.amount" class="{{ $cell }} text-end" aria-label="{{ __('ledger.builder.amount') }} {{ $i + 1 }}" data-test="amount-{{ $i }}"></td>
                            <td>
                                @if ($isForeign)
                                    <input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.rate" class="{{ $cell }} text-end" aria-label="{{ __('ledger.builder.rate') }} {{ $i + 1 }}" data-test="rate-{{ $i }}">
                                @else
                                    <span class="text-secondary-medium dark:text-ink-muted">—</span>
                                @endif
                            </td>
                            <td class="fin-num" data-test="usd-{{ $i }}">@if ($effective) <x-money :amount="$effective['usd']" /> @else — @endif</td>
                            <td>
                                <x-ui.form-select bare wire:model.live="lines.{{ $i }}.project" :label="(__('ledger.builder.project')).' '.($i + 1)" data-test="project-{{ $i }}">
                                    <option value="">{{ __('ledger.builder.no_project') }}</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->code }}">{{ $project->code }}</option>
                                    @endforeach
                                </x-ui.form-select>
                            </td>
                            <td>
                                <x-ui.form-select bare wire:model.live="lines.{{ $i }}.budget_line" :label="(__('ledger.builder.budget_line')).' '.($i + 1)" data-test="budget-line-{{ $i }}">
                                    <option value="">{{ __('ledger.builder.no_budget_line') }}</option>
                                    @foreach ($budgetLines as $budgetLine)
                                        @if ($line['project'] === '' || $line['project'] === $budgetLine->project_code)
                                            <option value="{{ $budgetLine->key }}">{{ $budgetLine->key }}</option>
                                        @endif
                                    @endforeach
                                </x-ui.form-select>
                            </td>
                            <td>
                                <x-ui.form-select bare wire:model.live="lines.{{ $i }}.fund_source" :label="(__('ledger.builder.fund_source')).' '.($i + 1)">
                                    <option value="">{{ __('ledger.builder.no_fund_source') }}</option>
                                    @foreach ($fundSources as $source)
                                        <option value="{{ $source->value }}">{{ $source->label() }}</option>
                                    @endforeach
                                </x-ui.form-select>
                            </td>
                            <td><input type="text" wire:model.live.debounce.400ms="lines.{{ $i }}.contact" class="{{ $cell }}" aria-label="{{ __('ledger.builder.contact') }} {{ $i + 1 }}"></td>
                            <td><input type="text" wire:model.live.debounce.400ms="lines.{{ $i }}.center" class="{{ $cell }}" aria-label="{{ __('ledger.builder.center') }} {{ $i + 1 }}"></td>
                            <td>
                                @if ($isPounds)
                                    <x-ui.form-select bare wire:model.live="lines.{{ $i }}.lira_type" :label="(__('ledger.builder.lira_type')).' '.($i + 1)">
                                        <option value="">—</option>
                                        <option value="old">{{ __('ledger.builder.lira_old') }}</option>
                                        <option value="new">{{ __('ledger.builder.lira_new') }}</option>
                                    </x-ui.form-select>
                                @else
                                    <span class="text-secondary-medium dark:text-ink-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" wire:click="removeLine({{ $i }})" class="rounded-md p-1.5 text-secondary-medium hover:bg-secondary-soft hover:text-status-critical dark:text-ink-muted" aria-label="{{ __('ledger.builder.remove_line') }} {{ $i + 1 }}" data-test="remove-line-{{ $i }}">
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </td>
                        </tr>
                        @if ($type === 'CRV' && $line['side'] === 'debit' && ($effective['account'] ?? $line['account']) === config('ledger.transfer_company_account'))
                            <tr wire:key="hawala-{{ $i }}" data-test="hawala-{{ $i }}">
                                <td></td>
                                <td colspan="12">
                                    <div class="grid max-w-xl items-end gap-3 sm:grid-cols-3">
                                        <label class="flex flex-col gap-1 text-xs text-secondary-medium dark:text-ink-muted">
                                            {{ __('ledger.builder.hawala_eur') }}
                                            <input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.eur_amount" class="{{ $cell }} text-end" data-test="eur-amount-{{ $i }}">
                                        </label>
                                        <label class="flex flex-col gap-1 text-xs text-secondary-medium dark:text-ink-muted">
                                            {{ __('ledger.builder.hawala_rate') }}
                                            <input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.eur_rate" placeholder="{{ $projectRates[$line['project']] ?? '' }}" class="{{ $cell }} text-end" data-test="eur-rate-{{ $i }}">
                                        </label>
                                        <label class="flex flex-col gap-1 text-xs text-secondary-medium dark:text-ink-muted">
                                            {{ __('ledger.builder.hawala_commission') }}
                                            <input type="text" inputmode="decimal" dir="ltr" wire:model.live.debounce.400ms="lines.{{ $i }}.commission" class="{{ $cell }} text-end" data-test="commission-{{ $i }}">
                                        </label>
                                    </div>
                                    <p class="mt-1 max-w-xl whitespace-normal text-xs text-secondary-medium dark:text-ink-muted">{{ __('ledger.builder.hawala_help') }}</p>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold" data-test="builder-totals">
                        <td colspan="6">{{ __('ledger.builder.total_debit') }} / {{ __('ledger.builder.total_credit') }} (USD)</td>
                        <td class="fin-num" colspan="1"><x-money :amount="$debit" /></td>
                        <td colspan="2" class="fin-num"><x-money :amount="$credit" /></td>
                        <td colspan="5">{{ __('ledger.builder.difference') }}: <x-money :amount="$difference" /></td>
                    </tr>
                </tfoot>
            </table>
        </x-ui.scroll-table>

        <div>
            <x-ui.button wire:click="addLine" icon="plus" data-test="add-line">{{ __('ledger.builder.add_line') }}</x-ui.button>
        </div>
        @if (bccomp($difference, '0', 2) !== 0 && bccomp(ltrim($difference, '-'), (string) config('ledger.rounding_tolerance_usd'), 2) <= 0)
            <p class="type-meta text-secondary-medium dark:text-ink-muted" data-test="rounding-note">{{ __('ledger.builder.rounding_note') }}</p>
        @endif
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.builder.attachments') }}</h2>
        <x-ui.panel class="flex flex-col gap-3 p-4" data-test="attachments">
            <input type="file" multiple wire:model="attachments" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" aria-label="{{ __('ledger.builder.attachments') }}" data-test="file-input">
            <p class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.builder.attachments_help') }}</p>
            @error('attachments')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
            @error('attachments.*')<p class="text-sm text-status-critical" role="alert">{{ $message }}</p>@enderror
            @foreach ($attachments as $n => $file)
                <p class="flex flex-wrap items-center gap-3 text-sm" wire:key="file-{{ $n }}">
                    <span dir="ltr">{{ $file->getClientOriginalName() }}</span>
                    <span class="text-secondary-medium dark:text-ink-muted" dir="ltr">{{ number_format($file->getSize() / 1024, 1) }} KB</span>
                    <button type="button" wire:click="removeAttachment({{ $n }})" class="text-status-critical hover:underline">{{ __('ledger.builder.remove_file') }}</button>
                </p>
            @endforeach
        </x-ui.panel>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="text-base font-semibold text-secondary-dark dark:text-ink">{{ __('ledger.builder.checks') }}</h2>
        <x-ui.panel class="flex flex-col gap-3 p-4" data-test="check-list">
            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($checks as $check)
                    <li class="flex items-center gap-2 text-sm" data-ok="{{ $check['ok'] ? '1' : '0' }}">
                        @if ($check['ok'])
                            <flux:icon.check-circle class="size-5 text-status-good" />
                        @else
                            <flux:icon.minus-circle class="size-5 text-secondary-light" />
                        @endif
                        <span>{{ $check['label'] }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($messages !== [])
                <ul class="list-disc ps-5 text-sm text-status-critical" data-test="check-messages">
                    @foreach ($messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @elseif ($ready)
                <p class="text-sm text-status-good" data-test="ready">{{ __('ledger.builder.all_good') }}</p>
            @endif

            @if ($warnings !== [])
                <div class="rounded-lg border border-status-warn bg-status-warn-soft px-4 py-2 text-sm text-status-warn" data-test="warnings">
                    <p class="font-semibold">{{ __('ledger.warnings.title') }}</p>
                    @foreach ($warnings as $warning)
                        <p>{{ $warning }}</p>
                    @endforeach
                </div>
            @endif
        </x-ui.panel>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button tone="primary" wire:click="save" :disabled="! $ready" wire:loading.attr="disabled" wire:target="save" data-test="save-voucher">{{ __('ledger.builder.save') }}</x-ui.button>
        <span class="type-meta text-secondary-medium dark:text-ink-muted">{{ __('ledger.builder.number_note') }}</span>
    </div>
</div>
