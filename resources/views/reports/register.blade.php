    <table><thead><tr><th>Visit date</th><th>Visit number</th><th>Requested service</th><th>Status</th>@foreach($columnLabels as $key=>$label)@if(in_array($key,$columns))<th>{{ $label }}</th>@endif@endforeach</tr></thead><tbody>
    @forelse($register as $visit)<tr>
        <td>{{ $visit->visit_date->format('Y-m-d') }}</td><td>{{ $visit->visit_number }}</td><td>{{ $names[$visit->service_id] ?? 'Former service' }}</td><td>{{ $visit->status }}</td>
        @foreach($columnLabels as $key=>$label)@if(in_array($key,$columns))<td>@if($key === 'queue_reference'){{ $visit->queue_reference ?? 'Not recorded' }}@elseif($key === 'completed_at'){{ $visit->completed_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? 'Not recorded' }}@else{{ $visit->completedBy?->name ?? 'Not recorded' }}@endif</td>@endif@endforeach
    </tr>@empty<tr><td colspan="{{ 4 + count($columns) }}">No visits match the selected filters.</td></tr>@endforelse
    </tbody></table>