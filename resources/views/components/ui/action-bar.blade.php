{{--
    The actions of a form, kept in reach at the bottom of the window while its page scrolls (finance decision of
    2026-11-25): every screen puts its save / send / approve buttons here, the main one last.
--}}
<div {{ $attributes->class('sticky bottom-0 z-10 -mx-4 flex flex-wrap items-end gap-3 border-t border-secondary-soft bg-white/95 px-4 py-3 backdrop-blur dark:border-line dark:bg-surface-1/95') }}>
    {{ $slot }}
</div>
