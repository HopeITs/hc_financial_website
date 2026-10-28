@props(['savedAt' => null, 'notice' => null])

{{-- Save as draft (U-04): the offer to restore an earlier draft, or what the last draft action did. --}}
@if ($savedAt !== null)
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="draft-offer">
        <span>{{ __('drafts.offer', ['time' => \Illuminate\Support\Carbon::parse($savedAt)->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}</span>
        <span class="flex items-center gap-2">
            <x-ui.button size="sm" wire:click="restoreDraft" data-test="draft-restore">{{ __('drafts.restore') }}</x-ui.button>
            <x-ui.button size="sm" tone="ghost" wire:click="discardDraft" data-test="draft-discard">{{ __('drafts.discard') }}</x-ui.button>
        </span>
    </div>
@elseif ($notice !== null)
    <div class="rounded-lg border border-secondary-soft bg-white px-4 py-2 text-sm dark:border-line dark:bg-surface-1" role="status" data-test="draft-notice">{{ $notice }}</div>
@endif
