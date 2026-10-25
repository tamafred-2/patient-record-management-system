<x-layout title="Nurse authorization">
    <h1 class="text-3xl font-semibold">Nurse authorization</h1>
    <p class="mt-3 max-w-3xl text-slate-600">Doctors decide which nurses may order follow-up laboratory tests after their assessment. Access applies to every patient the nurse assessed until a doctor turns it off. Use <strong>Manage</strong> to turn access on or off, choose permitted tests, see the change history and withdraw older visit-specific authorizations.</p>
    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <caption class="sr-only">Nurses and their laboratory ordering access</caption>
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th scope="col" class="p-3 font-semibold">Nurse</th>
                    <th scope="col" class="p-3 font-semibold">Laboratory ordering</th>
                    <th scope="col" class="p-3 font-semibold">Permitted tests</th>
                    <th scope="col" class="p-3 font-semibold">Turned on by</th>
                    <th scope="col" class="p-3 font-semibold">Older visit grants</th>
                    <th scope="col" class="p-3 font-semibold"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($nurses as $nurse)
                    @php
                        $current = $active->get($nurse->id);
                        $eligible = App\Support\FollowupAccess::nurse($nurse) && ! $nurse->is(auth()->user());
                        [$label, $tone] = ! $current ? ['Off', 'border-slate-300 bg-slate-50 text-slate-700']
                            : (! $eligible ? ['On · account not eligible', 'border-amber-300 bg-amber-50 text-amber-900']
                            : ($current->usable() ? ['On', 'border-emerald-300 bg-emerald-50 text-emerald-900'] : ['On · not usable', 'border-amber-300 bg-amber-50 text-amber-900']));
                    @endphp
                    <tr class="border-t border-slate-100 align-top">
                        <th scope="row" class="p-3 font-semibold">{{ $nurse->name }}</th>
                        <td class="p-3"><span class="inline-block whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-bold {{ $tone }}">{{ $label }}</span></td>
                        <td class="p-3">{{ $current?->allowedTestsLabel() ?? '—' }}</td>
                        <td class="p-3">@if($current){{ $current->doctor->name }}<br><span class="text-slate-500">{{ $current->created_at->timezone('Asia/Manila')->format('M j, Y') }}</span>@else — @endif</td>
                        <td class="p-3">{{ $olderGrants->get($nurse->id, 0) ?: '—' }}</td>
                        <td class="p-3 text-right"><a href="{{ route('nurse-authorizations.show', $nurse) }}" class="inline-block rounded-lg border border-emerald-800 px-4 py-2 font-semibold text-emerald-800" aria-label="Manage {{ $nurse->name }}">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-slate-600">No active nurse accounts with follow-up access.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
