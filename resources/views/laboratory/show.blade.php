<x-layout title="Laboratory services">
    <a href="{{ route('laboratory.index',['date'=>$visit->visit_date->toDateString()]) }}" class="text-emerald-800 underline">Back to laboratory</a>
    <h1 class="mt-5 text-3xl font-semibold">Laboratory services</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-1 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }} &middot; Queue: {{ $visit->queue_reference ?? 'Not recorded' }}</p>
    <section class="my-6 space-y-4">
        @forelse($records as $entry)
            <article class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">{{ $entry->test_name }}</h2>
                <p class="mt-2 text-sm">{{ App\Models\LaboratoryRecord::STATUSES[$entry->availability_status] }}</p>
                @if($entry->availability_status === 'PERFORMED_RHU')
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h3 class="font-semibold">Laboratory result</h3>
                        @if($entry->result_rows)
                            @if($entry->specimen)<p class="mt-2 text-sm">Specimen: {{ $entry->specimen }}</p>@endif
                            <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">
                                <thead><tr>@foreach(['Measurement / component', 'Result', 'Unit', 'Reference range', 'Report flag'] as $heading)<th class="border-b p-2">{{ $heading }}</th>@endforeach</tr></thead>
                                <tbody>@foreach($entry->result_rows as $row)<tr>@foreach(['measurement','value','unit','reference_range','flag'] as $key)<td class="border-b p-2 whitespace-pre-wrap break-words">{{ $row[$key] ?? 'Not recorded' }}</td>@endforeach</tr>@endforeach</tbody>
                            </table></div>
                        @elseif(!$entry->result)<p class="mt-2 text-sm">Result not recorded.</p>@endif
                        @if($entry->result)<p class="mt-3 whitespace-pre-wrap break-words text-sm">Previous text result: {{ $entry->result }}</p>@endif
                        @if($entry->completed_at)<p class="mt-3 text-xs text-slate-500">Results completed {{ $entry->completed_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
                    </div>
                @endif
                @if($entry->external_advice)<p class="mt-3 whitespace-pre-wrap break-words text-sm">External advice: {{ $entry->external_advice }}</p>@endif
                @if($entry->notes)<p class="mt-3 whitespace-pre-wrap break-words text-sm">{{ $entry->notes }}</p>@endif
                <p class="mt-3 text-xs text-slate-500">Recorded {{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} &middot; Updated {{ $entry->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                <a class="mt-3 inline-block text-sm font-semibold text-emerald-800 underline" href="{{ route('laboratory.edit',[$patient,$visit,$entry]) }}">{{ $entry->availability_status === 'PENDING' && $visit->status === 'OPEN' && auth()->user()->can('laboratory.record') ? 'Record results / update request' : 'Open record' }}</a>
            </article>
        @empty<p class="text-slate-600">No laboratory services recorded.</p>@endforelse
        {{ $records->links() }}
    </section>
    @if($visit->status === 'OPEN' && auth()->user()->can('laboratory.record'))
        @include('laboratory.form')
    @endif
</x-layout>
