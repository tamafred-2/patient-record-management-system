<x-layout title="Nurse authorization">
    <h1 class="text-3xl font-semibold">Nurse authorization</h1>
    <p class="mt-3 max-w-3xl text-slate-600">Manage nurses’ laboratory ordering access for patients they assessed.</p>
    <x-table-filters label="Search nurses, doctors or permitted tests" :filters="[
        'ordering' => ['label' => 'Laboratory ordering', 'options' => ['on' => 'On', 'off' => 'Off', 'unusable' => 'On but not usable']],
        'grants' => ['label' => 'Older visit grants', 'options' => ['with' => 'With grants', 'without' => 'Without grants']],
    ]" />
    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table data-server-filtered class="min-w-full text-left text-sm">
            <caption class="sr-only">Nurses and their laboratory ordering access</caption>
            <thead class="bg-slate-50 text-slate-700">
                <tr>
                    <th scope="col" class="p-3 font-semibold">Nurse</th>
                    <th scope="col" class="p-3 font-semibold">Laboratory ordering</th>
                    <th scope="col" class="p-3 font-semibold">Permitted tests</th>
                    <th scope="col" class="p-3 font-semibold">Turned on by</th>
                    <th scope="col" class="p-3 font-semibold">Older visit grants</th>
                    <th scope="col" class="p-3 font-semibold">Action</th>
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
                        <td class="whitespace-nowrap p-3"><a href="{{ route('nurse-authorizations.show', $nurse) }}" class="inline-block whitespace-nowrap font-semibold text-emerald-800 underline" aria-label="Manage {{ $nurse->name }}">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-slate-600">No nurses match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
