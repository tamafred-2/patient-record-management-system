<x-print-sheet title="Laboratory result" size="A4" :patient="$patient" :visit="$visit" :back="route('laboratory.show', [$patient, $visit])">
    <h2>{{ $record->test_name }}</h2>
    <dl>
        <div><dt>Record / version</dt><dd>#{{ $record->id }} / {{ $record->lock_version }}</dd></div>
        <div><dt>Specimen</dt><dd>{{ $record->specimen ?: 'Not recorded' }}</dd></div>
        <div><dt>Results completed in system</dt><dd>{{ $record->completed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Not recorded' }}</dd></div>
        <div><dt>Last updated</dt><dd>{{ $record->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
    </dl>
    @if($record->result_rows)
        <h2>Recorded results</h2>
        <table aria-label="Laboratory results">
            <thead><tr>@foreach(['Measurement / component', 'Result', 'Unit', 'Reference range', 'Report flag'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>@foreach($record->result_rows as $row)<tr>@foreach(['measurement', 'value', 'unit', 'reference_range', 'flag'] as $key)<td class="text">{{ filled($row[$key] ?? null) ? $row[$key] : '—' }}</td>@endforeach</tr>@endforeach</tbody>
        </table>
    @endif
    @if(filled($record->result))<h2>Previous text result</h2><p class="text">{{ $record->result }}</p>@endif
    @if(filled($record->notes))<h2>Recorded notes</h2><p class="text">{{ $record->notes }}</p>@endif
    <p class="note">Last recorded by {{ $record->updater?->name ?? 'Not recorded' }}. Values, units, ranges and flags are reproduced from the saved record. This copy does not establish clinical review, signing or release approval.</p>
</x-print-sheet>
