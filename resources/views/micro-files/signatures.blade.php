{{-- The places to sign at the foot of a micro-project form: one per role, an empty role leaves a gap. --}}
<div class="mt-14 grid grid-cols-3 gap-8">
    @foreach ($roles as $role)
        <div @class(['pt-1 text-center text-xs', 'border-t border-secondary-dark' => $role !== ''])>{{ $role }}</div>
    @endforeach
</div>
